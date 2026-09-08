<?php
/**
 * Metadata importer. Consumes the NFT factory's per-token JSON (0001.json .. 3333.json)
 * and creates/updates ghost_identity records.
 *
 * SECURITY MODEL
 *  - Source directory is resolved with realpath() and must contain only *.json.
 *  - Each file path is realpath-checked to stay inside the resolved root (no traversal).
 *  - JSON is decoded defensively; structure and every trait value is validated against
 *    GhostRoot\Support\Vocab. Unknown traits are errors, not silent passes.
 *  - No file from the metadata directory is ever include()d or executed.
 *  - Fully idempotent: a second run with no --update reports every token "skipped".
 *
 * @package GhostRoot
 */

namespace GhostRoot\Importer;

use GhostRoot\Support\Vocab;
use GhostRoot\Support\Log;

defined( 'ABSPATH' ) || exit;

final class Importer {

	/** @var array{scanned:int,valid:int,created:int,updated:int,skipped:int,errors:int} */
	private array $totals = [
		'scanned' => 0,
		'valid'   => 0,
		'created' => 0,
		'updated' => 0,
		'skipped' => 0,
		'errors'  => 0,
	];

	/** @var array<int,string> */
	private array $failures = [];

	/**
	 * @param string $dir     Source directory.
	 * @param array{dry_run?:bool,limit?:int,offset?:int,update?:bool,ids?:int[]} $opts
	 * @param callable|null $emit Line emitter (e.g. WP_CLI::log).
	 * @return array{totals:array,failures:array<int,string>,status:string}
	 */
	public function run( string $dir, array $opts = [], ?callable $emit = null ): array {
		$this->totals = array_fill_keys(['scanned','valid','created','updated','skipped','errors'], 0);
		$this->failures = [];
		$emit    = $emit ?? static function ( $l ): void {};
		$dry     = ! empty( $opts['dry_run'] );
		$update  = ! empty( $opts['update'] );
		$limit   = isset( $opts['limit'] ) ? max( 0, (int) $opts['limit'] ) : 0;
		$offset  = isset( $opts['offset'] ) ? max( 0, (int) $opts['offset'] ) : 0;
		$only    = isset( $opts['ids'] ) && is_array( $opts['ids'] ) ? array_map( 'intval', $opts['ids'] ) : [];

		$root = realpath( $dir );
		if ( false === $root || ! is_dir( $root ) ) {
			return $this->fail_out( 'Source directory not found: ' . $dir );
		}

		$files = glob( $root . '/*.json' ) ?: [];
		natsort( $files );
		$files = array_values( $files );

		if ( ! $files ) {
			return $this->fail_out( 'No .json files in ' . $root );
		}

		// Deterministic slice.
		$selected = [];
		foreach ( $files as $file ) {
			$base = basename( $file, '.json' );
			if (!preg_match('/^\d{4}$/D', $base)) {
				$this->error(0, 'invalid token filename'); continue;
			}
			$id = (int) $base;
			if ( $only && ! in_array( $id, $only, true ) ) {
				continue;
			}
			if (isset($selected[$id])) { $this->error($id, 'duplicate token id'); continue; }
			$selected[ $id ] = $file;
		}
		ksort( $selected );

		if ( ! $only ) {
			$selected = array_slice( $selected, $offset, $limit > 0 ? $limit : null, true );
		}

		$emit( sprintf( 'Source: %s', $root ) );
		$emit( sprintf( 'Files selected: %d%s', count( $selected ), $dry ? ' (dry-run)' : '' ) );

		foreach ( $selected as $id => $file ) {
			$this->totals['scanned']++;
			$result = $this->process( $root, $file, $id, $dry, $update );
			$emit( sprintf( '[ghost-root] metadata_import id=%04d status=%s', $id, $result ) );
		}

		return $this->summary( $emit );
	}

	/**
	 * @return string created|updated|skipped|error|valid
	 */
	private function process( string $root, string $file, int $id, bool $dry, bool $update ): string {
		$real = realpath( $file );
		if ( false === $real || 0 !== strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
			return $this->error( $id, 'path escapes source root' );
		}
		if ( $id < 1 || $id > Vocab::SUPPLY ) {
			return $this->error( $id, 'token id out of range 1..' . Vocab::SUPPLY );
		}

		$raw = file_get_contents( $real, false, null, 0, 65537 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw || strlen( $raw ) > 64 * 1024 ) {
			return $this->error( $id, 'unreadable or oversized file' );
		}

		$data = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return $this->error( $id, 'invalid JSON: ' . json_last_error_msg() );
		}

		$parsed = $this->validate( $id, $data );
		if ( is_string( $parsed ) ) {
			return $this->error( $id, $parsed );
		}

		$this->totals['valid']++;

		// Locate existing.
		$existing = get_posts(
			[
				'post_type'      => 'ghost_identity',
				'post_status'    => 'any',
				'meta_key'       => 'ghost_id',
				'meta_value'     => $id,
				'numberposts'    => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);
		$post_id = $existing[0] ?? 0;

		if ( $post_id && ! $update ) {
			$this->totals['skipped']++;
			return 'skipped';
		}

		if ( $dry ) {
			return 'valid';
		}

		$postarr = [
			'post_type'    => 'ghost_identity',
			'post_status'  => 'publish',
			'post_title'   => $parsed['title'],
			'post_name'    => (string) $id,
			'post_content' => $parsed['description'],
		];
		if ( $post_id ) {
			$postarr['ID'] = $post_id;
			$new_id        = wp_update_post( $postarr, true );
		} else {
			$new_id = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $new_id ) ) {
			return $this->error( $id, 'db: ' . $new_id->get_error_message() );
		}

		// Re-importing traits never rewinds an existing narrative state.
		if ($post_id) { $parsed['traits']['state'] = \GhostRoot\State\StateManager::current((int)$post_id); }
		$this->write_meta( (int) $new_id, $id, $parsed );

		if ( $post_id ) {
			$this->totals['updated']++;
			return 'updated';
		}
		$this->totals['created']++;
		return 'created';
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|string  Parsed identity array, or error string.
	 */
	private function validate( int $id, array $data ): array|string {
		foreach (['name','symbol','description','image','external_url','metadata_uri'] as $key) {
			if (isset($data[$key]) && (!is_string($data[$key]) || strlen($data[$key]) > 4096)) { return 'invalid string field: ' . $key; }
		}
		$name = (string) ( $data['name'] ?? '' );
		if ( ! preg_match( '#^GHOST//(\d{1,4})$#', $name, $m ) ) {
			return 'name does not match GHOST//NNNN';
		}
		if ( (int) $m[1] !== $id ) {
			return sprintf( 'name id %d != filename id %d', (int) $m[1], $id );
		}
		if ( 'GROOT' !== strtoupper( (string) ( $data['symbol'] ?? '' ) ) ) {
			return 'symbol is not GROOT';
		}
		if ( empty( $data['attributes'] ) || ! is_array( $data['attributes'] ) ) {
			return 'missing attributes array';
		}

		$traits = [];
		$seen = [];
		foreach ( $data['attributes'] as $attr ) {
			if ( ! is_array( $attr ) || ! isset( $attr['trait_type'], $attr['value'] ) ) {
				return 'malformed attribute entry';
			}
			if (!is_string($attr['trait_type']) || !is_string($attr['value']) || strlen($attr['value']) > 120) { return 'invalid attribute type or size'; }
			if (isset($seen[$attr['trait_type']])) { return 'duplicate attribute'; }
			$seen[$attr['trait_type']] = true;
			$type = sanitize_text_field( (string) $attr['trait_type'] );
			$val  = sanitize_text_field( (string) $attr['value'] );

			if ( 'Fingerprint' === $type ) {
				if ( ! preg_match( '/^[0-9A-Fa-f]{4,32}$/', $val ) ) {
					return 'fingerprint not hex';
				}
				$traits['fingerprint'] = strtoupper( $val );
				continue;
			}
			// Numeric / informational traits carried by the factory output.
			if ( 'Breach Count' === $type ) {
				$traits['breach_count'] = max( 0, min( 9999, (int) $attr['value'] ) );
				continue;
			}
			if ( 'Visual Variant' === $type ) {
				$traits['visual_variant'] = substr( $val, 0, 40 );
				continue;
			}
			if ( ! Vocab::is_valid_trait( $type, $val ) ) {
				return sprintf( 'unknown trait %s="%s"', $type, $val );
			}
			$traits[ $this->meta_key_for( $type ) ] = strtoupper( $val );
		}

		// Fingerprint is carried under ghost_root in the factory output, not in attributes.
		// Strip a leading 0x/0X so the stored value is the bare hex (templates re-add "0x").
		if ( empty( $traits['fingerprint'] ) && isset( $data['ghost_root']['fingerprint'] ) ) {
			$raw = preg_replace( '/^0x/i', '', trim( (string) $data['ghost_root']['fingerprint'] ) );
			$fp  = strtoupper( preg_replace( '/[^0-9A-Fa-f]/', '', (string) $raw ) );
			if ( preg_match( '/^[0-9A-F]{4,32}$/', $fp ) ) {
				$traits['fingerprint'] = $fp;
			}
		}

		// Required traits.
		foreach ( ['entity','face','eyes','mask','implant','corruption','access','background','state','rarity_band','archetype','fingerprint'] as $req ) {
			if ( empty( $traits[ $req ] ) ) {
				return "missing required trait: {$req}";
			}
		}

		if ($id > 3 && ($traits['rarity_band'] ?? '') === 'GENESIS') { return 'Genesis reserved for IDs 0001-0003'; }
		// Genesis invariant.
		if ( $id <= 3 ) {
			$traits['access']      = 'ROOT';
			$traits['rarity_band'] = 'GENESIS';
		}

		// Derived signal condition if not supplied.
		if ( empty( $traits['signal'] ) ) {
			$traits['signal'] = $this->derive_signal( $traits['corruption'] ?? '' );
		}
		$traits['recovery_node'] = 'NODE-' . str_pad( (string) ( ( $id % 48 ) + 1 ), 2, '0', STR_PAD_LEFT );
		$traits['last_signal']   = gmdate( 'Y-m-d\TH:i:s\Z', 1735689600 + ( $id * 733 ) );

		$image = (string) ( $data['image'] ?? '' );
		$ext = (string) ($data['metadata_uri'] ?? '');
		foreach (['image','external_url','metadata_uri'] as $key) {
			if (!empty($data[$key]) && !$this->safe_uri($data[$key])) { return 'invalid URI: ' . $key; }
		}

		return [
			'title'       => 'GHOST//' . str_pad( (string) $id, 4, '0', STR_PAD_LEFT ),
			'description' => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
			'traits'      => $traits,
			'image_uri'   => $this->safe_uri( $image ),
			'metadata_uri' => $this->safe_uri( $ext ),
		];
	}

	private function write_meta( int $post_id, int $ghost_id, array $parsed ): void {
		update_post_meta( $post_id, 'ghost_id', $ghost_id );
		foreach ( $parsed['traits'] as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		if ( $parsed['image_uri'] ) {
			update_post_meta( $post_id, 'image_uri', $parsed['image_uri'] );
		}
		if ( $parsed['metadata_uri'] ) {
			update_post_meta( $post_id, 'metadata_uri', $parsed['metadata_uri'] );
		}

		// Taxonomy mirrors for fast filtering.
		wp_set_object_terms( $post_id, $parsed['traits']['access'], 'ghost_access', false );
		wp_set_object_terms( $post_id, $parsed['traits']['state'], 'ghost_state', false );
		wp_set_object_terms( $post_id, $parsed['traits']['entity'], 'ghost_entity', false );

		// Seed state history if empty.
		if ( ! get_post_meta( $post_id, '_ghost_state_history', true ) ) {
			update_post_meta(
				$post_id,
				'_ghost_state_history',
				[
					[
						'from'       => '',
						'to'         => $parsed['traits']['state'],
						'timestamp'  => gmdate( 'c' ),
						'trigger'    => 'RECOVERY',
						'reference'  => 'metadata-import',
						'actor_type' => 'SYSTEM',
					],
				]
			);
		}
	}

	private function meta_key_for( string $trait_type ): string {
		$map = [
			'Entity'      => 'entity',
			'Face'        => 'face',
			'Eyes'        => 'eyes',
			'Mask'        => 'mask',
			'Implant'     => 'implant',
			'Corruption'  => 'corruption',
			'Access'      => 'access',
			'Background'  => 'background',
			'State'       => 'state',
			'Rarity Band' => 'rarity_band',
			'Archetype'   => 'archetype',
			'Signal'      => 'signal',
		];
		return $map[ $trait_type ] ?? sanitize_key( $trait_type );
	}

	private function derive_signal( string $corruption ): string {
		return match ( $corruption ) {
			'MEMORY BLEED', 'SIGNAL LOSS'      => 'NULL',
			'GLITCH', 'FRAGMENTATION', 'PACKET GHOSTING' => 'CORRUPTED',
			'BURN'                              => 'DEGRADED',
			'NONE'                              => 'STABLE',
			default                             => 'DEGRADED',
		};
	}

	private function safe_uri( string $uri ): string {
		$uri = trim( $uri );
		if ( '' === $uri ) {
			return '';
		}
		if ( preg_match( '#^(ipfs|ar)://[A-Za-z0-9._/\-]+$#', $uri ) ) {
			return $uri;
		}
		return esc_url_raw( $uri, [ 'http', 'https' ] );
	}

	private function error( int $id, string $why ): string {
		$this->totals['errors']++;
		$this->failures[ $id ] = $why;
		Log::line( 'metadata_import_error', [ 'id' => $id, 'why' => $why ] );
		return 'error';
	}

	private function fail_out( string $msg ): array {
		return [
			'totals'   => $this->totals,
			'failures' => [ 0 => $msg ],
			'status'   => 'FAIL',
		];
	}

	private function summary( callable $emit ): array {
		$t      = $this->totals;
		$status = 0 === $t['errors'] ? 'PASS' : 'FAIL';
		$emit( '' );
		$emit( sprintf( 'Files scanned:   %8d', $t['scanned'] ) );
		$emit( sprintf( 'Valid:           %8d', $t['valid'] ) );
		$emit( sprintf( 'Created:         %8d', $t['created'] ) );
		$emit( sprintf( 'Updated:         %8d', $t['updated'] ) );
		$emit( sprintf( 'Skipped:         %8d', $t['skipped'] ) );
		$emit( sprintf( 'Errors:          %8d', $t['errors'] ) );
		$emit( '' );
		foreach ( $this->failures as $id => $why ) {
			$emit( sprintf( '  ! %04d  %s', $id, $why ) );
		}
		$emit( 'STATUS: ' . $status );

		return [
			'totals'   => $t,
			'failures' => $this->failures,
			'status'   => $status,
		];
	}
}
