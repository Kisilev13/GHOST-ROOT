<?php
/**
 * Taxonomies + seeded terms.
 *
 * @package GhostRoot
 */

namespace GhostRoot\PostTypes;

use GhostRoot\Support\Vocab;

defined( 'ABSPATH' ) || exit;

final class Taxonomies {

	/** @var array<string,array{label:string,post_types:string[],slug:string}> */
	private const MAP = [
		'ghost_access'      => [ 'label' => 'Access',    'post_types' => [ 'ghost_identity' ],           'slug' => 'access' ],
		'ghost_state'       => [ 'label' => 'State',     'post_types' => [ 'ghost_identity' ],           'slug' => 'state' ],
		'ghost_entity'      => [ 'label' => 'Entity',    'post_types' => [ 'ghost_identity' ],           'slug' => 'entity' ],
		'incident_severity' => [ 'label' => 'Severity',  'post_types' => [ 'incident' ],                 'slug' => 'severity' ],
		'archive_type'      => [ 'label' => 'Archive Type', 'post_types' => [ 'archive_record' ],        'slug' => 'archive-type' ],
	];

	public function hooks(): void {
		add_action( 'init', [ $this, 'register' ] );
	}

	public function register(): void {
		foreach ( self::MAP as $tax => $cfg ) {
			register_taxonomy(
				$tax,
				$cfg['post_types'],
				[
					'labels'            => [
						'name'          => $cfg['label'],
						'singular_name' => $cfg['label'],
						'menu_name'     => $cfg['label'],
					],
					'public'            => true,
					'hierarchical'      => false,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'rewrite'           => [ 'slug' => $cfg['slug'], 'with_front' => false ],
				]
			);
		}
	}

	/**
	 * Idempotently create the controlled-vocabulary terms.
	 */
	public static function seed_terms(): void {
		$sets = [
			'ghost_access'      => Vocab::ACCESS,
			'ghost_state'       => Vocab::GHOST_STATES,
			'ghost_entity'      => Vocab::ENTITY_TAXONOMY,
			'incident_severity' => [ 'LOW', 'MODERATE', 'HIGH', 'CRITICAL', 'ROOT' ],
			'archive_type'      => [ 'TRANSMISSION', 'DOSSIER', 'INCIDENT', 'SYSTEM LOG', 'IMAGE', 'PROTOCOL', 'REDACTED FILE' ],
		];

		foreach ( $sets as $tax => $terms ) {
			foreach ( $terms as $term ) {
				if ( ! term_exists( $term, $tax ) ) {
					wp_insert_term( $term, $tax );
				}
			}
		}
	}
}
