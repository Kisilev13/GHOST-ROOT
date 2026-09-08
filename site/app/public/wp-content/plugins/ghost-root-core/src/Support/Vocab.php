<?php
/**
 * Canonical GHOST//ROOT vocabulary. Single source of truth for trait allowlists,
 * system states, rarity bands and their target supply counts.
 *
 * @package GhostRoot
 */

namespace GhostRoot\Support;

defined( 'ABSPATH' ) || exit;

final class Vocab {

	public const SUPPLY = 3333;

	/** @var array<string,int> */
	public const RARITY_BANDS = [
		'STANDARD'  => 2700,
		'CORRUPTED' => 500,
		'ADMIN'     => 100,
		'ROOT'      => 30,
		'GENESIS'   => 3,
	];

	/** @var string[] */
	public const SYSTEM_STATES = [
		'PRE_DISCOVERY',
		'DISCOVERY',
		'PRE_MINT',
		'MINT_LIVE',
		'SOLD_OUT',
		'POST_MINT',
		'INCIDENT',
	];

	/** @var string[] Ordered mutation chain. */
	public const GHOST_STATES = [ 'DORMANT', 'ACTIVE', 'COMPROMISED', 'ROOTED' ];

	/** @var string[] */
	public const ACCESS = [ 'USER', 'OPERATOR', 'ADMIN', 'SYSTEM', 'ROOT' ];

	/** @var string[] */
	public const ENTITY = [ 'HUMAN', 'SYNTHETIC', 'HOLLOW', 'SPECTER' ];

	/**
	 * Trait_type (as it appears in metadata attributes) => allowed values.
	 * Values are upper-cased on both sides before comparison.
	 *
	 * @var array<string,string[]>
	 */
	public const TRAITS = [
		'Entity'     => self::ENTITY,
		'Face'       => [ 'PORCELAIN', 'CARBON', 'CHROME', 'RECONSTRUCTED', 'CERAMIC', 'BIO-SYNTH' ],
		'Eyes'       => [ 'THERMAL', 'VOID', 'BIOMETRIC', 'FRACTURED', 'OPTICAL ARRAY', 'SIGNAL-BURN', 'NULL APERTURE', 'REVOCATION LENS', 'WITNESS ARRAY' ],
		'Mask'       => [ 'RESPIRATOR', 'FORENSIC PLATE', 'SKELETAL INTERFACE', 'NULL MASK', 'NEURAL VEIL', 'NONE' ],
		'Implant'    => [ 'NEURAL CABLE', 'ANTENNA', 'OPTICAL ARRAY', 'SPINAL BUS', 'SIGNAL CROWN', 'ROOT PORT' ],
		'Corruption' => [ 'GLITCH', 'BURN', 'SIGNAL LOSS', 'FRAGMENTATION', 'PACKET GHOSTING', 'MEMORY BLEED', 'NONE' ],
		'Access'     => self::ACCESS,
		'Background'  => [ 'SERVER', 'BLACKSITE', 'SATELLITE', 'VOID', 'ARCHIVE', 'COLD STORAGE', 'SIGNAL CHAMBER' ],
		'State'      => self::GHOST_STATES,
		'Signal'     => self::SIGNAL,
		'Rarity Band' => [ 'STANDARD', 'CORRUPTED', 'ADMIN', 'ROOT', 'GENESIS' ],
		'Signal'     => [ 'STABLE', 'DEGRADED', 'INTERMITTENT', 'CORRUPTED', 'NULL', 'UNKNOWN' ],
		'Archetype'  => [
			'THE OBSERVER', 'THE FRACTURE', 'THE HOLLOW', 'THE BREACH', 'THE SIGNAL',
			'THE OVERMIND', 'THE CROWN', 'THE TRANSCENDENT', 'THE ARCHIVIST', 'THE NULL',
			'THE WITNESS', 'THE ROOT',
		],
	];

	/** @var string[] Signal condition vocabulary (derived, not always in metadata). */
	public const SIGNAL = [ 'STABLE', 'DEGRADED', 'INTERMITTENT', 'CORRUPTED', 'NULL', 'UNKNOWN' ];

	/** @var string[] */
	public const ENTITY_TAXONOMY = [ 'HUMAN', 'SYNTHETIC', 'HOLLOW', 'SPECTER' ];

	/**
	 * Is a value allowed for a given trait_type? Case / whitespace tolerant.
	 */
	public static function is_valid_trait( string $trait_type, string $value ): bool {
		if ( ! isset( self::TRAITS[ $trait_type ] ) ) {
			return false;
		}
		$needle = strtoupper( trim( preg_replace( '/\s+/', ' ', $value ) ) );
		foreach ( self::TRAITS[ $trait_type ] as $allowed ) {
			if ( strtoupper( $allowed ) === $needle ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Clamp an arbitrary string to a member of an allowlist, or return $default.
	 *
	 * @param string[] $allowed Allowlist.
	 */
	public static function clamp( string $value, array $allowed, string $default = '' ): string {
		$needle = strtoupper( trim( preg_replace( '/\s+/', ' ', $value ) ) );
		foreach ( $allowed as $a ) {
			if ( strtoupper( $a ) === $needle ) {
				return $a;
			}
		}
		return $default;
	}
}
