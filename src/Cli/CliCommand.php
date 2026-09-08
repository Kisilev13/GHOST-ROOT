<?php
/**
 * WP-CLI: `wp ghost-root ...`
 *
 * @package GhostRoot
 */

namespace GhostRoot\Cli;

use GhostRoot\Importer\Importer;
use GhostRoot\Arg\Challenges;
use GhostRoot\State\StateManager;
use GhostRoot\PostTypes\Taxonomies;
use GhostRoot\Support\Vocab;

defined( 'ABSPATH' ) || exit;

final class CliCommand {

	/**
	 * Import GHOST//ROOT metadata JSON into ghost_identity records.
	 *
	 * ## OPTIONS
	 *
	 * <dir>
	 * : Path to the directory containing 0001.json .. 3333.json
	 *
	 * [--dry-run]
	 * : Validate and report without writing.
	 *
	 * [--limit=<n>]
	 * : Import at most N tokens (by ascending id).
	 *
	 * [--offset=<n>]
	 * : Skip the first N tokens.
	 *
	 * [--update]
	 * : Update existing identities instead of skipping them.
	 *
	 * [--ids=<list>]
	 * : Comma-separated explicit token ids (overrides limit/offset).
	 *
	 * ## EXAMPLES
	 *
	 *     wp ghost-root import ./metadata --dry-run
	 *     wp ghost-root import ./metadata --limit=333
	 *     wp ghost-root import ./metadata --update
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string,string>  $assoc_args Flags.
	 */
	public function import( array $args, array $assoc_args ): void {
		$dir = $args[0] ?? '';
		if ( '' === $dir ) {
			\WP_CLI::error( 'Provide the metadata directory path.' );
		}

		$opts = [
			'dry_run' => isset( $assoc_args['dry-run'] ),
			'update'  => isset( $assoc_args['update'] ),
			'limit'   => isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0,
			'offset'  => isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0,
		];
		if ( ! empty( $assoc_args['ids'] ) ) {
			$opts['ids'] = array_filter( array_map( 'intval', explode( ',', (string) $assoc_args['ids'] ) ) );
		}

		$result = ( new Importer() )->run( $dir, $opts, [ '\WP_CLI', 'log' ] );

		if ( 'PASS' === $result['status'] ) {
			\WP_CLI::success( 'Import complete: STATUS PASS' );
		} else {
			\WP_CLI::warning( 'Import finished with errors: STATUS FAIL' );
		}
	}

	/**
	 * Seed base content: taxonomy terms, ARG challenges, incidents, archive, transmissions, pages.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Recreate seed content even if it already exists.
	 *
	 * @param string[]             $args
	 * @param array<string,string> $assoc_args
	 */
	public function seed( array $args, array $assoc_args ): void {
		$force = isset( $assoc_args['force'] );
		Taxonomies::seed_terms();
		Challenges::seed_default();
		( new \GhostRoot\Content\Seeder() )->run( $force, [ '\WP_CLI', 'log' ] );
		\WP_CLI::success( 'Seed complete.' );
	}

	/**
	 * Show a status overview.
	 */
	public function status(): void {
		$fmt = static fn( $l, $v ) => \WP_CLI::log( str_pad( $l, 26 ) . $v );
		$fmt( 'System state', \GhostRoot\Settings\Config::system_state() );
		$fmt( 'Identities', wp_count_posts( 'ghost_identity' )->publish . ' / ' . Vocab::SUPPLY );
		$fmt( 'Incidents', wp_count_posts( 'incident' )->publish );
		$fmt( 'Archive records', wp_count_posts( 'archive_record' )->publish );
		$fmt( 'Transmissions', wp_count_posts( 'transmission' )->publish );
		$mint = \GhostRoot\Settings\Config::mint_config();
		$fmt( 'Mint state', $mint['state'] );
		$fmt( 'Collection address', $mint['collection'] ?: 'NOT YET DEPLOYED' );
		$fmt( 'Candy Machine', $mint['candyMachine'] ?: 'NOT YET DEPLOYED' );
	}

	/**
	 * Transition a ghost identity's state.
	 *
	 * ## OPTIONS
	 *
	 * <ghost_id>
	 * : Numeric ghost id (1..3333).
	 *
	 * <to>
	 * : Target state: DORMANT|ACTIVE|COMPROMISED|ROOTED
	 *
	 * [--trigger=<t>]
	 * : Trigger label. Default MANUAL.
	 *
	 * [--force]
	 * : Allow non-adjacent transitions.
	 *
	 * @param string[]             $args
	 * @param array<string,string> $assoc_args
	 */
	public function transition( array $args, array $assoc_args ): void {
		[ $ghost_id, $to ] = array_pad( $args, 2, '' );
		$p = get_posts(
			[
				'post_type'   => 'ghost_identity',
				'meta_key'    => 'ghost_id',
				'meta_value'  => (int) $ghost_id,
				'numberposts' => 1,
				'fields'      => 'ids',
			]
		);
		if ( ! $p ) {
			\WP_CLI::error( 'Identity not found: ' . $ghost_id );
		}
		$res = StateManager::transition(
			(int) $p[0],
			(string) $to,
			[ 'trigger' => $assoc_args['trigger'] ?? 'MANUAL', 'actor' => 'ADMIN', 'reference' => 'wp-cli' ],
			isset( $assoc_args['force'] )
		);
		if ( is_wp_error( $res ) ) {
			\WP_CLI::error( $res->get_error_message() );
		}
		\WP_CLI::success( sprintf( 'GHOST//%04d -> %s', (int) $ghost_id, StateManager::current( (int) $p[0] ) ) );
	}
}
