<?php
/**
 * Collection stats — normalised so the UI never renders a fake zero.
 * Absent figures are null and surface as "—" / "NO MARKET DATA".
 *
 * @package GhostRoot
 */

namespace GhostRoot\OpenSea;

defined( 'ABSPATH' ) || exit;

final class Stats {

	/** @return array normalised stats + cache_state + source */
	public static function public_data(): array {
		$slug = Config::collection_slug();
		if ( '' === $slug ) {
			return self::empty( Config::status() );
		}
		$hit = Cache::remember(
			'stats',
			Config::ttl( 'stats' ),
			static fn() => Client::get( '/collections/' . rawurlencode( $slug ) . '/stats' )
		);
		if ( null === $hit['data'] ) {
			return self::empty( 'unavailable' );
		}
		return self::normalise( (array) $hit['data'], $hit['state'] );
	}

	public static function empty( string $reason = 'none' ): array {
		return [
			'source'        => 'none',
			'reason'        => $reason,
			'cache_state'   => 'unavailable',
			'floor_price'   => null,
			'floor_symbol'  => null,
			'volume_total'  => null,
			'volume_24h'    => null,
			'volume_symbol' => null,
			'sales_total'   => null,
			'sales_24h'     => null,
			'num_owners'    => null,
			'market_cap'    => null,
			'listed_count'  => null,
		];
	}

	/** @param array<string,mixed> $raw */
	public static function normalise( array $raw, string $cache_state = 'fresh' ): array {
		$total     = is_array( $raw['total'] ?? null ) ? $raw['total'] : [];
		$intervals = is_array( $raw['intervals'] ?? null ) ? $raw['intervals'] : [];

		$day = null;
		foreach ( $intervals as $i ) {
			if ( is_array( $i ) && in_array( ( $i['interval'] ?? '' ), [ 'one_day', 'day', '24h' ], true ) ) {
				$day = $i;
				break;
			}
		}

		$num = static fn( $v ) => is_numeric( $v ) ? ( 0 + $v ) : null;

		return [
			'source'        => $total || $intervals ? 'opensea' : 'none',
			'reason'        => $total || $intervals ? '' : 'no_market_data',
			'cache_state'   => $cache_state,
			'floor_price'   => $num( $total['floor_price'] ?? null ),
			'floor_symbol'  => sanitize_text_field( (string) ( $total['floor_price_symbol'] ?? ( $total['volume_symbol'] ?? '' ) ) ) ?: null,
			'volume_total'  => $num( $total['volume'] ?? null ),
			'volume_24h'    => $num( $day['volume'] ?? null ),
			'volume_symbol' => sanitize_text_field( (string) ( $total['volume_symbol'] ?? ( $day['volume_symbol'] ?? '' ) ) ) ?: null,
			'sales_total'   => $num( $total['sales'] ?? null ),
			'sales_24h'     => $num( $day['sales'] ?? null ),
			'num_owners'    => $num( $total['num_owners'] ?? null ),
			'market_cap'    => $num( $total['market_cap'] ?? null ),
			'listed_count'  => null, // populated by Activity::listings_summary()
		];
	}

	/** Human display for a numeric+symbol pair, or an em-dash. */
	public static function fmt( ?float $value, ?string $symbol, int $precision = 2 ): string {
		if ( null === $value ) {
			return '—';
		}
		$n = number_format_i18n( $value, $value < 1 ? max( $precision, 3 ) : $precision );
		return $symbol ? $n . ' ' . $symbol : $n;
	}
}
