<?php
/**
 * Plugin Name: GHOST//ROOT hardening
 * Description: Durable security hardening for ghostroot.site — survives WP Engine wp-config re-sync. Added 2026-09-08 during Safe Browsing remediation.
 * Author: GHOST//ROOT
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/*
 * WP Engine periodically regenerates wp-config.php from a platform template, which
 * resets DISALLOW_FILE_EDIT. mu-plugins load after wp-config, so we cannot always
 * (re)define the constant — instead we neutralise the in-dashboard file editors
 * directly, which holds regardless of the constant's value.
 */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

add_action(
	'admin_menu',
	static function () {
		remove_submenu_page( 'themes.php', 'theme-editor.php' );
		remove_submenu_page( 'plugins.php', 'plugin-editor.php' );
	},
	999
);

add_action(
	'admin_init',
	static function () {
		global $pagenow;
		if ( in_array( $pagenow, array( 'theme-editor.php', 'plugin-editor.php' ), true ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}
);

// XML-RPC is not used by this site (no remote publishing, no Jetpack).
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter(
	'wp_headers',
	static function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);
