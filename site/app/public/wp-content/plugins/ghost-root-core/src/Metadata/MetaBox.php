<?php
/**
 * Admin meta boxes for identity (+ genesis) and incident editing.
 * All writes are nonce-protected and capability-checked.
 *
 * @package GhostRoot
 */

namespace GhostRoot\Metadata;

use GhostRoot\Support\Vocab;
use GhostRoot\State\StateManager;

defined( 'ABSPATH' ) || exit;

final class MetaBox {

	private const IDENTITY_FIELDS = [
		'ghost_id'      => 'Ghost ID',
		'entity'        => 'Entity',
		'face'          => 'Face',
		'eyes'          => 'Eyes',
		'mask'          => 'Mask',
		'implant'       => 'Implant',
		'corruption'    => 'Corruption',
		'access'        => 'Access',
		'background'    => 'Background',
		'signal'        => 'Signal',
		'state'         => 'State',
		'rarity_band'   => 'Rarity band',
		'archetype'     => 'Archetype',
		'fingerprint'   => 'Fingerprint',
		'recovery_node' => 'Recovery node',
		'last_signal'   => 'Last signal',
		'image_uri'     => 'Image URI',
		'metadata_uri'  => 'Metadata URI',
		'token_address' => 'Token address',
	];

	private const GENESIS_FIELDS = [
		'codename'         => 'Codename',
		'origin'           => 'Origin',
		'known_events'     => 'Known events',
		'unknowns'         => 'Unknowns',
		'visual_signature' => 'Visual signature',
		'quote'            => 'Quote',
		'future_role'      => 'Future role',
	];

	private const INCIDENT_FIELDS = [
		'incident_id'     => 'Incident ID',
		'incident_date'   => 'Date',
		'severity'        => 'Severity',
		'status'          => 'Status',
		'affected_nodes'  => 'Affected nodes',
		'transmission_id' => 'Transmission ID',
		'summary'         => 'Summary',
		'evidence'        => 'Evidence',
	];

	private const ARCHIVE_FIELDS = [
		'record_node'   => 'Node',
		'record_access' => 'Access',
		'record_status' => 'Status',
		'record_date'   => 'Date',
	];

	public function hooks(): void {
		add_action( 'add_meta_boxes', [ $this, 'add' ] );
		add_action( 'save_post', [ $this, 'save' ], 10, 2 );
	}

	public function add(): void {
		add_meta_box( 'gr_identity', 'GHOST//ROOT — Identity', [ $this, 'render_identity' ], 'ghost_identity', 'normal', 'high' );
		add_meta_box( 'gr_incident', 'GHOST//ROOT — Incident', [ $this, 'render_incident' ], 'incident', 'normal', 'high' );
		add_meta_box( 'gr_archive', 'GHOST//ROOT — Record', [ $this, 'render_archive' ], 'archive_record', 'side', 'default' );
	}

	public function render_identity( \WP_Post $post ): void {
		wp_nonce_field( 'gr_save_meta', 'gr_meta_nonce' );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( self::IDENTITY_FIELDS as $key => $label ) {
			$this->field_row( $post->ID, $key, $label );
		}
		echo '</tbody></table>';

		echo '<h4>Genesis fields (IDs 0001–0003 only)</h4>';
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( self::GENESIS_FIELDS as $key => $label ) {
			$this->field_row( $post->ID, $key, $label, true );
		}
		echo '</tbody></table>';

		$history = StateManager::history( $post->ID );
		if ( $history ) {
			echo '<h4>State history</h4><ol style="font-family:monospace;font-size:12px">';
			foreach ( $history as $h ) {
				printf(
					'<li>%s &rarr; %s &nbsp; %s &nbsp; trigger=%s actor=%s</li>',
					esc_html( $h['from'] ?: '—' ),
					esc_html( $h['to'] ),
					esc_html( $h['timestamp'] ),
					esc_html( $h['trigger'] ),
					esc_html( $h['actor_type'] )
				);
			}
			echo '</ol>';
		}
	}

	public function render_incident( \WP_Post $post ): void {
		wp_nonce_field( 'gr_save_meta', 'gr_meta_nonce' );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( self::INCIDENT_FIELDS as $key => $label ) {
			$this->field_row( $post->ID, $key, $label );
		}
		echo '</tbody></table>';
	}

	public function render_archive( \WP_Post $post ): void {
		wp_nonce_field( 'gr_save_meta', 'gr_meta_nonce' );
		foreach ( self::ARCHIVE_FIELDS as $key => $label ) {
			printf(
				'<p><label><strong>%s</strong><br><input type="text" name="gr_meta[%s]" value="%s" class="widefat"></label></p>',
				esc_html( $label ),
				esc_attr( $key ),
				esc_attr( (string) get_post_meta( $post->ID, $key, true ) )
			);
		}
	}

	private function field_row( int $post_id, string $key, string $label, bool $textarea = false ): void {
		$value   = (string) get_post_meta( $post_id, $key, true );
		$enum    = $this->enum_for( $key );
		echo '<tr><th scope="row"><label for="gr_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( $enum ) {
			echo '<select id="gr_' . esc_attr( $key ) . '" name="gr_meta[' . esc_attr( $key ) . ']">';
			echo '<option value="">—</option>';
			foreach ( $enum as $opt ) {
				printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $opt ), selected( $value, $opt, false ) );
			}
			echo '</select>';
		} elseif ( $textarea ) {
			printf( '<textarea id="gr_%1$s" name="gr_meta[%1$s]" rows="2" class="large-text">%2$s</textarea>', esc_attr( $key ), esc_textarea( $value ) );
		} else {
			printf( '<input type="text" id="gr_%1$s" name="gr_meta[%1$s]" value="%2$s" class="regular-text">', esc_attr( $key ), esc_attr( $value ) );
		}
		echo '</td></tr>';
	}

	/**
	 * @return string[]|null
	 */
	private function enum_for( string $key ): ?array {
		return match ( $key ) {
			'entity'      => Vocab::ENTITY,
			'access'      => Vocab::ACCESS,
			'state'       => Vocab::GHOST_STATES,
			'face'        => Vocab::TRAITS['Face'],
			'eyes'        => Vocab::TRAITS['Eyes'],
			'mask'        => Vocab::TRAITS['Mask'],
			'implant'     => Vocab::TRAITS['Implant'],
			'corruption'  => Vocab::TRAITS['Corruption'],
			'background'  => Vocab::TRAITS['Background'],
			'signal'      => Vocab::SIGNAL,
			'rarity_band' => array_keys( Vocab::RARITY_BANDS ),
			'archetype'   => Vocab::TRAITS['Archetype'],
			'severity'    => [ 'LOW', 'MODERATE', 'HIGH', 'CRITICAL', 'ROOT' ],
			default       => null,
		};
	}

	/**
	 * @param int      $post_id Post being saved.
	 * @param \WP_Post $post    Post object.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! in_array( $post->post_type, [ 'ghost_identity', 'incident', 'archive_record' ], true ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['gr_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gr_meta_nonce'] ) ), 'gr_save_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = isset( $_POST['gr_meta'] ) && is_array( $_POST['gr_meta'] ) ? wp_unslash( $_POST['gr_meta'] ) : [];

		$allowed_fields = match ($post->post_type) {
			'ghost_identity' => array_merge(self::IDENTITY_FIELDS, self::GENESIS_FIELDS),
			'incident' => self::INCIDENT_FIELDS,
			default => self::ARCHIVE_FIELDS,
		};
		foreach ( $raw as $key => $value ) {
			$key = sanitize_key( $key );
			if (!isset($allowed_fields[$key]) || !is_scalar($value)) { continue; }
			if ($post->post_type === 'ghost_identity') {
				if ($key === 'ghost_id') { continue; } // Stable importer-owned ID.
				$is_genesis = (int)get_post_meta($post_id, 'ghost_id', true) <= 3;
				if ($is_genesis && in_array($key, ['access', 'rarity_band'], true)) { continue; }
				if ($key === 'state') {
					if (current_user_can('manage_options') && $value !== StateManager::current($post_id)) {
						StateManager::transition($post_id, (string)$value, ['trigger'=>'ADMIN_EDIT','actor'=>'ADMIN','reference'=>'meta-box']);
					}
					continue;
				}
			}
			// register_post_meta sanitize_callbacks run inside update_post_meta.
			if ( in_array( $key, array_keys( self::GENESIS_FIELDS ), true ) || in_array( $key, [ 'summary', 'evidence' ], true ) ) {
				update_post_meta( $post_id, $key, wp_kses_post( (string) $value ) );
			} else {
				update_post_meta( $post_id, $key, sanitize_text_field( (string) $value ) );
			}
		}

		// Keep taxonomy mirrors in sync for identities.
		if ( 'ghost_identity' === $post->post_type ) {
			foreach ( [ 'access' => 'ghost_access', 'state' => 'ghost_state', 'entity' => 'ghost_entity' ] as $meta => $tax ) {
				$v = (string) get_post_meta( $post_id, $meta, true );
				if ( $v ) {
					wp_set_object_terms( $post_id, $v, $tax, false );
				}
			}
		}
	}
}
