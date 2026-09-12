<?php
/**
 * Typed, read-only accessor for GHOST//ROOT system configuration.
 *
 * @package GhostRoot
 */

namespace GhostRoot\Settings;

use GhostRoot\Activator;
use GhostRoot\Support\Vocab;

defined( 'ABSPATH' ) || exit;

final class Config {

	public const OPTION = 'ghost_root_settings';

	/** @var array<string,mixed>|null */
	private static $cache = null;

	/**
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored     = get_option( self::OPTION, [] );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : [], Activator::default_settings() );
		}
		return self::$cache;
	}

	public static function flush(): void {
		self::$cache = null;
	}

	public static function get( string $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public static function system_state(): string {
		return Vocab::clamp( (string) self::get( 'system_state' ), Vocab::SYSTEM_STATES, 'DISCOVERY' );
	}

	public static function is_mint_live(): bool {
		return 'MINT_LIVE' === self::get( 'mint_state' ) && self::get( 'candy_machine_address' );
	}

	/**
	 * Public, non-sensitive mint configuration for the REST endpoint.
	 *
	 * @return array<string,mixed>
	 */
	public static function launch_plan(): array {
		$path = dirname( __DIR__, 2 ) . "/assets/launch-plan.json";
		$data = is_readable( $path ) ? json_decode( file_get_contents( $path ), true ) : [];
		return is_array( $data ) ? $data : [];
	}

	public static function mint_config(): array {
		return [
			'network'      => 'solana',
			'standard'     => 'metaplex-core',
			'mint'         => 'core-candy-machine',
			'collection'   => self::get( 'collection_address' ) ?: null,
			'candyMachine' => self::get( 'candy_machine_address' ) ?: null,
			'price'        => (float) self::get( 'mint_price_sol' ),
			'maxPerWallet' => (int) self::get( 'mint_max_per_wallet' ),
			'state'        => (string) self::get( 'mint_state' ),
			'supply'       => Vocab::SUPPLY,
			'launchPlan'   => self::launch_plan(),
			'walletLimitScope' => 'per_phase_independent',
			'combinedMaxPerWallet' => 10,
			'deployed'     => (bool) (self::get('collection_address') && self::get('candy_machine_address') && self::get('treasury_address') && self::get('contract_verified')),
		];
	}

	/**
	 * Verification fields for /verify/. Unset values surface as null -> "NOT YET DEPLOYED".
	 *
	 * @return array<string,?string>
	 */
	public static function verification(): array {
		$keys = [
			'collection_address', 'candy_machine_address', 'update_authority', 'treasury_address',
			'metadata_cid', 'artwork_cid',
		];
		$out = [];
		foreach ( $keys as $k ) {
			$v        = (string) self::get( $k );
			$out[ $k ] = '' === $v ? null : $v;
		}
		$out['source_hash']     = self::get( 'source_hash' ) ?: null;
		$out['deployment_hash'] = self::get( 'deployment_hash' ) ?: null;
		return $out;
	}
}
