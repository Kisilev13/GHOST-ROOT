<?php
/**
 * ARG / CTF challenge registry. Challenge definitions live in options (small, stable).
 * Answers are stored ONLY as salted SHA-256 hashes — never plaintext.
 *
 * @package GhostRoot
 */

namespace GhostRoot\Arg;

defined( 'ABSPATH' ) || exit;

final class Challenges {

	public const OPTION = 'ghost_root_challenges';

	/**
	 * Normalise a candidate answer before hashing/compare.
	 */
	public static function normalize( string $answer ): string {
		$answer = strtolower( trim( $answer ) );
		$answer = preg_replace( '/^(flag|ghost)\{(.+)\}$/', '$2', $answer );
		$answer = preg_replace( '/\s+/', '', $answer );
		return $answer;
	}

	public static function hash( string $normalized ): string {
		$salt = defined( 'NONCE_SALT' ) ? NONCE_SALT : 'ghost-root-arg';
		return hash( 'sha256', 'gr-arg|' . $salt . '|' . $normalized );
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, [] );
		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( string $slug ): ?array {
		$all = self::all();
		return $all[ $slug ] ?? null;
	}

	/**
	 * @return array<string,mixed>|null Public-safe view (no answer hashes).
	 */
	public static function public_view( string $slug, int $stage ): ?array {
		$c = self::get( $slug );
		if ( ! $c ) {
			return null;
		}
		$stages = $c['stages'];
		if ($stage < 1 || $stage > count($stages) || $stage > Verifier::unlocked_stage($slug)) { return null; }
		$s      = $stages[ $stage - 1 ];
		return [
			'slug'         => $slug,
			'title'        => $c['title'],
			'stage'        => $stage,
			'total_stages' => count( $stages ),
			'prompt'       => $s['prompt'],
			'kind'         => $s['kind'],
			'max_attempts' => (int) $c['max_attempts'],
			'cooldown'     => (int) $c['cooldown'],
			'released'     => (bool) $c['released'],
			'hints'        => array_slice($s['hints'] ?? [], 0, min(3, 1 + Verifier::failed_attempts($slug, $stage))),
		];
	}

	/**
	 * Idempotently seed the default Incident 31 chain.
	 */
	public static function seed_default(): void {
		$existing = self::all();
		if ( isset( $existing['incident-31'] ) ) {
			return;
		}

		// Stage answers (plaintext here only to derive the stored hash on seed).
		//  1: base64("node thirty one")                -> "nodethirtyone"
		//  2: hex of "root port"                        -> "rootport"
		//  3: rot13("frperg" => "secret") keyword       -> "secret"
		$stages = [
			[
				'kind'   => 'base64',
				'prompt' => "Recovered fragment:\n\nbm9kZSB0aGlydHkgb25l\n\nDecode it. Submit the plaintext.",
				'hints'  => [ 'Standard Base64.', 'It decodes to three words.', 'It names the node that should be dead.' ],
				'answer' => 'node thirty one',
			],
			[
				'kind'   => 'hex',
				'prompt' => "NODE-31 responded with:\n\n726f6f7420706f7274\n\nDecode the hex. Submit the plaintext.",
				'hints'  => [ 'Hex -> ASCII.', 'Two words.', 'It is an implant trait.' ],
				'answer' => 'root port',
			],
			[
				'kind'   => 'cipher',
				'prompt' => "Final transmission (ROT13):\n\nfrperg\n\nRotate it back. Submit the word.",
				'hints'  => [ 'Caesar shift of 13.', 'One word.', 'What the ROOT network keeps.' ],
				'answer' => 'secret',
			],
		];

		foreach ( $stages as &$s ) {
			$s['answer_hash'] = self::hash( self::normalize( $s['answer'] ) );
			unset( $s['answer'] );
		}
		unset( $s );

		$existing['incident-31'] = [
			'title'        => 'INCIDENT // 031',
			'stages'       => $stages,
			'max_attempts' => 6,
			'cooldown'     => 900,
			'released'     => true,
			'reward'       => 'INCIDENT 31 CLEARED — you were never here.',
		];

		update_option( self::OPTION, $existing, false );
	}
}
