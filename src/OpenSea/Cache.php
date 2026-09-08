<?php
/**
 * Transient cache with stale-while-revalidate.
 *
 * `remember()` returns fresh data when available, otherwise fetches. When the
 * stored copy is merely stale (not missing) it returns the stale copy AND
 * queues a single background refresh so the next visitor gets fresh data
 * without this request blocking on OpenSea. When OpenSea fails and there is a
 * stale copy, the stale copy wins — the site never shows a fabricated zero.
 *
 * @package GhostRoot
 */

namespace GhostRoot\OpenSea;

use GhostRoot\Support\Log;

defined( 'ABSPATH' ) || exit;

final class Cache {

	private const PREFIX  = 'gr_os_';
	public const REFRESH_HOOK = 'ghost_root_opensea_refresh';
	/** Keys we know how to warm from cron. */
	public const WARMABLE = [ 'collection', 'stats', 'activity', 'listings', 'verification' ];

	/**
	 * @param string   $key      Logical key, e.g. "stats".
	 * @param int      $ttl      Fresh-window seconds.
	 * @param callable():(array|\WP_Error) $fetch Producer.
	 * @return array{data:mixed,state:'fresh'|'stale'|'unavailable',age:int}
	 */
	public static function remember( string $key, int $ttl, callable $fetch ): array {
		$stored = get_transient( self::PREFIX . $key );
		$now    = time();

		if ( is_array( $stored ) && isset( $stored['t'] ) ) {
			$age = $now - (int) $stored['t'];
			if ( $age <= $ttl ) {
				return [ 'data' => $stored['d'], 'state' => 'fresh', 'age' => $age ];
			}
			// Stale: schedule a refresh, serve stale now.
			self::queue_refresh( $key );
			$fresh = self::produce( $key, $ttl, $fetch );
			if ( null !== $fresh ) {
				return [ 'data' => $fresh, 'state' => 'fresh', 'age' => 0 ];
			}
			return [ 'data' => $stored['d'], 'state' => 'stale', 'age' => $age ];
		}

		$fresh = self::produce( $key, $ttl, $fetch );
		if ( null !== $fresh ) {
			return [ 'data' => $fresh, 'state' => 'fresh', 'age' => 0 ];
		}
		return [ 'data' => null, 'state' => 'unavailable', 'age' => -1 ];
	}

	/** Run the producer; store + return on success, null on WP_Error. */
	private static function produce( string $key, int $ttl, callable $fetch ): ?array {
		$result = $fetch();
		if ( is_wp_error( $result ) ) {
			Log::line( 'opensea_cache_miss_error', [ 'key' => $key, 'err' => $result->get_error_code() ] );
			return null;
		}
		self::put( $key, $result, $ttl );
		return is_array( $result ) ? $result : (array) $result;
	}

	public static function put( string $key, $data, int $ttl ): void {
		// Keep stale copies alive well past the fresh window for SWR fallback.
		set_transient( self::PREFIX . $key, [ 't' => time(), 'd' => $data ], max( $ttl * 6, DAY_IN_SECONDS ) );
	}

	public static function peek( string $key ) {
		$stored = get_transient( self::PREFIX . $key );
		return is_array( $stored ) && array_key_exists( 'd', $stored ) ? $stored : null;
	}

	public static function forget( string $key ): void {
		delete_transient( self::PREFIX . $key );
	}

	public static function purge_all(): void {
		foreach ( array_merge( self::WARMABLE, [ 'status', 'traits' ] ) as $k ) {
			self::forget( $k );
		}
		Log::line( 'opensea_cache_purged', [] );
	}

	private static function queue_refresh( string $key ): void {
		if ( ! in_array( $key, self::WARMABLE, true ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::REFRESH_HOOK, [ $key ] ) ) {
			wp_schedule_single_event( time() + 5, self::REFRESH_HOOK, [ $key ] );
		}
	}
}
