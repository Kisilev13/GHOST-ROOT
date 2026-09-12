<?php
/**
 * Activation / deactivation: custom tables, rewrite flush, default options.
 *
 * @package GhostRoot
 */

namespace GhostRoot;

use GhostRoot\PostTypes\PostTypes;
use GhostRoot\PostTypes\Taxonomies;
use GhostRoot\Arg\Challenges;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public static function activate(): void {
		self::install_tables();

		// Register objects then flush so pretty permalinks work immediately.
		( new PostTypes() )->register();
		( new Taxonomies() )->register();
		Taxonomies::seed_terms();
		Challenges::seed_default();

		if ( false === get_option( 'ghost_root_settings' ) ) {
			add_option( 'ghost_root_settings', self::default_settings() );
		}
		update_option( 'ghost_root_db_version', GHOST_ROOT_VERSION );

		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		\GhostRoot\OpenSea\Service::unschedule();
		$single = wp_next_scheduled( \GhostRoot\OpenSea\Cache::REFRESH_HOOK );
		if ( $single ) {
			wp_clear_scheduled_hook( \GhostRoot\OpenSea\Cache::REFRESH_HOOK );
		}
		flush_rewrite_rules();
	}

	/**
	 * Create custom tables via dbDelta. Idempotent.
	 */
	public static function install_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$events  = $wpdb->prefix . 'ghost_root_events';
		$attempts = $wpdb->prefix . 'ghost_root_attempts';

		$sql_events = "CREATE TABLE {$events} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_type VARCHAR(40) NOT NULL DEFAULT '',
			subject_id VARCHAR(64) NOT NULL DEFAULT '',
			actor_type VARCHAR(20) NOT NULL DEFAULT 'SYSTEM',
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			payload LONGTEXT NULL,
			result VARCHAR(20) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY event_subject (event_type, subject_id),
			KEY ip_time (ip_hash, created_at)
		) {$charset};";

		$sql_attempts = "CREATE TABLE {$attempts} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			challenge_slug VARCHAR(64) NOT NULL DEFAULT '',
			stage SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			correct TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY chal_ip_time (challenge_slug, ip_hash, created_at)
		) {$charset};";

		dbDelta( $sql_events );
		dbDelta( $sql_attempts );
		dbDelta("CREATE TABLE {$wpdb->prefix}ghost_root_limits (
			bucket_key CHAR(64) NOT NULL,
			requests INT UNSIGNED NOT NULL DEFAULT 0,
			expires_at BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (bucket_key),
			KEY expiry (expires_at)
		) {$charset};");
	}

	/**
	 * Default settings — no addresses, safe pre-launch posture.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_settings(): array {
		return [
			'system_state'          => 'DISCOVERY',
			'network_status'        => 'DEGRADED',
			'mint_state'            => 'PRE_MINT',
			'mint_price_sol'        => 0.15,
			'mint_max_per_wallet'   => 5,
			'collection_address'    => '',
			'candy_machine_address' => '',
			'update_authority'      => '',
			'treasury_address'      => '',
			'metadata_cid'          => '',
			'artwork_cid'           => '',
			'discord_url'           => '',
			'x_url'                 => '',
			'opensea_collection_slug' => '',
			'contract_verified'     => false,
			'signal_strength'       => 81,
			'active_nodes'          => 12,
		];
	}
}
