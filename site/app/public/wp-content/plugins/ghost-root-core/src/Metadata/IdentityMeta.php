<?php
/**
 * Registered post meta for ghost_identity (+ genesis extension fields) and incident.
 * Every field is typed, sanitized, and REST-visible only where safe.
 *
 * @package GhostRoot
 */

namespace GhostRoot\Metadata;

use GhostRoot\Support\Vocab;

defined( 'ABSPATH' ) || exit;

final class IdentityMeta {

	/** Display string fields, clamped to a vocabulary where one exists. */
	private const STRING_FIELDS = [
		'entity'       => Vocab::ENTITY,
		'face'         => null,
		'eyes'         => null,
		'mask'         => null,
		'implant'      => null,
		'corruption'   => null,
		'access'       => Vocab::ACCESS,
		'background'   => null,
		'signal'       => null,
		'state'        => Vocab::GHOST_STATES,
		'rarity_band'  => null,
		'archetype'    => null,
		'recovery_node' => null,
		'last_signal'  => null,
		'fingerprint'  => null,
	];

	/** Genesis-only narrative fields (rich text allowed). */
	private const GENESIS_FIELDS = [
		'codename', 'origin', 'known_events', 'unknowns', 'visual_signature', 'quote', 'future_role',
	];

	public function hooks(): void {
		add_action( 'init', [ $this, 'register' ] );
	}

	public function register(): void {
		// Numeric identity id.
		register_post_meta(
			'ghost_identity',
			'ghost_id',
			[
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => [ $this, 'can_edit' ],
			]
		);

		foreach ( self::STRING_FIELDS as $key => $allowed ) {
			register_post_meta(
				'ghost_identity',
				$key,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => $key !== 'state',
					'sanitize_callback' => $this->string_sanitizer( $key, $allowed ),
					'auth_callback'     => [ $this, 'can_edit' ],
				]
			);
		}

		// URLs — validated, not REST-writable by non-editors.
		foreach ( [ 'image_uri', 'metadata_uri' ] as $key ) {
			register_post_meta(
				'ghost_identity',
				$key,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => [ $this, 'sanitize_uri' ],
					'auth_callback'     => [ $this, 'can_edit' ],
				]
			);
		}

		// On-chain token address — base58 charset only, may be empty.
		register_post_meta(
			'ghost_identity',
			'token_address',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ $this, 'sanitize_base58' ],
				'auth_callback'     => [ $this, 'can_edit' ],
			]
		);

		// State history — internal only, never REST-exposed or user-writable.
		register_post_meta(
			'ghost_identity',
			'_ghost_state_history',
			[
				'type'          => 'array',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => '__return_false',
			]
		);

		foreach ( self::GENESIS_FIELDS as $key ) {
			register_post_meta(
				'ghost_identity',
				$key,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => 'wp_kses_post',
					'auth_callback'     => [ $this, 'can_edit' ],
				]
			);
		}

		// Incident fields.
		$incident_strings = [ 'incident_id', 'incident_date', 'status', 'affected_nodes', 'transmission_id' ];
		foreach ( $incident_strings as $key ) {
			register_post_meta(
				'incident',
				$key,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => [ $this, 'can_edit' ],
				]
			);
		}
		foreach ( [ 'evidence', 'summary' ] as $key ) {
			register_post_meta(
				'incident',
				$key,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'wp_kses_post',
					'auth_callback'     => [ $this, 'can_edit' ],
				]
			);
		}

		// Archive record fields.
		foreach ( [ 'record_node', 'record_access', 'record_status', 'record_date' ] as $key ) {
			register_post_meta(
				'archive_record',
				$key,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => [ $this, 'can_edit' ],
				]
			);
		}
	}

	/**
	 * @param string        $key     Meta key.
	 * @param string[]|null  $allowed Optional allowlist.
	 */
	private function string_sanitizer( string $key, ?array $allowed ): callable {
		return static function ( $value ) use ( $key, $allowed ) {
			$value = sanitize_text_field( (string) $value );
			if ( 'fingerprint' === $key ) {
				$hex = strtoupper( preg_replace( '/[^0-9a-fA-F]/', '', $value ) );
				return substr( $hex, 0, 32 );
			}
			if ( is_array( $allowed ) ) {
				return Vocab::clamp( $value, $allowed, $value === '' ? '' : ( $allowed[0] ?? '' ) );
			}
			return substr( $value, 0, 120 );
		};
	}

	public function sanitize_uri( $value ): string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		// Allow ipfs:// and ar:// in addition to http(s).
		if ( preg_match( '#^(ipfs|ar)://[A-Za-z0-9._/\-]+$#', $value ) ) {
			return $value;
		}
		return esc_url_raw( $value, [ 'http', 'https' ] );
	}

	public function sanitize_base58( $value ): string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		return preg_match( '/^[1-9A-HJ-NP-Za-km-z]{32,64}$/', $value ) ? $value : '';
	}

	/**
	 * Meta auth callback — only users who can edit this post may write it.
	 */
	public function can_edit( $allowed, $meta_key, $post_id ): bool {
		if (in_array($meta_key, ['ghost_id','state'], true)) { return false; }
		if (in_array($meta_key, ['access','rarity_band'], true) && (int)get_post_meta($post_id, 'ghost_id', true) <= 3) { return false; }
		return current_user_can( 'edit_post', $post_id );
	}
}
