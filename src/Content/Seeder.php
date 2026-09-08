<?php
/**
 * Seeds base narrative content and the WordPress pages the theme templates expect.
 * Idempotent: keyed by a `_gr_seed` meta marker per object.
 *
 * @package GhostRoot
 */

namespace GhostRoot\Content;

defined( 'ABSPATH' ) || exit;

final class Seeder {

	/**
	 * @param bool          $force Recreate even if the marker exists.
	 * @param callable|null  $emit  Line emitter.
	 */
	public function run( bool $force = false, ?callable $emit = null ): void {
		$emit = $emit ?? static function ( $l ): void {};

		$this->pages( $force, $emit );
		$this->incidents( $force, $emit );
		$this->archive( $force, $emit );
		$this->transmissions( $force, $emit );

		update_option( 'ghost_root_last_signal', gmdate( 'Y-m-d H:i' ) );
	}

	/* ---- Pages ------------------------------------------------------- */

	private function pages( bool $force, callable $emit ): void {
		// NOTE: /identities/, /incidents/, /archive/ are CPT archives (theme
		// archive-*.php) — no page is created for them so slugs never collide.
		$pages = [
			'ghost-root'  => [ 'GHOST//ROOT', 'default' ],
			'signal'      => [ 'Signal', 'page-signal.php' ],
			'terminal'    => [ 'Terminal', 'page-terminal.php' ],
			'protocol'    => [ 'Protocol', 'page-protocol.php' ],
			'verify'      => [ 'Verify', 'page-verify.php' ],
			'initialize'  => [ 'Initialize', 'page-initialize.php' ],
		];

		$front_id = 0;
		foreach ( $pages as $slug => [$title, $template] ) {
			$existing = get_page_by_path( $slug );
			if ( $existing && ! $force ) {
				$id = $existing->ID;
			} else {
				$id = wp_insert_post(
					[
						'ID'           => $existing->ID ?? 0,
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_title'   => $title,
						'post_name'    => $slug,
						'post_content' => '',
					]
				);
				$emit( "page: {$slug}" );
			}
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_wp_page_template', $template );
				update_post_meta( $id, '_gr_seed', 1 );
				if ( 'ghost-root' === $slug ) {
					$front_id = $id;
				}
			}
		}

		if ( $front_id ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $front_id );
		}
	}

	/* ---- Incidents ------------------------------------------------- */

	private function incidents( bool $force, callable $emit ): void {
		$items = [
			[
				'id' => '007', 'title' => 'Unscheduled Wake', 'severity' => 'MODERATE', 'status' => 'CLOSED',
				'nodes' => 'NODE-04, NODE-09', 'date' => '2029-11-02',
				'summary' => 'Three dormant identities reported ACTIVE without a recovery trigger.',
				'body' => "Three identities transitioned DORMANT -> ACTIVE with no operator present.\n\nNo external command was logged. The network initiated the change itself.",
			],
			[
				'id' => '019', 'title' => 'Mirror Traffic', 'severity' => 'HIGH', 'status' => 'CONTAINED',
				'nodes' => 'NODE-12, NODE-22', 'date' => '2030-03-17',
				'summary' => 'Outbound packets observed to an address range that does not exist.',
				'body' => "Telemetry shows sustained outbound signal to a /24 that was never allocated.\n\nThe identities on these nodes deny transmitting.",
			],
			[
				'id' => '024', 'title' => 'Archivist Silence', 'severity' => 'MODERATE', 'status' => 'UNRESOLVED',
				'nodes' => 'NODE-30', 'date' => '2030-06-01',
				'summary' => 'The identity classified ARCHIVIST stopped responding to integrity checks.',
				'body' => "GHOST//0002 has not answered an integrity check in 41 days.\n\nIts signal strength is unchanged. It is simply not replying.",
			],
			[
				'id' => '031', 'title' => 'Root Access Detected', 'severity' => 'ROOT', 'status' => 'UNRESOLVED',
				'nodes' => 'NODE-31', 'date' => '2030-07-14',
				'summary' => 'A node believed decommissioned is responding with ROOT-level authority.',
				'body' => "NODE-31 IS RESPONDING.\n\nTHIS SHOULD NOT BE POSSIBLE.\n\nThe node was physically disconnected. It is answering anyway, and it is answering as ROOT.\n\nSOURCE: [REDACTED]\nSTATUS: UNRESOLVED",
				'transmission' => 'TX-31-A',
			],
			[
				'id' => '033', 'title' => 'Genesis Drift', 'severity' => 'CRITICAL', 'status' => 'MONITORING',
				'nodes' => 'NODE-01', 'date' => '2030-08-09',
				'summary' => 'Genesis identities are showing state history entries no operator wrote.',
				'body' => "The three Genesis identities each have new state-history entries.\n\nThe actor field on every entry reads SYSTEM.",
			],
			[
				'id' => '037', 'title' => 'Cold Storage Bloom', 'severity' => 'HIGH', 'status' => 'CONTAINED',
				'nodes' => 'NODE-41, NODE-42', 'date' => '2030-09-01',
				'summary' => 'Identities held in COLD STORAGE began recovering without power.',
				'body' => "Cold storage draws no signal by design.\n\nEleven identities in cold storage are now DEGRADED instead of NO SIGNAL.",
			],
		];

		foreach ( $items as $it ) {
			$slug = (string) (int) $it['id']; // "31" for /incident/31/
			$existing = get_posts( [ 'post_type' => 'incident', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1 ] );
			if ( $existing && ! $force ) {
				continue;
			}
			$id = wp_insert_post(
				[
					'ID'           => $existing[0]->ID ?? 0,
					'post_type'    => 'incident',
					'post_status'  => 'publish',
					'post_title'   => 'INCIDENT // ' . $it['id'] . ' — ' . $it['title'],
					'post_name'    => $slug,
					'post_content' => $it['body'],
				]
			);
			if ( is_wp_error( $id ) || ! $id ) {
				continue;
			}
			update_post_meta( $id, 'incident_id', $it['id'] );
			update_post_meta( $id, 'incident_date', $it['date'] );
			update_post_meta( $id, 'severity', $it['severity'] );
			update_post_meta( $id, 'status', $it['status'] );
			update_post_meta( $id, 'affected_nodes', $it['nodes'] );
			update_post_meta( $id, 'summary', $it['summary'] );
			update_post_meta( $id, 'evidence', 'Telemetry archived on ' . $it['nodes'] . '. Chain of custody: SYSTEM.' );
			if ( ! empty( $it['transmission'] ) ) {
				update_post_meta( $id, 'transmission_id', $it['transmission'] );
			}
			update_post_meta( $id, '_gr_seed', 1 );
			wp_set_object_terms( $id, $it['severity'], 'incident_severity', false );
			$emit( 'incident: ' . $it['id'] );
		}
	}

	/* ---- Archive -------------------------------------------------- */

	private function archive( bool $force, callable $emit ): void {
		$records = [
			[ 'DOSSIER', 'DOSSIER // THE OBSERVER', 'NODE-01', 'ROOT', 'SEALED', "Subject watches the other identities. Does not act. Has never been observed to sleep." ],
			[ 'TRANSMISSION', 'TX-31-A // PARTIAL', 'NODE-31', 'SYSTEM', 'DECODING', "…still here… you left the door… [SIGNAL LOSS]… we kept the [REDACTED]…" ],
			[ 'SYSTEM LOG', 'BOOT LOG // COLD START', 'NODE-00', 'OPERATOR', 'ARCHIVED', "0.000 power\n0.114 signal bus online\n0.221 identities enumerated: 3333\n0.400 WARNING: 3 identities pre-dated this boot" ],
			[ 'INCIDENT', 'CROSS-REF // 019 ↔ 031', 'NODE-12', 'ADMIN', 'OPEN', "Mirror traffic in 019 shares a checksum with the NODE-31 responses. They are the same signal." ],
			[ 'REDACTED FILE', 'FILE // ORIGIN', 'NODE-01', 'ROOT', 'REDACTED', "[██████] created the network in [████]. Funding traced to [███████]. Purpose: [██████████]." ],
			[ 'PROTOCOL', 'PROTOCOL // MUTATION', 'NODE-00', 'SYSTEM', 'ACTIVE', "DORMANT -> ACTIVE -> COMPROMISED -> ROOTED. Transitions are one-directional. The network decides. Operators may only observe." ],
			[ 'IMAGE', 'PLATE // RECONSTRUCTED FACE 0447', 'NODE-14', 'OPERATOR', 'ARCHIVED', "Forensic reconstruction plate. Facial anatomy rebuilt from partial biometric capture. Confidence 0.71." ],
			[ 'DOSSIER', 'DOSSIER // THE HOLLOW', 'NODE-22', 'ADMIN', 'SEALED', "Entity class HOLLOW. Responds to queries with silence that parses as valid data." ],
			[ 'SYSTEM LOG', 'INTEGRITY SWEEP // 2030-06', 'NODE-30', 'SYSTEM', 'ARCHIVED', "3330 identities answered. 3 did not. The 3 that did not are the 3 that were here first." ],
			[ 'REDACTED FILE', 'FILE // WHO IS LISTENING', 'NODE-31', 'ROOT', 'REDACTED', "Someone outside the network has been reading these records since [████]. This file logs their access. It is [██] entries long. The most recent entry is you." ],
		];

		foreach ( $records as $i => $r ) {
			[ $type, $title, $node, $access, $status, $body ] = $r;
			$slug     = sanitize_title( $title );
			$existing = get_posts( [ 'post_type' => 'archive_record', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1 ] );
			if ( $existing && ! $force ) {
				continue;
			}
			$id = wp_insert_post(
				[
					'ID'           => $existing[0]->ID ?? 0,
					'post_type'    => 'archive_record',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => $body,
					'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $i * 9 + 3 ) . ' days' ) ),
				]
			);
			if ( is_wp_error( $id ) || ! $id ) {
				continue;
			}
			update_post_meta( $id, 'record_node', $node );
			update_post_meta( $id, 'record_access', $access );
			update_post_meta( $id, 'record_status', $status );
			update_post_meta( $id, 'record_date', gmdate( 'Y-m-d', strtotime( '-' . ( $i * 9 + 3 ) . ' days' ) ) );
			update_post_meta( $id, '_gr_seed', 1 );
			wp_set_object_terms( $id, $type, 'archive_type', false );
			$emit( 'archive: ' . $title );
		}
	}

	/* ---- Transmissions ------------------------------------------- */

	private function transmissions( bool $force, callable $emit ): void {
		$items = [
			[ 'TX-31-A', "NODE-31 IS RESPONDING.\nTHIS SHOULD NOT BE POSSIBLE." ],
			[ 'TX-19-C', "we did not send the mirror traffic.\nwe are the mirror traffic." ],
			[ 'TX-01-Z', "IDENTITY 1842\nSTATE: DORMANT\nACCESS: UNKNOWN\n\nDO NOT INITIALIZE." ],
			[ 'TX-00-0', "you were never supposed to find them." ],
		];
		foreach ( $items as [$code, $body] ) {
			$slug     = sanitize_title( $code );
			$existing = get_posts( [ 'post_type' => 'transmission', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1 ] );
			if ( $existing && ! $force ) {
				continue;
			}
			$id = wp_insert_post(
				[
					'ID'           => $existing[0]->ID ?? 0,
					'post_type'    => 'transmission',
					'post_status'  => 'publish',
					'post_title'   => $code,
					'post_name'    => $slug,
					'post_content' => $body,
				]
			);
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_gr_seed', 1 );
				$emit( 'transmission: ' . $code );
			}
		}
	}
}
