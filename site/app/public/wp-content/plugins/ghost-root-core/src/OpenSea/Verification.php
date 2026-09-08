<?php
/**
 * Marketplace verification state for /verify/ and the marketplace module.
 *
 * A slug is only reported VERIFIED when the on-chain identifiers match — never
 * on name similarity (task section 10). The `ghost-root` slug on OpenSea today
 * is an unrelated Ethereum collection; this class returns MISMATCH for anything
 * whose chain/address disagrees with the authoritative Solana collection.
 *
 * @package GhostRoot
 */

namespace GhostRoot\OpenSea;

defined( 'ABSPATH' ) || exit;

final class Verification {

	/**
	 * @return array{
	 *   state:string, slug:?string, chain:string, indexed_items:?int,
	 *   checks:array<int,array{field:string,expected:string,actual:string,ok:bool,critical:bool}>,
	 *   opensea_url:?string, checked_at:int, cache_state:string, notes:string[]
	 * }
	 */
	public static function report(): array {
		$hit = Cache::remember( 'verification', Config::ttl( 'verification' ), [ self::class, 'compute' ] );
		$data = $hit['data'];
		if ( ! is_array( $data ) ) {
			return self::scaffold( Config::STATE_ERROR, null, [ 'Verification could not be computed.' ] );
		}
		$data['cache_state'] = $hit['state'];
		return $data;
	}

	/**
	 * Cheap, non-blocking state for high-traffic surfaces (the footer runs on
	 * every page). Reads the warmed transient; never calls OpenSea. Falls back
	 * to the coarse config-only status.
	 *
	 * @return array{state:string,slug:?string,chain:string,opensea_url:?string}
	 */
	public static function quick(): array {
		$peek = Cache::peek( 'verification' );
		if ( is_array( $peek ) && is_array( $peek['d'] ?? null ) && isset( $peek['d']['state'] ) ) {
			return [
				'state'       => (string) $peek['d']['state'],
				'slug'        => $peek['d']['slug'] ?? null,
				'chain'       => (string) ( $peek['d']['chain'] ?? Config::chain() ),
				'opensea_url' => $peek['d']['opensea_url'] ?? null,
			];
		}
		$slug = Config::collection_slug();
		return [
			'state'       => Config::status(),
			'slug'        => $slug ?: null,
			'chain'       => Config::chain(),
			'opensea_url' => $slug ? esc_url_raw( 'https://opensea.io/collection/' . $slug ) : null,
		];
	}

	/** Force a recompute (admin / cron). */
	public static function compute() {
		$chain     = Config::chain();
		$want_addr = Config::solana_collection_address();
		$slug      = Config::collection_slug();

		if ( '' === $want_addr ) {
			return self::scaffold(
				Config::STATE_AWAITING,
				$slug ?: null,
				[
					'The Metaplex Core collection has not been deployed. There is nothing for OpenSea to index yet.',
					'The full integration is in place and will verify automatically once the collection address is set.',
				]
			);
		}
		if ( '' === $slug ) {
			return self::scaffold(
				Config::STATE_NOT_INDEXED,
				null,
				[ 'No verified OpenSea slug is configured for this collection. Run the operator discovery tool once minted.' ]
			);
		}

		$collection = Collection::fetch( $slug );
		if ( is_wp_error( $collection ) ) {
			$code = (string) ( $collection->get_error_data()['status'] ?? '' );
			return self::scaffold(
				'404' === $code ? Config::STATE_NOT_INDEXED : Config::STATE_ERROR,
				$slug,
				[ '404' === $code ? 'Configured slug does not resolve on OpenSea.' : 'OpenSea did not respond during verification.' ]
			);
		}

		$contracts = (array) ( $collection['contracts'] ?? [] );
		$got_chain = '';
		$got_addr  = '';
		foreach ( $contracts as $c ) {
			if ( is_array( $c ) && ( $c['chain'] ?? '' ) === $chain ) {
				$got_chain = (string) $c['chain'];
				$got_addr  = (string) $c['address'];
			}
		}
		if ( '' === $got_chain && $contracts ) {
			$first     = is_array( $contracts[0] ) ? $contracts[0] : [];
			$got_chain = (string) ( $first['chain'] ?? '' );
			$got_addr  = (string) ( $first['address'] ?? '' );
		}

		$supply = (int) ( $collection['total_supply'] ?? $collection['unique_item_count'] ?? 0 );
		$name   = (string) ( $collection['name'] ?? '' );
		$purl   = rtrim( (string) ( $collection['project_url'] ?? '' ), '/' );

		$checks = [
			self::chk( 'chain', $chain, $got_chain ?: 'none', $got_chain === $chain, true ),
			self::chk( 'collection_address', $want_addr, $got_addr ?: 'none', strcasecmp( $got_addr, $want_addr ) === 0, true ),
			self::chk( 'name', 'GHOST//ROOT', $name ?: 'none', self::norm( $name ) === self::norm( 'GHOST//ROOT' ), false ),
			self::chk( 'indexed_items', (string) \GhostRoot\Support\Vocab::SUPPLY, (string) $supply, 0 === $supply || abs( $supply - \GhostRoot\Support\Vocab::SUPPLY ) <= 67, false ),
			self::chk( 'project_url', home_url( '/' ), $purl ?: 'none', self::host_eq( $purl, home_url() ), false ),
		];

		$critical_fail = false;
		$soft_fail     = false;
		foreach ( $checks as $c ) {
			if ( ! $c['ok'] && $c['critical'] ) {
				$critical_fail = true;
			}
			if ( ! $c['ok'] && ! $c['critical'] ) {
				$soft_fail = true;
			}
		}

		$notes = [];
		if ( $critical_fail ) {
			$state   = Config::STATE_MISMATCH;
			$notes[] = 'CRITICAL MISMATCH: the OpenSea collection under this slug is on a different chain or address than the authoritative GHOST//ROOT Solana collection. Automated writes are disabled.';
		} elseif ( 0 === $supply ) {
			$state   = Config::STATE_PARTIAL;
			$notes[] = 'Collection resolves but no items are indexed yet.';
		} elseif ( $soft_fail ) {
			$state = Config::STATE_PARTIAL;
		} else {
			$state = Config::STATE_VERIFIED;
		}

		return [
			'state'         => $state,
			'slug'          => $slug,
			'chain'         => $chain,
			'indexed_items' => $supply ?: null,
			'checks'        => $checks,
			'opensea_url'   => esc_url_raw( (string) ( $collection['opensea_url'] ?? ( 'https://opensea.io/collection/' . $slug ) ) ),
			'checked_at'    => time(),
			'cache_state'   => 'fresh',
			'notes'         => $notes,
		];
	}

	private static function scaffold( string $state, ?string $slug, array $notes ): array {
		return [
			'state'         => $state,
			'slug'          => $slug,
			'chain'         => Config::chain(),
			'indexed_items' => null,
			'checks'        => [],
			'opensea_url'   => $slug ? esc_url_raw( 'https://opensea.io/collection/' . $slug ) : null,
			'checked_at'    => time(),
			'cache_state'   => 'fresh',
			'notes'         => $notes,
		];
	}

	private static function chk( string $field, string $expected, string $actual, bool $ok, bool $critical ): array {
		return compact( 'field', 'expected', 'actual', 'ok', 'critical' );
	}

	private static function norm( string $s ): string {
		return strtoupper( preg_replace( '/\s+/', '', trim( $s ) ) );
	}

	private static function host_eq( string $a, string $b ): bool {
		$ha = wp_parse_url( $a, PHP_URL_HOST );
		$hb = wp_parse_url( $b, PHP_URL_HOST );
		if ( ! $ha || ! $hb ) {
			return false;
		}
		return ltrim( strtolower( $ha ), 'www.' ) === ltrim( strtolower( $hb ), 'www.' );
	}
}
