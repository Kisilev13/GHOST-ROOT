<?php
/**
 * Custom post types for the GHOST//ROOT network.
 *
 * @package GhostRoot
 */

namespace GhostRoot\PostTypes;

defined( 'ABSPATH' ) || exit;

final class PostTypes {

	public function hooks(): void {
		add_action( 'init', [ $this, 'register' ] );
	}

	public function register(): void {
		register_post_type(
			'ghost_identity',
			$this->args(
				'Identity',
				'Identities',
				[
					'rewrite'      => [ 'slug' => 'identity', 'with_front' => false ],
					'has_archive'  => 'identities',
					'menu_icon'    => 'dashicons-id-alt',
					'supports'     => [ 'title', 'editor', 'thumbnail', 'custom-fields', 'page-attributes' ],
				]
			)
		);

		register_post_type(
			'incident',
			$this->args(
				'Incident',
				'Incidents',
				[
					'rewrite'     => [ 'slug' => 'incident', 'with_front' => false ],
					'has_archive' => 'incidents',
					'menu_icon'   => 'dashicons-warning',
					'supports'    => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
				]
			)
		);

		register_post_type(
			'transmission',
			$this->args(
				'Transmission',
				'Transmissions',
				[
					'rewrite'     => [ 'slug' => 'transmission', 'with_front' => false ],
					'has_archive' => 'transmissions',
					'menu_icon'   => 'dashicons-rss',
					'supports'    => [ 'title', 'editor', 'custom-fields' ],
				]
			)
		);

		register_post_type(
			'archive_record',
			$this->args(
				'Archive Record',
				'Archive',
				[
					'rewrite'     => [ 'slug' => 'archive', 'with_front' => false ],
					'has_archive' => 'archive',
					'menu_icon'   => 'dashicons-media-text',
					'supports'    => [ 'title', 'editor', 'custom-fields' ],
				]
			)
		);

		register_post_type(
			'protocol_record',
			$this->args(
				'Protocol Record',
				'Protocol Records',
				[
					'rewrite'      => [ 'slug' => 'protocol-record', 'with_front' => false ],
					'has_archive'  => false,
					'public'       => false,
					'show_in_rest' => false,
					'show_ui'      => true,
					'menu_icon'    => 'dashicons-shield',
					'supports'     => [ 'title', 'editor', 'custom-fields' ],
				]
			)
		);
	}

	/**
	 * Shared argument builder.
	 *
	 * @param string               $singular Singular label.
	 * @param string               $plural   Plural label.
	 * @param array<string,mixed>   $overrides Overrides.
	 * @return array<string,mixed>
	 */
	private function args( string $singular, string $plural, array $overrides ): array {
		$labels = [
			'name'               => $plural,
			'singular_name'      => $singular,
			'add_new_item'       => "Add {$singular}",
			'edit_item'          => "Edit {$singular}",
			'new_item'           => "New {$singular}",
			'view_item'          => "View {$singular}",
			'search_items'       => "Search {$plural}",
			'not_found'          => 'NO SIGNAL',
			'not_found_in_trash' => 'NO SIGNAL',
			'all_items'          => $plural,
			'menu_name'          => $plural,
		];

		$defaults = [
			'labels'              => $labels,
			'public'              => true,
			'show_in_rest'        => true,
			'hierarchical'        => false,
			'exclude_from_search' => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'show_in_nav_menus'   => true,
		];

		return array_merge( $defaults, $overrides );
	}
}
