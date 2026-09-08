<?php
/**
 * Server-side OpenSea API v2 client.
 *
 * The ONLY place ghostroot.site talks to OpenSea. Runs on the WP backend with
 * `wp_remote_request`; the browser never sees the endpoint or the key.
 *
 *  - `X-API-KEY` header on every call; redacted in every log line.
 *  - Reads live `x-ratelimit-*` headers into a transient for the admin panel.
 *  - 429 / 5xx / transport error -> WP_Error (the Cache layer then serves the
 *    last good copy). We do NOT sleep-and-retry inside a page request; the cron
 *    warmer absorbs transient failures.
 *  - Short timeout, fail-closed, no stack traces to callers.
 *
 * @package GhostRoot
 */

namespace GhostRoot\OpenSea;

use GhostRoot\Support\Log;
use GhostRoot\Support\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class Client {

	private const RL_TRANSIENT = 'ghost_root_opensea_rl';

	/**
	 * GET a v2 path. Returns decoded array on success, WP_Error otherwise.
	 *
	 * @param string               $path  e.g. "/collections/foo/stats"
	 * @param array<string,scalar> $query Query args.
	 */
	public static function get( string $path, array $query = [] ) {
		return self::request( 'GET', $path, $query, null );
	}

	/**
	 * @param string                    $method GET|POST|PATCH
	 * @param string                    $path   v2 path.
	 * @param array<string,scalar>       $query  Query args.
	 * @param array<string,mixed>|null   $body   JSON body for writes.
	 * @param string|null                $bearer Optional wallet JWT (never the PAT).
	 */
	public static function request( string $method, string $path, array $query = [], ?array $body = null, ?string $bearer = null ) {
		$key = Config::api_key();
		if ( '' === $key ) {
			return new \WP_Error( 'gr_opensea_disabled', 'OpenSea integration is not configured on this server.', [ 'status' => 503 ] );
		}

		// Defensive client-side throttle shared with the rest of the plugin.
		if ( ! RateLimiter::allow( 'opensea-egress', 90, 60 ) ) {
			Log::line( 'opensea_self_throttled', [ 'endpoint' => $path ] );
			return new \WP_Error( 'gr_opensea_throttle', 'Local OpenSea request budget exhausted; try later.', [ 'status' => 429 ] );
		}

		$url = Config::base_url() . '/' . ltrim( $path, '/' );
		if ( $query ) {
			// add_query_arg() urlencodes values itself — do not pre-encode.
			$url = add_query_arg( array_filter( $query, static fn( $v ) => '' !== $v && null !== $v ), $url );
		}

		$args = [
			'method'     => $method,
			'timeout'    => Config::http_timeout(),
			'redirection' => 2,
			'headers'    => [
				'accept'     => 'application/json',
				'x-api-key'  => $key,
				'user-agent' => 'ghostroot.site/1.0 (+https://ghostroot.site)',
			],
		];
		if ( null !== $body ) {
			$args['headers']['content-type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}
		if ( $bearer ) {
			$args['headers']['authorization'] = 'Bearer ' . $bearer;
		}

		$started  = microtime( true );
		$response = wp_remote_request( $url, $args );
		$ms       = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $response ) ) {
			Log::line( 'opensea_transport_error', [ 'endpoint' => $path, 'ms' => $ms, 'err' => $response->get_error_code() ] );
			return new \WP_Error( 'gr_opensea_transport', 'OpenSea is unreachable.', [ 'status' => 502 ] );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$headers = wp_remote_retrieve_headers( $response );
		self::record_rate_limit( $headers );

		Log::line(
			'opensea_request',
			[
				'endpoint'     => $path,
				'method'       => $method,
				'status'       => $code,
				'ms'           => $ms,
				'rl_remaining' => is_object( $headers ) ? ( $headers['x-ratelimit-remaining'] ?? '?' ) : '?',
				'key'          => self::redact( $key ),
			]
		);

		$raw  = wp_remote_retrieve_body( $response );
		$json = json_decode( $raw, true );

		if ( 429 === $code ) {
			return new \WP_Error( 'gr_opensea_rate', 'OpenSea rate limit reached.', [ 'status' => 429, 'retry_after' => (int) ( is_object( $headers ) ? ( $headers['retry-after'] ?? 0 ) : 0 ) ] );
		}
		if ( $code >= 500 ) {
			return new \WP_Error( 'gr_opensea_5xx', 'OpenSea service error.', [ 'status' => 502 ] );
		}
		if ( $code >= 400 ) {
			$msg = is_array( $json ) && ! empty( $json['errors'][0] ) ? (string) $json['errors'][0] : 'OpenSea request rejected.';
			return new \WP_Error( 'gr_opensea_' . $code, sanitize_text_field( $msg ), [ 'status' => $code ] );
		}
		if ( null === $json && '' !== trim( (string) $raw ) ) {
			return new \WP_Error( 'gr_opensea_json', 'Malformed response from OpenSea.', [ 'status' => 502 ] );
		}

		return is_array( $json ) ? $json : [];
	}

	/** @return array{limit:?int,remaining:?int,reset:?int,seen_at:int} */
	public static function rate_limit_snapshot(): array {
		$t = get_transient( self::RL_TRANSIENT );
		return is_array( $t ) ? $t : [ 'limit' => null, 'remaining' => null, 'reset' => null, 'seen_at' => 0 ];
	}

	private static function record_rate_limit( $headers ): void {
		if ( ! is_object( $headers ) && ! is_array( $headers ) ) {
			return;
		}
		$get = static function ( $h, $k ) {
			if ( is_object( $h ) && method_exists( $h, 'offsetGet' ) ) {
				return $h->offsetExists( $k ) ? $h[ $k ] : null;
			}
			return is_array( $h ) ? ( $h[ $k ] ?? null ) : null;
		};
		$limit     = $get( $headers, 'x-ratelimit-limit' );
		$remaining = $get( $headers, 'x-ratelimit-remaining' );
		$reset     = $get( $headers, 'x-ratelimit-reset' );
		if ( null === $limit && null === $remaining ) {
			return;
		}
		set_transient(
			self::RL_TRANSIENT,
			[
				'limit'     => null !== $limit ? (int) $limit : null,
				'remaining' => null !== $remaining ? (int) $remaining : null,
				'reset'     => null !== $reset ? (int) $reset : null,
				'seen_at'   => time(),
			],
			2 * HOUR_IN_SECONDS
		);
	}

	/** Short, non-reversible fingerprint. Safe to log. */
	public static function redact( string $s ): string {
		$len = strlen( $s );
		if ( $len < 8 ) {
			return '***';
		}
		return substr( $s, 0, 3 ) . '…' . substr( $s, -2 ) . '(' . $len . ')';
	}
}
