<?php
/**
 * REST — namespace ghost-root/v1, prefix /opensea.
 *
 * THREAT MODEL
 *  - Every GET route serves cached, sanitised marketplace data that is already
 *    public on opensea.io. permission_callback = named `public_read`.
 *  - The API key is NEVER included in any response.
 *  - Routes fail closed: on any OpenSea error they return HTTP 200 with
 *    { available:false, reason:... } so the front end degrades, never 500s.
 *  - POST /opensea/refresh requires `manage_options` + a nonce (cache purge only;
 *    it performs no OpenSea write).
 *
 * @package GhostRoot
 */

namespace GhostRoot\OpenSea;

use GhostRoot\Support\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class Rest {

	private const NS = 'ghost-root/v1';

	public function hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register' ] );
	}

	public function public_read( \WP_REST_Request $req ): bool {
		return true;
	}

	public function require_admin( \WP_REST_Request $req ): bool {
		return current_user_can( 'manage_options' )
			&& (bool) wp_verify_nonce( (string) $req->get_header( 'x-wp-nonce' ), 'wp_rest' );
	}

	public function register(): void {
		$ro = [
			'methods'             => \WP_REST_Server::READABLE,
			'permission_callback' => [ $this, 'public_read' ],
		];

		register_rest_route( self::NS, '/opensea/status', $ro + [ 'callback' => [ $this, 'status' ] ] );
		register_rest_route( self::NS, '/opensea/collection', $ro + [ 'callback' => [ $this, 'collection' ] ] );
		register_rest_route( self::NS, '/opensea/stats', $ro + [ 'callback' => [ $this, 'stats' ] ] );
		register_rest_route(
			self::NS,
			'/opensea/activity',
			$ro + [
				'callback' => [ $this, 'activity' ],
				'args'     => [ 'limit' => [ 'default' => 12, 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route( self::NS, '/opensea/listings', $ro + [ 'callback' => [ $this, 'listings' ] ] );
		register_rest_route( self::NS, '/opensea/verification', $ro + [ 'callback' => [ $this, 'verification' ] ] );

		register_rest_route(
			self::NS,
			'/opensea/refresh',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => [ $this, 'require_admin' ],
				'callback'            => [ $this, 'refresh' ],
			]
		);
	}

	private function guard( string $bucket, int $limit = 30 ): ?\WP_REST_Response {
		if ( ! RateLimiter::allow( 'opensea-' . $bucket, $limit, 60 ) ) {
			$r = new \WP_REST_Response( [ 'available' => false, 'reason' => 'rate_limited' ], 200 );
			$r->header( 'Cache-Control', 'no-store' );
			return $r;
		}
		return null;
	}

	private function ok( $data, int $max_age ): \WP_REST_Response {
		$r = new \WP_REST_Response( $data, 200 );
		$r->header( 'Cache-Control', 'public, max-age=' . max( 15, $max_age ) . ', stale-while-revalidate=60' );
		return $r;
	}

	public function status( \WP_REST_Request $req ) {
		if ( $g = $this->guard( 'status', 60 ) ) {
			return $g;
		}
		$snap = Config::public_snapshot();
		unset( $snap['key_source'] ); // internal only
		$snap['generated'] = gmdate( 'c' );
		return $this->ok( $snap, Config::ttl( 'status' ) );
	}

	public function collection( \WP_REST_Request $req ) {
		if ( $g = $this->guard( 'collection' ) ) {
			return $g;
		}
		return $this->ok( Collection::public_data(), Config::ttl( 'collection' ) );
	}

	public function stats( \WP_REST_Request $req ) {
		if ( $g = $this->guard( 'stats', 60 ) ) {
			return $g;
		}
		$stats   = Stats::public_data();
		$listing = Activity::listings_summary();
		if ( ! empty( $listing['available'] ) ) {
			$stats['listed_count'] = $listing['count'];
			if ( null === $stats['floor_price'] && null !== $listing['floor_price'] ) {
				$stats['floor_price']  = $listing['floor_price'];
				$stats['floor_symbol'] = $listing['symbol'];
			}
		}
		return $this->ok( $stats, Config::ttl( 'floor' ) );
	}

	public function activity( \WP_REST_Request $req ) {
		if ( $g = $this->guard( 'activity', 60 ) ) {
			return $g;
		}
		return $this->ok( Activity::recent( (int) $req['limit'] ), Config::ttl( 'activity' ) );
	}

	public function listings( \WP_REST_Request $req ) {
		if ( $g = $this->guard( 'listings', 60 ) ) {
			return $g;
		}
		return $this->ok( Activity::listings_summary(), Config::ttl( 'listings' ) );
	}

	public function verification( \WP_REST_Request $req ) {
		if ( $g = $this->guard( 'verification', 60 ) ) {
			return $g;
		}
		return $this->ok( Verification::report(), Config::ttl( 'verification' ) );
	}

	public function refresh( \WP_REST_Request $req ): \WP_REST_Response {
		Cache::purge_all();
		return new \WP_REST_Response( [ 'ok' => true, 'purged' => true ] );
	}
}
