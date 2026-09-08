<?php
/**
 * Recent collection activity + listings summary.
 * Path verified live: GET /events/collection/{slug} -> { asset_events, next }
 *
 * @package GhostRoot
 */

namespace GhostRoot\OpenSea;

defined( 'ABSPATH' ) || exit;

final class Activity {

	/** @return array{available:bool,rows:array,cache_state?:string} */
	public static function recent( int $limit = 12 ): array {
		$slug = Config::collection_slug();
		if ( '' === $slug ) {
			return [ 'available' => false, 'rows' => [], 'reason' => Config::status() ];
		}
		$limit = max( 1, min( 25, $limit ) );
		$hit   = Cache::remember(
			'activity',
			Config::ttl( 'activity' ),
			static fn() => Client::get( '/events/collection/' . rawurlencode( $slug ), [ 'limit' => $limit ] )
		);
		if ( null === $hit['data'] ) {
			return [ 'available' => false, 'rows' => [], 'reason' => 'unavailable' ];
		}
		$events = is_array( $hit['data']['asset_events'] ?? null ) ? $hit['data']['asset_events'] : [];
		return [
			'available'   => true,
			'cache_state' => $hit['state'],
			'rows'        => array_slice( array_map( [ self::class, 'row' ], $events ), 0, $limit ),
		];
	}

	/** @param array<string,mixed> $e */
	private static function row( array $e ): array {
		$payment = is_array( $e['payment'] ?? null ) ? $e['payment'] : null;
		$nft     = is_array( $e['nft'] ?? null ) ? $e['nft'] : [];
		return [
			'type'      => strtoupper( sanitize_key( (string) ( $e['event_type'] ?? 'event' ) ) ),
			'identity'  => sanitize_text_field( (string) ( $nft['name'] ?? ( $nft['identifier'] ?? '' ) ) ) ?: null,
			'price'     => $payment ? self::amount( $payment ) : null,
			'from'      => self::short_addr( (string) ( $e['from_address'] ?? ( $e['seller'] ?? '' ) ) ),
			'to'        => self::short_addr( (string) ( $e['to_address'] ?? ( $e['buyer'] ?? '' ) ) ),
			'timestamp' => isset( $e['event_timestamp'] ) ? (int) $e['event_timestamp'] : null,
			'url'       => esc_url_raw( (string) ( $nft['opensea_url'] ?? '' ) ) ?: null,
		];
	}

	/** @param array<string,mixed> $p */
	private static function amount( array $p ): ?string {
		if ( ! isset( $p['quantity'], $p['decimals'] ) ) {
			return null;
		}
		$decimals = max( 0, (int) $p['decimals'] );
		$value    = (float) $p['quantity'] / ( 10 ** $decimals );
		$symbol   = sanitize_text_field( (string) ( $p['symbol'] ?? '' ) );
		return rtrim( rtrim( number_format( $value, min( 6, $decimals ), '.', '' ), '0' ), '.' ) . ( $symbol ? ' ' . $symbol : '' );
	}

	private static function short_addr( string $addr ): ?string {
		$addr = trim( $addr );
		if ( '' === $addr ) {
			return null;
		}
		return strlen( $addr ) > 12 ? substr( $addr, 0, 4 ) . '…' . substr( $addr, -4 ) : sanitize_text_field( $addr );
	}

	/** { count, floor_price, symbol } from best listings, or nulls. */
	public static function listings_summary(): array {
		$slug = Config::collection_slug();
		if ( '' === $slug ) {
			return [ 'available' => false, 'count' => null, 'floor_price' => null, 'symbol' => null ];
		}
		$hit = Cache::remember(
			'listings',
			Config::ttl( 'listings' ),
			static fn() => Client::get( '/listings/collection/' . rawurlencode( $slug ) . '/best', [ 'limit' => 100 ] )
		);
		$data = $hit['data'];
		if ( ! is_array( $data ) || ! isset( $data['listings'] ) || ! is_array( $data['listings'] ) ) {
			return [ 'available' => false, 'count' => null, 'floor_price' => null, 'symbol' => null ];
		}
		$listings = $data['listings'];
		$floor    = null;
		$symbol   = null;
		foreach ( $listings as $l ) {
			$cur = $l['price']['current'] ?? null;
			if ( is_array( $cur ) && isset( $cur['value'], $cur['decimals'] ) ) {
				$v = (float) $cur['value'] / ( 10 ** (int) $cur['decimals'] );
				if ( null === $floor || $v < $floor ) {
					$floor  = $v;
					$symbol = sanitize_text_field( (string) ( $cur['currency'] ?? '' ) );
				}
			}
		}
		return [
			'available'   => true,
			'cache_state' => $hit['state'],
			'count'       => count( $listings ),
			'floor_price' => $floor,
			'symbol'      => $symbol,
		];
	}
}
