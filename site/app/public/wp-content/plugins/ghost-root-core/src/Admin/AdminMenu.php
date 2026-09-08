<?php
/**
 * GHOST//ROOT admin menu + status dashboard.
 *
 * @package GhostRoot
 */

namespace GhostRoot\Admin;

use GhostRoot\Settings\Config;
use GhostRoot\Settings\Settings;
use GhostRoot\Support\Vocab;
use GhostRoot\Arg\Challenges;

defined( 'ABSPATH' ) || exit;

final class AdminMenu {

	public function hooks(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
	}

	public function menu(): void {
		add_menu_page(
			'GHOST//ROOT',
			'GHOST//ROOT',
			'edit_posts',
			'ghost-root',
			[ $this, 'dashboard' ],
			'dashicons-shield-alt',
			3
		);
		add_submenu_page( 'ghost-root', 'Overview', 'Overview', 'edit_posts', 'ghost-root', [ $this, 'dashboard' ] );
		add_submenu_page( 'ghost-root', 'System', 'System', 'manage_options', 'ghost-root-system', [ $this, 'system' ] );
		add_submenu_page( 'ghost-root', 'Identities', 'Identities', 'edit_posts', 'edit.php?post_type=ghost_identity' );
		add_submenu_page( 'ghost-root', 'Incidents', 'Incidents', 'edit_posts', 'edit.php?post_type=incident' );
		add_submenu_page( 'ghost-root', 'Transmissions', 'Transmissions', 'edit_posts', 'edit.php?post_type=transmission' );
		add_submenu_page( 'ghost-root', 'Archive', 'Archive', 'edit_posts', 'edit.php?post_type=archive_record' );
		add_submenu_page( 'ghost-root', 'Import', 'Import', 'manage_options', 'ghost-root-import', [ $this, 'import_help' ] );
		add_submenu_page( 'ghost-root', 'Verification', 'Verification', 'manage_options', 'ghost-root-verify', [ $this, 'verify' ] );
	}

	public function system(): void {
		( new Settings() )->render_page();
	}

	public function dashboard(): void {
		$c    = Config::all();
		$rows = [
			'System state'        => Config::system_state(),
			'Network status'      => (string) $c['network_status'],
			'Mint state'          => (string) $c['mint_state'],
			'Identities imported' => wp_count_posts( 'ghost_identity' )->publish . ' / ' . Vocab::SUPPLY,
			'Incidents'           => wp_count_posts( 'incident' )->publish,
			'Archive records'     => wp_count_posts( 'archive_record' )->publish,
			'Transmissions'       => wp_count_posts( 'transmission' )->publish,
			'Collection address'  => $c['collection_address'] ?: 'NOT YET DEPLOYED',
			'Candy Machine'       => $c['candy_machine_address'] ?: 'NOT YET DEPLOYED',
			'Treasury'            => $c['treasury_address'] ?: 'NOT YET DEPLOYED',
			'Contract verified'   => $c['contract_verified'] ? 'YES' : 'NO',
			'ARG challenges'      => count( Challenges::all() ),
		];
		echo '<div class="wrap"><h1>GHOST//ROOT — Overview</h1><table class="widefat striped" style="max-width:640px"><tbody>';
		foreach ( $rows as $k => $v ) {
			printf( '<tr><th style="width:40%%">%s</th><td><code>%s</code></td></tr>', esc_html( $k ), esc_html( (string) $v ) );
		}
		echo '</tbody></table>';
		echo '<p style="margin-top:1em"><a class="button" href="' . esc_url( admin_url( 'admin.php?page=ghost-root-system' ) ) . '">System settings</a> ';
		echo '<a class="button" href="' . esc_url( home_url( '/' ) ) . '">View site</a></p></div>';
	}

	public function import_help(): void {
		echo '<div class="wrap"><h1>GHOST//ROOT — Import</h1>';
		echo '<p>Metadata import runs through WP-CLI so it can be validated, dry-run and repeated safely.</p>';
		echo '<pre style="background:#111;color:#e8e8e3;padding:1em;overflow:auto">';
		echo esc_html(
			"# validate only\nwp ghost-root import /path/to/metadata --dry-run\n\n"
			. "# import a sample\nwp ghost-root import /path/to/metadata --limit=333\n\n"
			. "# full collection\nwp ghost-root import /path/to/metadata\n\n"
			. "# re-import / correct existing\nwp ghost-root import /path/to/metadata --update\n\n"
			. "wp ghost-root status"
		);
		echo '</pre>';
		echo '<p>The importer validates every trait against the GHOST//ROOT vocabulary, rejects path traversal and invalid IDs, and is fully idempotent.</p></div>';
	}

	public function verify(): void {
		$v = Config::verification();
		echo '<div class="wrap"><h1>GHOST//ROOT — Verification</h1><table class="widefat striped" style="max-width:720px"><tbody>';
		foreach ( $v as $k => $val ) {
			printf(
				'<tr><th style="width:32%%">%s</th><td><code>%s</code></td></tr>',
				esc_html( strtoupper( str_replace( '_', ' ', $k ) ) ),
				esc_html( $val ?? 'NOT YET DEPLOYED' )
			);
		}
		echo '</tbody></table>';
		echo '<p>These values are shown publicly on <a href="' . esc_url( home_url( '/verify/' ) ) . '">/verify/</a>. Set them on the System screen once real deployment addresses exist. Never enter private keys or seed phrases.</p></div>';
	}
}
