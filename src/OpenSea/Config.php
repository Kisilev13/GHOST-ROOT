<?php
/**
 * OpenSea integration configuration.
 *
 * SECURITY: the API key is read ONLY from server-side environment / a wp-config
 * constant. It is NEVER read from or written to the ghost_root_settings option
 * (that store rejects secret-shaped input anyway), never sent to the browser,
 * and never returned by any REST route.
 *
 *   define( 'GHOST_ROOT_OPENSEA_API_KEY', '...' );   // in wp-config.php  (preferred)
 *   OPENSEA_API_KEY=...                              // or a real env var
 *
 * Non-secret settings (slug, chain, TTLs) come from constants / filters / the
 * plugin settings option, in that order.
 *
 * @package GhostRoot
 */

namespace GhostRoot\OpenSea;

use GhostRoot\Settings\Config as SiteConfig;

defined( 'ABSPATH' ) || exit;

final class Config {

	public const CHAIN = 'solana';

	/** Verification / index states surfaced to /verify/ and the marketplace UI. */
	public const STATE_AWAITING   = 'AWAITING_SOLANA_COLLECTION_DEPLOYMENT';
	public const STATE_NOT_INDEXED = 'NOT_INDEXED';
	public const STATE_PENDING    = 'PENDING';
	public const STATE_PARTIAL    = 'PARTIAL';
	public const STATE_VERIFIED   = 'VERIFIED';
	public const STATE_MISMATCH   = 'MISMATCH';
	public const STATE_ERROR      = 'ERROR';

	/**
	 * The OpenSea API key. Empty string when not configured -> integration
	 * runs in "scaffolded but inert" mode and every surface degrades gracefully.
	 */
	public static function api_key(): string {
		if ( defined( 'GHOST_ROOT_OPENSEA_API_KEY' ) && is_string( GHOST_ROOT_OPENSEA_API_KEY ) ) {
			return trim( GHOST_ROOT_OPENSEA_API_KEY );
		}
		foreach ( [ 'OPENSEA_API_KEY', 'GHOST_ROOT_OPENSEA_API_KEY' ] as $name ) {
			$v = getenv( $name );
			if ( is_string( $v ) && '' !== trim( $v ) ) {
				return trim( $v );
			}
			if ( ! empty( $_SERVER[ $name ] ) && is_string( $_SERVER[ $name ] ) ) {
				return trim( $_SERVER[ $name ] );
			}
		}
		return '';
	}

	public static function has_api_key(): bool {
		return '' !== self::api_key();
	}

	public static function base_url(): string {
		$testnet = defined( 'GHOST_ROOT_OPENSEA_TESTNET' ) && GHOST_ROOT_OPENSEA_TESTNET;
		$url     = $testnet ? 'https://testnets-api.opensea.io/api/v2' : 'https://api.opensea.io/api/v2';
		return (string) apply_filters( 'ghost_root_opensea_base_url', $url );
	}

	public static function chain(): string {
		return (string) apply_filters( 'ghost_root_opensea_chain', self::CHAIN );
	}

	/**
	 * The resolved + VERIFIED OpenSea collection slug, or '' if unknown.
	 * A slug is only trusted once opensea:verify (the operator toolkit) has
	 * confirmed it and an admin has saved it here — never guessed at runtime.
	 */
	public static function collection_slug(): string {
		if ( defined( 'GHOST_ROOT_OPENSEA_SLUG' ) && is_string( GHOST_ROOT_OPENSEA_SLUG ) ) {
			return sanitize_title( GHOST_ROOT_OPENSEA_SLUG );
		}
		$env = getenv( 'OPENSEA_COLLECTION_SLUG' );
		if ( is_string( $env ) && '' !== trim( $env ) ) {
			return sanitize_title( $env );
		}
		return sanitize_title( (string) SiteConfig::get( 'opensea_collection_slug', '' ) );
	}

	/** The authoritative Solana / Metaplex Core collection address (base58) or ''. */
	public static function solana_collection_address(): string {
		$addr = (string) SiteConfig::get( 'collection_address', '' );
		if ( '' === $addr ) {
			$env  = getenv( 'SOLANA_COLLECTION_ADDRESS' );
			$addr = is_string( $env ) ? trim( $env ) : '';
		}
		return preg_match( '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/D', $addr ) ? $addr : '';
	}

	/** Coarse status independent of any live API call. */
	public static function status(): string {
		if ( '' === self::solana_collection_address() ) {
			return self::STATE_AWAITING;
		}
		if ( '' === self::collection_slug() ) {
			return self::STATE_NOT_INDEXED;
		}
		return self::STATE_PENDING; // refined by Verification against the live API
	}

	/**
	 * Cache TTL in seconds for a data class (task section 17). Filterable.
	 *
	 * @param 'collection'|'stats'|'floor'|'activity'|'listings'|'traits'|'verification'|'status' $type
	 */
	public static function ttl( string $type ): int {
		$defaults = [
			'collection'   => 3 * HOUR_IN_SECONDS,
			'stats'        => 10 * MINUTE_IN_SECONDS,
			'floor'        => 2 * MINUTE_IN_SECONDS,
			'activity'     => 90,
			'listings'     => 2 * MINUTE_IN_SECONDS,
			'traits'       => 12 * HOUR_IN_SECONDS,
			'verification' => DAY_IN_SECONDS,
			'status'       => 5 * MINUTE_IN_SECONDS,
		];
		$ttl = $defaults[ $type ] ?? 5 * MINUTE_IN_SECONDS;
		return max( 30, (int) apply_filters( 'ghost_root_opensea_ttl', $ttl, $type ) );
	}

	public static function http_timeout(): int {
		return (int) apply_filters( 'ghost_root_opensea_http_timeout', 12 );
	}

	/** Non-secret public config for the /status REST route + admin screen. */
	public static function public_snapshot(): array {
		return [
			'enabled'          => self::has_api_key(),
			'chain'            => self::chain(),
			'collection_slug'  => self::collection_slug() ?: null,
			'solana_address'   => self::solana_collection_address() ?: null,
			'status'           => self::status(),
			'key_source'       => self::key_source(),
		];
	}

	/** Where the key came from — for the admin screen. Never the key itself. */
	public static function key_source(): string {
		if ( defined( 'GHOST_ROOT_OPENSEA_API_KEY' ) ) {
			return 'wp-config constant';
		}
		if ( getenv( 'OPENSEA_API_KEY' ) || getenv( 'GHOST_ROOT_OPENSEA_API_KEY' ) ) {
			return 'environment variable';
		}
		if ( ! empty( $_SERVER['OPENSEA_API_KEY'] ) ) {
			return 'server var';
		}
		return 'not configured';
	}
}
