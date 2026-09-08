<?php
/**
 * Structured logging. Never records secrets, cookies, auth headers or IPs in the clear.
 *
 * @package GhostRoot
 */

namespace GhostRoot\Support;

defined( 'ABSPATH' ) || exit;

final class Log {

	private const REDACT = '/(private[_\s-]?key|seed[_\s-]?phrase|mnemonic|signing[_\s-]?secret|wallet[_\s-]?secret|password|authorization|cookie|bearer)/i';

	/**
	 * Write a structured line. Only emits when WP_DEBUG is on (or filter forces it).
	 *
	 * @param string               $event   Event slug, e.g. "metadata_import".
	 * @param array<string,scalar>  $context Key/value pairs. Values are redacted + scalar-cast.
	 */
	public static function line( string $event, array $context = [] ): void {
		$enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		/** This filter lets ops force logging without global WP_DEBUG. */
		if ( ! apply_filters( 'ghost_root_logging_enabled', $enabled, $event ) ) {
			return;
		}

		$parts = [ '[ghost-root] ' . $event ];
		foreach ( $context as $k => $v ) {
			$key = preg_replace( '/[^a-z0-9_]/i', '', (string) $k );
			$val = is_scalar( $v ) ? (string) $v : wp_json_encode( $v );
			$val = preg_replace( self::REDACT, '[redacted]', (string) $val );
			$val = preg_replace( '/\s+/', ' ', $val );
			$parts[] = $key . '=' . $val;
		}

		error_log( implode( ' ', $parts ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
