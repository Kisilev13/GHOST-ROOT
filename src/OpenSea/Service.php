<?php
/**
 * OpenSea module wiring: REST, admin, cron cache-warmer, and the
 * [ghost_root_marketplace] / [ghost_root_market_state] shortcodes.
 *
 * The public site NEVER blocks on OpenSea: templates read from cache only, and
 * a wp-cron job (every 5 min) keeps the hot data warm. If cron is disabled the
 * stale-while-revalidate path in Cache still refreshes opportunistically.
 *
 * @package GhostRoot
 */

namespace GhostRoot\OpenSea;

defined( 'ABSPATH' ) || exit;

final class Service {

	public const CRON_HOOK = 'ghost_root_opensea_warm';

	public function hooks(): void {
		( new Rest() )->hooks();
		( new Admin() )->hooks();

		add_shortcode( 'ghost_root_marketplace', [ $this, 'marketplace_shortcode' ] );
		add_shortcode( 'ghost_root_market_state', [ $this, 'market_state_shortcode' ] );

		add_action( Cache::REFRESH_HOOK, [ $this, 'warm_one' ], 10, 1 );
		add_action( self::CRON_HOOK, [ $this, 'warm_all' ] );
		add_filter( 'cron_schedules', [ $this, 'cron_schedules' ] );
		add_action( 'init', [ $this, 'maybe_schedule' ] );
	}

	public function cron_schedules( array $s ): array {
		$s['ghost_root_5min'] = [ 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Every 5 minutes (GHOST//ROOT)' ];
		return $s;
	}

	public function maybe_schedule(): void {
		if ( Config::has_api_key() && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'ghost_root_5min', self::CRON_HOOK );
		}
		if ( ! Config::has_api_key() ) {
			$ts = wp_next_scheduled( self::CRON_HOOK );
			if ( $ts ) {
				wp_unschedule_event( $ts, self::CRON_HOOK );
			}
		}
	}

	public static function unschedule(): void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	/** Warm one key (single-event handler from Cache SWR). */
	public function warm_one( string $key ): void {
		switch ( $key ) {
			case 'collection':
				Cache::forget( 'collection' );
				Collection::public_data();
				break;
			case 'stats':
				Cache::forget( 'stats' );
				Stats::public_data();
				break;
			case 'activity':
				Cache::forget( 'activity' );
				Activity::recent();
				break;
			case 'listings':
				Cache::forget( 'listings' );
				Activity::listings_summary();
				break;
			case 'verification':
				Cache::forget( 'verification' );
				Cache::put( 'verification', Verification::compute(), Config::ttl( 'verification' ) );
				break;
		}
	}

	/** Full warm pass (cron). Skips work entirely when nothing is indexed. */
	public function warm_all(): void {
		if ( ! Config::has_api_key() ) {
			return;
		}
		Cache::put( 'verification', Verification::compute(), Config::ttl( 'verification' ) );
		if ( '' === Config::collection_slug() ) {
			return; // nothing else to warm until a verified slug exists
		}
		Cache::forget( 'collection' );
		Collection::public_data();
		Cache::forget( 'stats' );
		Stats::public_data();
		Cache::forget( 'listings' );
		Activity::listings_summary();
		Cache::forget( 'activity' );
		Activity::recent();
	}

	/* ---- Shortcodes ------------------------------------------------------ */

	/** SECONDARY MARKET module — native GHOST//ROOT styling, not an OS widget. */
	public function marketplace_shortcode( $atts = [] ): string {
		wp_enqueue_style( 'ghost-root-components' );
		wp_enqueue_script( 'ghost-root-market' );
		$v      = Verification::report();
		$stats  = Stats::public_data();
		$listed = Activity::listings_summary();
		$count  = $listed['available'] ? $listed['count'] : null;

		$state_label = [
			Config::STATE_VERIFIED    => 'ACTIVE',
			Config::STATE_PARTIAL     => 'INDEXING',
			Config::STATE_PENDING     => 'PENDING',
			Config::STATE_NOT_INDEXED => 'NOT LISTED',
			Config::STATE_AWAITING    => 'AWAITING DEPLOYMENT',
			Config::STATE_MISMATCH    => 'UNVERIFIED',
			Config::STATE_ERROR       => 'UNAVAILABLE',
		][ $v['state'] ] ?? 'UNAVAILABLE';

		$has_market = Config::STATE_VERIFIED === $v['state'] || Config::STATE_PARTIAL === $v['state'];
		$dash       = '—';

		ob_start(); ?>
		<section class="gr-market" data-endpoint="<?php echo esc_url( rest_url( 'ghost-root/v1/opensea/stats' ) ); ?>" data-state="<?php echo esc_attr( strtolower( $v['state'] ) ); ?>">
			<p class="gr-eyebrow">SECONDARY MARKET</p>
			<dl class="gr-data-list">
				<div><dt>MARKET</dt><dd>OPENSEA</dd></div>
				<div><dt>NETWORK</dt><dd>SOLANA</dd></div>
				<div><dt>STATUS</dt><dd data-market-status><?php echo esc_html( $state_label ); ?></dd></div>
				<div><dt>FLOOR</dt><dd data-market-floor><?php echo esc_html( $has_market ? Stats::fmt( $stats['floor_price'], $stats['floor_symbol'] ) : $dash ); ?></dd></div>
				<div><dt>LISTED</dt><dd data-market-listed><?php echo esc_html( null !== $count ? number_format_i18n( $count ) : $dash ); ?></dd></div>
				<div><dt>OWNERS</dt><dd data-market-owners><?php echo esc_html( null !== $stats['num_owners'] ? number_format_i18n( $stats['num_owners'] ) : $dash ); ?></dd></div>
				<div><dt>24H VOLUME</dt><dd data-market-vol><?php echo esc_html( $has_market ? Stats::fmt( $stats['volume_24h'], $stats['volume_symbol'] ) : $dash ); ?></dd></div>
			</dl>
			<?php if ( $has_market && ! empty( $v['opensea_url'] ) ) : ?>
				<a class="gr-button" href="<?php echo esc_url( $v['opensea_url'] ); ?>" target="_blank" rel="noopener nofollow">ACCESS MARKET ↗</a>
			<?php else : ?>
				<p class="gr-muted" data-market-notice>
					<?php
					echo esc_html(
						Config::STATE_AWAITING === $v['state']
							? 'Secondary market opens after the Solana collection is deployed and indexed by OpenSea.'
							: ( Config::STATE_MISMATCH === $v['state']
								? 'No verified OpenSea market for this collection yet.'
								: 'MARKET DATA // TEMPORARILY UNAVAILABLE' )
					);
					?>
				</p>
			<?php endif; ?>
			<p class="gr-mono gr-market-attr">DATA: OPENSEA API · CACHED</p>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** MARKET STATE panel for a single identity page. */
	public function market_state_shortcode( $atts = [] ): string {
		$atts = shortcode_atts( [ 'ghost_id' => 0 ], $atts );
		wp_enqueue_style( 'ghost-root-components' );
		wp_enqueue_script( 'ghost-root-market' );
		$v = Verification::report();

		if ( Config::STATE_VERIFIED !== $v['state'] && Config::STATE_PARTIAL !== $v['state'] ) {
			$msg = Config::STATE_AWAITING === $v['state']
				? 'Not yet minted. No market data.'
				: 'MARKET DATA // TEMPORARILY UNAVAILABLE';
			return '<section class="gr-market-state"><p class="gr-eyebrow">MARKET STATE</p><p class="gr-muted">' . esc_html( $msg ) . '</p></section>';
		}

		// Per-item data is only shown when we can query the live item; the JS
		// component hydrates from the local proxy. Server renders the shell.
		return '<section class="gr-market-state" data-ghost-id="' . esc_attr( (string) (int) $atts['ghost_id'] )
			. '" data-endpoint="' . esc_url( rest_url( 'ghost-root/v1/opensea/activity?limit=6' ) ) . '">'
			. '<p class="gr-eyebrow">MARKET STATE</p>'
			. '<dl class="gr-data-list"><div><dt>OWNER</dt><dd data-ms-owner>—</dd></div>'
			. '<div><dt>LISTING</dt><dd data-ms-listing>—</dd></div>'
			. '<div><dt>LAST SALE</dt><dd data-ms-last>—</dd></div></dl>'
			. ( ! empty( $v['opensea_url'] ) ? '<a class="gr-text-link" href="' . esc_url( $v['opensea_url'] ) . '" target="_blank" rel="noopener nofollow">VIEW ON OPENSEA ↗</a>' : '' )
			. '</section>';
	}
}
