<?php
/**
 * Plugin Name:       GHOST//ROOT Core
 * Plugin URI:        https://ghostroot.local
 * Description:        Business logic for the GHOST//ROOT recovered-identity network: post types, taxonomies, identity data model, system state, terminal, ARG engine, mutation engine, metadata importer, REST API, WP-CLI, admin.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            GHOST//ROOT
 * License:           GPL-2.0-or-later
 * Text Domain:       ghost-root
 *
 * @package GhostRoot
 */

namespace GhostRoot;

defined( 'ABSPATH' ) || exit;

define( 'GHOST_ROOT_VERSION', '1.0.0' );
define( 'GHOST_ROOT_FILE', __FILE__ );
define( 'GHOST_ROOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'GHOST_ROOT_URL', plugin_dir_url( __FILE__ ) );

require_once GHOST_ROOT_DIR . 'src/Autoloader.php';
Autoloader::register();

register_activation_hook( __FILE__, [ Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Activator::class, 'deactivate' ] );

/**
 * Boot the plugin once all plugins are loaded.
 */
function boot(): Plugin {
	static $plugin = null;
	if ( null === $plugin ) {
		$plugin = new Plugin();
		$plugin->init();
	}
	return $plugin;
}

boot();
