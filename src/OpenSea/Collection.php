<?php
/**
 * Collection metadata reads + sanitisation for public output.
 *
 * @package GhostRoot
 */

namespace GhostRoot\OpenSea;

defined( 'ABSPATH' ) || exit;

final class Collection {

	/** Raw collection object from OpenSea (cached), or WP_Error. */
	public static function fetch( string $slug ) {
		return Client::get( '/collections/' . rawurlencode( $slug ) );
	}

	/** Cached, sanitised public subset. Never leaks editors/owner unless asked. */
	public static function public_data(): array {
		$slug = Config::collection_slug();
		if ( '' === $slug ) {
			return [ 'available' => false, 'reason' => Config::status() ];
		}
		$hit = Cache::remember( 'collection', Config::ttl( 'collection' ), static fn() => self::fetch( $slug ) );
		if ( null === $hit['data'] ) {
			return [ 'available' => false, 'reason' => 'unavailable' ];
		}
		return self::shape( $hit['data'], $hit['state'] );
	}

	/** @param array<string,mixed> $c */
	public static function shape( array $c, string $cache_state = 'fresh' ): array {
		$contracts = [];
		foreach ( (array) ( $c['contracts'] ?? [] ) as $ct ) {
			if ( is_array( $ct ) && isset( $ct['address'], $ct['chain'] ) ) {
				$contracts[] = [
					'address' => sanitize_text_field( (string) $ct['address'] ),
					'chain'   => sanitize_key( (string) $ct['chain'] ),
				];
			}
		}
		return [
			'available'    => true,
			'cache_state'  => $cache_state,
			'slug'         => sanitize_title( (string) ( $c['collection'] ?? '' ) ),
			'name'         => sanitize_text_field( (string) ( $c['name'] ?? '' ) ),
			'description'  => wp_kses_post( (string) ( $c['description'] ?? '' ) ),
			'category'     => sanitize_text_field( (string) ( $c['category'] ?? '' ) ),
			'image_url'    => esc_url_raw( (string) ( $c['image_url'] ?? '' ) ) ?: null,
			'banner_url'   => esc_url_raw( (string) ( $c['banner_image_url'] ?? '' ) ) ?: null,
			'opensea_url'  => esc_url_raw( (string) ( $c['opensea_url'] ?? '' ) ) ?: null,
			'project_url'  => esc_url_raw( (string) ( $c['project_url'] ?? '' ) ) ?: null,
			'total_supply' => isset( $c['total_supply'] ) ? (int) $c['total_supply'] : null,
			'item_count'   => isset( $c['unique_item_count'] ) ? (int) $c['unique_item_count'] : null,
			'safelist'     => sanitize_key( (string) ( $c['safelist_status'] ?? '' ) ),
			'is_disabled'  => (bool) ( $c['is_disabled'] ?? false ),
			'contracts'    => $contracts,
			'fees'         => self::fees( (array) ( $c['fees'] ?? [] ) ),
		];
	}

	/** @param array<int,mixed> $fees */
	private static function fees( array $fees ): array {
		$out = [];
		foreach ( $fees as $f ) {
			if ( is_array( $f ) && isset( $f['fee'] ) ) {
				$out[] = [
					'pct'       => round( (float) $f['fee'], 2 ),
					'recipient' => sanitize_text_field( (string) ( $f['recipient'] ?? '' ) ),
					'required'  => (bool) ( $f['required'] ?? false ),
				];
			}
		}
		return $out;
	}

	/** Cached collection holders count fallback (when stats.num_owners is absent). */
	public static function holders_count(): ?int {
		$slug = Config::collection_slug();
		if ( '' === $slug ) {
			return null;
		}
		$hit = Cache::remember(
			'holders',
			Config::ttl( 'stats' ),
			static fn() => Client::get( '/collections/' . rawurlencode( $slug ) . '/holders', [ 'limit' => 1 ] )
		);
		$data = $hit['data'];
		if ( ! is_array( $data ) ) {
			return null;
		}
		// The list endpoint does not return a grand total; only use it to prove
		// there is >=1 holder. Real owner counts come from Stats::num_owners.
		return isset( $data['holders'] ) && is_array( $data['holders'] ) && $data['holders'] ? 1 : 0;
	}
}
