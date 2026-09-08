<?php
/**
 * Plugin orchestrator. Wires every module. Holds no domain logic itself.
 *
 * @package GhostRoot
 */

namespace GhostRoot;

use GhostRoot\PostTypes\PostTypes;
use GhostRoot\PostTypes\Taxonomies;
use GhostRoot\Metadata\IdentityMeta;
use GhostRoot\Metadata\MetaBox;
use GhostRoot\Settings\Settings;
use GhostRoot\Rest\Rest;
use GhostRoot\Admin\AdminMenu;
use GhostRoot\Frontend\Shortcodes;
use GhostRoot\Frontend\Assets;
use GhostRoot\Arg\Challenges;
use GhostRoot\OpenSea\Service as OpenSeaService;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/**
	 * Boot every module on the correct hooks.
	 */
	public function init(): void {
		( new PostTypes() )->hooks();
		( new Taxonomies() )->hooks();
		( new IdentityMeta() )->hooks();
		( new MetaBox() )->hooks();
		( new Settings() )->hooks();
		( new Rest() )->hooks();
		( new AdminMenu() )->hooks();
		( new Shortcodes() )->hooks();
		( new Assets() )->hooks();
		( new OpenSeaService() )->hooks();

		add_action( 'init', [ $this, 'maybe_upgrade' ], 1 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'ghost-root', Cli\CliCommand::class );
		}
	}

	/**
	 * Run schema upgrades when the stored version lags the code version.
	 */
	public function maybe_upgrade(): void {
		if ( get_option( 'ghost_root_db_version' ) !== GHOST_ROOT_VERSION ) {
			Activator::install_tables();
			Challenges::seed_default();
			update_option( 'ghost_root_db_version', GHOST_ROOT_VERSION );
		}
	}
}
