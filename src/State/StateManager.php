<?php
/**
 * Ghost mutation engine. Documented transitions only:
 *   DORMANT -> ACTIVE -> COMPROMISED -> ROOTED
 *
 * Transitions are never reachable from a public REST request. Every transition is
 * appended to per-post history meta and to the ghost_root_events audit table.
 *
 * @package GhostRoot
 */

namespace GhostRoot\State;

use GhostRoot\Support\Vocab;
use GhostRoot\Support\Log;

defined( 'ABSPATH' ) || exit;

final class StateManager {

	/** Allowed directed edges (forward one step). */
	private const EDGES = [
		'DORMANT'     => 'ACTIVE',
		'ACTIVE'      => 'COMPROMISED',
		'COMPROMISED' => 'ROOTED',
	];

	/**
	 * Transition a ghost identity to a new state.
	 *
	 * @param int                  $post_id  ghost_identity post ID.
	 * @param string               $to       Target state.
	 * @param array<string,string>  $context  trigger, actor (actor_type), reference.
	 * @param bool                 $force    Allow non-adjacent transitions (admin/CLI only).
	 * @return true|\WP_Error
	 */
	public static function transition( int $post_id, string $to, array $context = [], bool $force = false ) {
		if (!(defined('WP_CLI') && WP_CLI) && !current_user_can('manage_options')) {
			return new \WP_Error('gr_forbidden', 'State transitions require an administrator.', ['status'=>403]);
		}
		if ($force) { return new \WP_Error('gr_force_disabled', 'State skips and reversals are not permitted.', ['status'=>400]); }
		$post = get_post( $post_id );
		if ( ! $post || 'ghost_identity' !== $post->post_type ) {
			return new \WP_Error( 'gr_not_identity', 'Not a ghost_identity post.' );
		}

		$to   = Vocab::clamp( $to, Vocab::GHOST_STATES );
		if ( '' === $to ) {
			return new \WP_Error( 'gr_bad_state', 'Unknown target state.' );
		}

		$from = self::current( $post_id );

		if ( $from === $to ) {
			return new \WP_Error( 'gr_noop', 'Already in that state.' );
		}
		if ( ! $force && ( self::EDGES[ $from ] ?? null ) !== $to ) {
			return new \WP_Error( 'gr_illegal_transition', sprintf( 'Illegal transition %s -> %s.', $from, $to ) );
		}

		$entry = [
			'from'       => $from,
			'to'         => $to,
			'timestamp'  => gmdate( 'c' ),
			'trigger'    => sanitize_text_field( $context['trigger'] ?? 'MANUAL' ),
			'reference'  => sanitize_text_field( $context['reference'] ?? '' ),
			'actor_type' => Vocab::clamp( $context['actor'] ?? 'SYSTEM', Vocab::ACCESS, 'SYSTEM' ),
		];

		// Persist.
		update_post_meta( $post_id, 'state', $to );
		wp_set_object_terms( $post_id, $to, 'ghost_state', false );

		$history   = get_post_meta( $post_id, '_ghost_state_history', true );
		$history   = is_array( $history ) ? $history : [];
		$history[] = $entry;
		update_post_meta( $post_id, '_ghost_state_history', $history );

		self::audit( (string) $post_id, $entry );
		Log::line( 'state_transition', [ 'post' => $post_id, 'from' => $from, 'to' => $to, 'trigger' => $entry['trigger'] ] );

		/** Fires after a successful mutation. */
		do_action( 'ghost_root_state_transitioned', $post_id, $from, $to, $entry );

		return true;
	}

	public static function current( int $post_id ): string {
		$state = (string) get_post_meta( $post_id, 'state', true );
		return Vocab::clamp( $state, Vocab::GHOST_STATES, 'DORMANT' );
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	public static function history( int $post_id ): array {
		$h = get_post_meta( $post_id, '_ghost_state_history', true );
		return is_array( $h ) ? $h : [];
	}

	private static function audit( string $subject_id, array $entry ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'ghost_root_events',
			[
				'event_type' => 'state_transition',
				'subject_id' => $subject_id,
				'actor_type' => $entry['actor_type'],
				'ip_hash'    => '',
				'payload'    => wp_json_encode( $entry ),
				'result'     => 'ok',
				'created_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
	}
}
