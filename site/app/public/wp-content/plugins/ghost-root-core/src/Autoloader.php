<?php
/**
 * PSR-4-ish autoloader for the GhostRoot namespace.
 *
 * @package GhostRoot
 */

namespace GhostRoot;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	/**
	 * Map GhostRoot\Sub\Class -> src/Sub/Class.php
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function load( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = GHOST_ROOT_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
