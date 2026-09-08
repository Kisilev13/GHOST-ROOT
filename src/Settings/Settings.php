<?php
/**
 * GHOST//ROOT -> System settings page. Every field sanitized. Secret-shaped input
 * (private keys, seed phrases, mnemonics, signing secrets) is rejected on save.
 *
 * @package GhostRoot
 */

namespace GhostRoot\Settings;

use GhostRoot\Support\Vocab;
use GhostRoot\Support\Log;

defined( 'ABSPATH' ) || exit;

final class Settings {

	private const GROUP = 'ghost_root_settings_group';

	private const SECRET_RE = '/(private[_\s-]?key|seed[_\s-]?phrase|mnemonic|signing[_\s-]?secret|wallet[_\s-]?secret|secret[_\s-]?key)/i';

	public function hooks(): void {
		add_action( 'admin_init', [ $this, 'register' ] );
	}

	public function register(): void {
		register_setting(
			self::GROUP,
			Config::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => \GhostRoot\Activator::default_settings(),
			]
		);
	}

	/**
	 * @param mixed $input Raw submitted array.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ): array {
		$input   = is_array( $input ) ? $input : [];
		$current = Config::all();
		$out     = $current;
		$rejected = [];

		// 1. Refuse anything that looks like a secret, anywhere in the payload.
		foreach ( $input as $k => $v ) {
			if (!is_scalar($v)) { unset($input[$k]); $rejected[] = 'invalid_structure'; continue; }
			$blob = is_scalar( $v ) ? (string) $v : wp_json_encode( $v );
			if ( preg_match( self::SECRET_RE, (string) $k ) || preg_match( self::SECRET_RE, (string) $blob ) ) {
				$rejected[] = sanitize_key( (string) $k );
				unset( $input[ $k ] );
				continue;
			}
			// 64-hex or 12–24 space-separated words => looks like key material.
			$trim = trim( (string) $blob );
			if ( (!in_array($k, ['source_hash','deployment_hash'], true) && preg_match( '/^[0-9a-fA-F]{64,}$/', $trim )) || preg_match( '/^(\S+\s+){11,23}\S+$/', $trim ) ) {
				$rejected[] = sanitize_key( (string) $k );
				unset( $input[ $k ] );
			}
		}

		$enum  = static fn( $val, array $allowed, string $def ) => Vocab::clamp( (string) $val, $allowed, $def );
		$b58   = static fn( $val ) => preg_match( '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/D', trim( (string) $val ) ) ? trim( (string) $val ) : '';
		$cid   = static fn( $val ) => preg_match( '#^[A-Za-z0-9._/\-]{0,120}$#', trim( (string) $val ) ) ? trim( (string) $val ) : '';

		if ( isset( $input['system_state'] ) ) {
			$out['system_state'] = $enum( $input['system_state'], Vocab::SYSTEM_STATES, 'DISCOVERY' );
		}
		if ( isset( $input['mint_state'] ) ) {
			$out['mint_state'] = $enum( $input['mint_state'], [ 'PRE_MINT', 'ALLOWLIST', 'PUBLIC', 'MINT_LIVE', 'SOLD_OUT', 'CLOSED' ], 'PRE_MINT' );
		}
		if ( isset( $input['network_status'] ) ) {
			$out['network_status'] = sanitize_text_field( $input['network_status'] );
		}
		if ( isset( $input['mint_price_sol'] ) ) {
			$out['mint_price_sol'] = round( max( 0, (float) $input['mint_price_sol'] ), 4 );
		}
		if ( isset( $input['mint_max_per_wallet'] ) ) {
			$out['mint_max_per_wallet'] = min( 100, max( 0, absint( $input['mint_max_per_wallet'] ) ) );
		}
		foreach ( [ 'collection_address', 'candy_machine_address', 'update_authority', 'treasury_address' ] as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$out[ $k ] = $b58( $input[ $k ] );
			}
		}
		foreach ( [ 'metadata_cid', 'artwork_cid' ] as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$out[ $k ] = $cid( $input[ $k ] );
			}
		}
		foreach ( [ 'discord_url', 'x_url' ] as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$out[ $k ] = esc_url_raw( trim( (string) $input[ $k ] ), [ 'https', 'http' ] );
			}
		}
		if ( isset( $input['opensea_collection_slug'] ) ) {
			// A VERIFIED OpenSea slug only. Not a secret; still strictly sanitised.
			$out['opensea_collection_slug'] = sanitize_title( (string) $input['opensea_collection_slug'] );
		}
		$out['contract_verified'] = ! empty( $input['contract_verified'] );
		foreach (['source_hash','deployment_hash'] as $key) {
			if (isset($input[$key])) { $out[$key] = preg_match('/^[0-9a-f]{64}$/iD', $input[$key]) ? strtolower($input[$key]) : ''; }
		}
		if ( isset( $input['signal_strength'] ) ) {
			$out['signal_strength'] = min( 100, max( 0, absint( $input['signal_strength'] ) ) );
		}
		if ( isset( $input['active_nodes'] ) ) {
			$out['active_nodes'] = min( 3333, max( 0, absint( $input['active_nodes'] ) ) );
		}

		if ( $rejected ) {
			add_settings_error(
				Config::OPTION,
				'gr_secret_rejected',
				'Rejected input that resembled key material or a secret: ' . esc_html( implode( ', ', array_unique( $rejected ) ) ) . '. Private keys, seed phrases and signing secrets must never be stored in WordPress.',
				'error'
			);
			Log::line( 'settings_secret_rejected', [ 'fields' => implode( ',', array_unique( $rejected ) ) ] );
		}

		Config::flush();
		return $out;
	}

	/**
	 * Render helper used by AdminMenu.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$c = Config::all();
		settings_errors( Config::OPTION );
		echo '<div class="wrap"><h1>GHOST//ROOT — System</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP );
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->select_row( 'system_state', 'System state', Vocab::SYSTEM_STATES, $c );
		$this->text_row( 'network_status', 'Network status', $c );
		$this->select_row( 'mint_state', 'Mint state', [ 'PRE_MINT', 'ALLOWLIST', 'PUBLIC', 'MINT_LIVE', 'SOLD_OUT', 'CLOSED' ], $c );
		$this->text_row( 'mint_price_sol', 'Mint price (SOL)', $c );
		$this->text_row( 'mint_max_per_wallet', 'Max per wallet', $c );
		$this->text_row( 'collection_address', 'Collection address', $c, 'base58 — leave blank until deployed' );
		$this->text_row( 'candy_machine_address', 'Candy Machine address', $c, 'base58 — leave blank until deployed' );
		$this->text_row( 'update_authority', 'Update authority', $c );
		$this->text_row( 'treasury_address', 'Treasury address', $c );
		$this->text_row( 'metadata_cid', 'Metadata CID', $c );
		$this->text_row( 'artwork_cid', 'Artwork CID', $c );
		$this->text_row( 'source_hash', 'Source SHA-256', $c );
		$this->text_row( 'deployment_hash', 'Deployment SHA-256', $c );
		$this->text_row( 'discord_url', 'Discord URL', $c );
		$this->text_row( 'x_url', 'X URL', $c );
		$this->text_row( 'opensea_collection_slug', 'OpenSea collection slug', $c, 'Only set this after `npm run opensea:verify` reports VERIFIED. Leave blank until then.' );
		$this->checkbox_row( 'contract_verified', 'Contract verified', $c );
		$this->text_row( 'signal_strength', 'Signal strength (0–100)', $c );
		$this->text_row( 'active_nodes', 'Active nodes', $c );

		echo '</tbody></table>';
		echo '<p class="description">Private keys, seed phrases, mnemonics and signing secrets are rejected on save and never stored.</p>';
		submit_button();
		echo '</form></div>';
	}

	private function name( string $k ): string {
		return Config::OPTION . '[' . $k . ']';
	}

	private function text_row( string $k, string $label, array $c, string $hint = '' ): void {
		printf(
			'<tr><th scope="row"><label for="gr_%1$s">%2$s</label></th><td><input type="text" id="gr_%1$s" name="%3$s" value="%4$s" class="regular-text" />%5$s</td></tr>',
			esc_attr( $k ),
			esc_html( $label ),
			esc_attr( $this->name( $k ) ),
			esc_attr( (string) ( $c[ $k ] ?? '' ) ),
			$hint ? '<p class="description">' . esc_html( $hint ) . '</p>' : ''
		);
	}

	private function select_row( string $k, string $label, array $options, array $c ): void {
		printf( '<tr><th scope="row"><label for="gr_%1$s">%2$s</label></th><td><select id="gr_%1$s" name="%3$s">', esc_attr( $k ), esc_html( $label ), esc_attr( $this->name( $k ) ) );
		foreach ( $options as $opt ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $opt ), selected( $c[ $k ] ?? '', $opt, false ) );
		}
		echo '</select></td></tr>';
	}

	private function checkbox_row( string $k, string $label, array $c ): void {
		printf(
			'<tr><th scope="row">%2$s</th><td><label><input type="checkbox" name="%3$s" value="1" %4$s /> %2$s</label></td></tr>',
			esc_attr( $k ),
			esc_html( $label ),
			esc_attr( $this->name( $k ) ),
			checked( ! empty( $c[ $k ] ), true, false )
		);
	}
}
