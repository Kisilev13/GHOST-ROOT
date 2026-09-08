<?php
/**
 * GHOST//ROOT -> OpenSea admin screen (manage_options).
 *
 * Read-only status board + a nonce-protected "purge cache" button. No OpenSea
 * write is ever triggered from WordPress — mutations run through the operator
 * toolkit in /opensea with separate credentials. The API key is never shown.
 *
 * @package GhostRoot
 */

namespace GhostRoot\OpenSea;

defined( 'ABSPATH' ) || exit;

final class Admin {

	public function hooks(): void {
		add_action( 'admin_menu', [ $this, 'menu' ], 20 );
		add_action( 'admin_post_ghost_root_opensea_purge', [ $this, 'handle_purge' ] );
	}

	public function menu(): void {
		add_submenu_page(
			'ghost-root',
			'OpenSea',
			'OpenSea',
			'manage_options',
			'ghost-root-opensea',
			[ $this, 'render' ]
		);
	}

	public function handle_purge(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ghost_root_opensea_purge' ) ) {
			wp_die( 'Not allowed.' );
		}
		Cache::purge_all();
		Cache::put( 'verification', Verification::compute(), Config::ttl( 'verification' ) );
		wp_safe_redirect( add_query_arg( 'purged', '1', admin_url( 'admin.php?page=ghost-root-opensea' ) ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$snap = Config::public_snapshot();
		$v    = Verification::report();
		$rl   = Client::rate_limit_snapshot();

		echo '<div class="wrap"><h1>GHOST//ROOT — OpenSea integration</h1>';

		if ( isset( $_GET['purged'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>OpenSea cache purged and verification recomputed.</p></div>';
		}

		if ( ! $snap['enabled'] ) {
			echo '<div class="notice notice-warning"><p><strong>Inert.</strong> No <code>OPENSEA_API_KEY</code> is configured on this server. '
				. 'Set it as an environment variable or add <code>define( \'GHOST_ROOT_OPENSEA_API_KEY\', \'…\' );</code> to <code>wp-config.php</code>. '
				. 'All marketplace surfaces degrade to “awaiting / unavailable” until then.</p></div>';
		}

		$rows = [
			'Integration'          => $snap['enabled'] ? 'ENABLED' : 'INERT (no API key)',
			'API key source'       => esc_html( Config::key_source() ),
			'Chain'                => strtoupper( $snap['chain'] ),
			'Authoritative Solana address' => $snap['solana_address'] ?: 'NOT DEPLOYED',
			'OpenSea slug'         => $snap['collection_slug'] ?: 'NOT SET / NOT DISCOVERED',
			'Coarse status'        => $snap['status'],
			'Verification state'   => '<strong>' . esc_html( $v['state'] ) . '</strong>',
			'Indexed items'        => null !== $v['indexed_items'] ? number_format_i18n( $v['indexed_items'] ) : '—',
			'Verification cache'   => esc_html( $v['cache_state'] ?? '—' ),
			'Rate limit (last seen)' => $rl['remaining'] !== null ? intval( $rl['remaining'] ) . ' / ' . intval( $rl['limit'] ) . ' at ' . gmdate( 'H:i:s', (int) $rl['seen_at'] ) . ' UTC' : 'not observed yet',
		];

		echo '<table class="widefat striped" style="max-width:760px"><tbody>';
		foreach ( $rows as $k => $val ) {
			printf( '<tr><th style="width:38%%">%s</th><td><code>%s</code></td></tr>', esc_html( $k ), wp_kses_post( (string) $val ) );
		}
		echo '</tbody></table>';

		if ( ! empty( $v['checks'] ) ) {
			echo '<h2>Identity checks</h2><table class="widefat striped" style="max-width:760px"><thead><tr><th>Field</th><th>Expected</th><th>Actual</th><th>OK</th><th>Critical</th></tr></thead><tbody>';
			foreach ( $v['checks'] as $c ) {
				printf(
					'<tr><td>%s</td><td><code>%s</code></td><td><code>%s</code></td><td>%s</td><td>%s</td></tr>',
					esc_html( $c['field'] ),
					esc_html( $c['expected'] ),
					esc_html( $c['actual'] ),
					$c['ok'] ? '✅' : '❌',
					$c['critical'] ? 'yes' : 'no'
				);
			}
			echo '</tbody></table>';
		}

		if ( ! empty( $v['notes'] ) ) {
			echo '<h2>Notes</h2><ul style="list-style:disc;margin-left:1.4em">';
			foreach ( $v['notes'] as $n ) {
				echo '<li>' . esc_html( $n ) . '</li>';
			}
			echo '</ul>';
		}

		echo '<h2>Cache</h2><table class="widefat striped" style="max-width:760px"><tbody>';
		foreach ( [ 'collection', 'stats', 'activity', 'listings', 'verification' ] as $key ) {
			$peek = Cache::peek( $key );
			$age  = $peek ? ( time() - (int) $peek['t'] ) . 's ago' : 'empty';
			printf( '<tr><th style="width:38%%">%s</th><td><code>%s</code></td></tr>', esc_html( $key ), esc_html( $age ) );
		}
		echo '</tbody></table>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:1em">';
		echo '<input type="hidden" name="action" value="ghost_root_opensea_purge">';
		wp_nonce_field( 'ghost_root_opensea_purge' );
		submit_button( 'Purge OpenSea cache & re-verify', 'secondary', 'submit', false );
		echo '</form>';

		echo '<h2>Operator tasks (not run from WordPress)</h2>';
		echo '<pre style="background:#111;color:#e8e8e3;padding:1em;overflow:auto">'
			. esc_html(
				"cd opensea\n"
				. "npm run opensea:discover        # resolve + verify the slug from the on-chain address\n"
				. "npm run opensea:verify          # full identity + trait check\n"
				. "npm run opensea:profile-sync -- --account <wallet>   # dry-run profile diff\n"
				. "npm run opensea:collection-sync -- --dry-run          # dry-run collection diff\n"
				. "npm run opensea:health"
			)
			. '</pre>';
		echo '<p>Collection / profile writes require an interactive wallet signature and are performed by the operator toolkit, never by this site.</p>';

		echo '</div>';
	}
}
