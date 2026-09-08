<?php
/**
 * ARG answer verification: timing-safe comparison, attempt counting, cooldown.
 *
 * @package GhostRoot
 */

namespace GhostRoot\Arg;

use GhostRoot\Support\RateLimiter;
use GhostRoot\Support\Log;

defined( 'ABSPATH' ) || exit;

final class Verifier {

	/**
	 * @param string $slug   Challenge slug.
	 * @param int    $stage  1-based stage.
	 * @param string $answer Raw user answer.
	 * @return array{ok:bool,status:string,message:string,stage:int,total_stages:int,attempts_left:int,cooldown:int,reward?:string}
	 */
	public static function verify( string $slug, int $stage, string $answer ): array {
		$c = Challenges::get( $slug );
		if ( ! $c || empty( $c['released'] ) ) {
			return self::deny( 'not_found', 'No such challenge.', 1, 1 );
		}

		$stages = $c['stages'];
		$total  = count( $stages );
		if ($stage < 1 || $stage > $total || $stage > self::unlocked_stage($slug) || strlen($answer) > 256) {
			return self::deny('invalid_stage', 'Stage is locked or input is invalid.', $stage, $total);
		}
		$max    = (int) $c['max_attempts'];
		$cool   = (int) $c['cooldown'];

		$ip_hash = substr( RateLimiter::actor_hash( 'arg:' . $slug ), 0, 64 );
		$window  = self::attempts_in_window( $slug, $ip_hash, $cool );

		if ( $window >= $max ) {
			Log::line( 'arg_cooldown', [ 'slug' => $slug, 'stage' => $stage ] );
			return [
				'ok'            => false,
				'status'        => 'cooldown',
				'message'       => sprintf( 'Attempt limit reached. Cooldown %d minutes.', (int) ceil( $cool / 60 ) ),
				'stage'         => $stage,
				'total_stages'  => $total,
				'attempts_left' => 0,
				'cooldown'      => $cool,
			];
		}

		$expected  = (string) ( $stages[ $stage - 1 ]['answer_hash'] ?? '' );
		$submitted = Challenges::hash( Challenges::normalize( $answer ) );
		$correct   = hash_equals( $expected, $submitted );

		self::record( $slug, $stage, $ip_hash, $correct );
		Log::line( 'arg_attempt', [ 'slug' => $slug, 'stage' => $stage, 'result' => $correct ? 'correct' : 'wrong' ] );

		$attempts_left = max( 0, $max - ( $window + 1 ) );

		if ( ! $correct ) {
			return [
				'ok'            => false,
				'status'        => 'wrong',
				'message'       => 'ACCESS DENIED.',
				'stage'         => $stage,
				'total_stages'  => $total,
				'attempts_left' => $attempts_left,
				'cooldown'      => $cool,
			];
		}

		$next = $stage + 1;
		if ( $next > $total ) {
			return [
				'ok'            => true,
				'status'        => 'complete',
				'message'       => (string) ( $c['reward'] ?? 'CHALLENGE CLEARED.' ),
				'stage'         => $stage,
				'total_stages'  => $total,
				'attempts_left' => $attempts_left,
				'cooldown'      => $cool,
				'reward'        => (string) ( $c['reward'] ?? '' ),
			];
		}

		return [
			'ok'            => true,
			'status'        => 'advance',
			'message'       => 'STAGE CLEARED. Advancing.',
			'stage'         => $next,
			'total_stages'  => $total,
			'attempts_left' => $attempts_left,
			'cooldown'      => $cool,
		];
	}

	private static function deny( string $status, string $msg, int $stage, int $total ): array {
		return [
			'ok'            => false,
			'status'        => $status,
			'message'       => $msg,
			'stage'         => $stage,
			'total_stages'  => $total,
			'attempts_left' => 0,
			'cooldown'      => 0,
		];
	}

	public static function unlocked_stage(string $slug): int {
		global $wpdb;
		$hash = RateLimiter::actor_hash('arg:' . $slug);
		$completed = array_map('intval', $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT stage FROM {$wpdb->prefix}ghost_root_attempts WHERE challenge_slug = %s AND ip_hash = %s AND correct = 1",
			$slug, $hash
		)));
		$stage = 1;
		while (in_array($stage, $completed, true)) { $stage++; }
		return $stage;
	}

	public static function failed_attempts(string $slug, int $stage): int {
		global $wpdb;
		return (int)$wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ghost_root_attempts WHERE challenge_slug = %s AND stage = %d AND ip_hash = %s AND correct = 0",
			$slug, $stage, RateLimiter::actor_hash('arg:' . $slug)
		));
	}

	private static function attempts_in_window( string $slug, string $ip_hash, int $window ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'ghost_root_attempts';
		$since = gmdate( 'Y-m-d H:i:s', time() - max( 60, $window ) );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE challenge_slug = %s AND ip_hash = %s AND created_at >= %s",
				$slug,
				$ip_hash,
				$since
			)
		);
	}

	private static function record( string $slug, int $stage, string $ip_hash, bool $correct ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'ghost_root_attempts',
			[
				'challenge_slug' => $slug,
				'stage'          => $stage,
				'ip_hash'        => $ip_hash,
				'correct'        => $correct ? 1 : 0,
				'created_at'     => current_time( 'mysql', true ),
			],
			[ '%s', '%d', '%s', '%d', '%s' ]
		);

		if ( $correct ) {
			$wpdb->insert(
				$wpdb->prefix . 'ghost_root_events',
				[
					'event_type' => 'arg_progress',
					'subject_id' => $slug . ':' . $stage,
					'actor_type' => 'USER',
					'ip_hash'    => $ip_hash,
					'payload'    => wp_json_encode( [ 'stage' => $stage ] ),
					'result'     => 'correct',
					'created_at' => current_time( 'mysql', true ),
				],
				[ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
			);
		}
	}
}
