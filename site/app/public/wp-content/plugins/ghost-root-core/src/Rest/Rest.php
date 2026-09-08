<?php
/**
 * REST API — namespace ghost-root/v1.
 *
 * THREAT MODEL
 *  - GET routes expose only non-sensitive, already-public identity/incident data.
 *    Their permission_callback is the named `public_read` (documented intentional).
 *  - POST /terminal and POST /arg/{challenge}/verify are unauthenticated by design (public
 *    puzzle/interaction surface) but are rate limited, length capped, allowlisted,
 *    and never mutate privileged state. Documented named callback `public_interact`.
 *  - POST /state/transition requires `manage_options`. Never public.
 *  - No route uses a bare `__return_true`.
 *
 * @package GhostRoot
 */

namespace GhostRoot\Rest;

use GhostRoot\Settings\Config;
use GhostRoot\Support\Vocab;
use GhostRoot\Support\RateLimiter;
use GhostRoot\Terminal\Terminal;
use GhostRoot\Arg\Challenges;
use GhostRoot\Arg\Verifier;
use GhostRoot\State\StateManager;
use GhostRoot\Metadata\Portrait;

defined( 'ABSPATH' ) || exit;

final class Rest {

	private const NS = 'ghost-root/v1';

	public function hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register' ] );
	}

	/* ---- Permission callbacks (named + documented) ---------------------- */

	/** Public read: identity/incident/system data that is already visible on the site. */
	public function public_read(): bool {
		return true;
	}

	/** Public interaction: terminal + ARG. Safe because rate-limited, allowlisted, no privileged writes. */
	public function public_interact( \WP_REST_Request $req ): bool|\WP_Error {
		$origin = $req->get_header('origin');
		if ($origin) {
			$site = wp_parse_url(home_url());
			$other = wp_parse_url($origin);
			if (!$other || ($site['host'] ?? '') !== ($other['host'] ?? '') || ($site['scheme'] ?? '') !== ($other['scheme'] ?? '') || ($site['port'] ?? null) !== ($other['port'] ?? null)) {
				return new \WP_Error('gr_origin', 'Cross-origin interaction is not accepted.', ['status'=>403]);
			}
		}
		return true;
	}

	/** Privileged: mutation engine. */
	public function require_admin(): bool {
		return current_user_can( 'manage_options' );
	}

	/* ---- Routes -------------------------------------------------------- */

	public function register(): void {
		register_rest_route(
			self::NS,
			'/system',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'public_read' ],
				'callback'            => [ $this, 'system' ],
			]
		);

		register_rest_route(
			self::NS,
			'/identities',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'public_read' ],
				'callback'            => [ $this, 'identities' ],
				'args'                => [
					'page'     => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
					'per_page' => [ 'default' => 24, 'sanitize_callback' => 'absint' ],
					'entity'   => [ 'sanitize_callback' => 'sanitize_text_field' ],
					'access'   => [ 'sanitize_callback' => 'sanitize_text_field' ],
					'state'    => [ 'sanitize_callback' => 'sanitize_text_field' ],
					'rarity'   => [ 'sanitize_callback' => 'sanitize_text_field' ],
					'signal'   => [ 'sanitize_callback' => 'sanitize_text_field' ],
					'search'   => [ 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);

		register_rest_route(
			self::NS,
			'/identity/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'public_read' ],
				'callback'            => [ $this, 'identity' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);

		register_rest_route(
			self::NS,
			'/incidents',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'public_read' ],
				'callback'            => [ $this, 'incidents' ],
			]
		);

		register_rest_route(
			self::NS,
			'/mint-config',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'public_read' ],
				'callback'            => [ $this, 'mint_config' ],
			]
		);

		register_rest_route(
			self::NS,
			'/terminal',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => [ $this, 'public_interact' ],
				'callback'            => [ $this, 'terminal' ],
				'args'                => [
							'command' => [ 'required' => true, 'type' => 'string', 'maxLength' => 256 ],
				],
			]
		);

		register_rest_route(
			self::NS,
			'/arg/(?P<challenge>[a-z0-9\-]+)/verify',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => [ $this, 'public_interact' ],
				'callback'            => [ $this, 'arg_verify' ],
				'args'                => [
					'challenge' => [ 'sanitize_callback' => 'sanitize_key' ],
					'stage'     => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
					'answer'    => [ 'required' => true, 'type' => 'string', 'maxLength' => 256 ],
				],
			]
		);

		register_rest_route(
			self::NS,
			'/arg/(?P<challenge>[a-z0-9\-]+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'public_read' ],
				'callback'            => [ $this, 'arg_state' ],
				'args'                => [
					'challenge' => [ 'sanitize_callback' => 'sanitize_key' ],
					'stage'     => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
				],
			]
		);

		register_rest_route(
			self::NS,
			'/state/transition',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => [ $this, 'require_admin' ],
				'callback'            => [ $this, 'state_transition' ],
				'args'                => [
					'post_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
					'to'      => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'trigger' => [ 'sanitize_callback' => 'sanitize_text_field' ],
					'force'   => [ 'default' => false ],
				],
			]
		);
	}

	/* ---- Handlers ----------------------------------------------------- */

	public function system(): \WP_REST_Response {
		$c   = Config::all();
		$res = new \WP_REST_Response(
			[
				'system_state'         => Config::system_state(),
				'network'              => 'ROOT',
				'network_status'       => (string) $c['network_status'],
				'identities_recovered' => (int) wp_count_posts( 'ghost_identity' )->publish,
				'supply'               => Vocab::SUPPLY,
				'active_nodes'         => (int) $c['active_nodes'],
				'signal_strength'      => (int) $c['signal_strength'],
				'last_verified_signal' => (string) get_option( 'ghost_root_last_signal', gmdate( 'c', time() - HOUR_IN_SECONDS ) ),
				'rarity_bands'         => Vocab::RARITY_BANDS,
			]
		);
		$res->header( 'Cache-Control', 'public, max-age=60' );
		return $res;
	}

	public function identities( \WP_REST_Request $req ): \WP_REST_Response {
		$per   = min( 48, max( 1, (int) $req['per_page'] ) );
		$page  = max( 1, (int) $req['page'] );
		$meta  = [];

		foreach ( [ 'entity' => 'entity', 'access' => 'access', 'state' => 'state', 'rarity' => 'rarity_band', 'signal' => 'signal' ] as $param => $key ) {
			$val = strtoupper( trim( (string) $req[ $param ] ) );
			if ( '' !== $val ) {
				$meta[] = [ 'key' => $key, 'value' => $val, 'compare' => '=' ];
			}
		}

		$args = [
			'post_type'      => 'ghost_identity',
			'post_status'    => 'publish',
			'posts_per_page' => $per,
			'paged'          => $page,
			'orderby'        => 'meta_value_num',
			'meta_key'       => 'ghost_id',
			'order'          => 'ASC',
			's'              => (string) $req['search'],
		];
		if ( $meta ) {
			$args['meta_query']            = $meta;
			$args['meta_query']['relation'] = 'AND';
			// Re-add the orderby key since meta_query replaced meta_key context.
			$args['meta_query'][]           = [ 'key' => 'ghost_id', 'compare' => 'EXISTS' ];
		}

		$q     = new \WP_Query( $args );
		$items = array_map( [ $this, 'card' ], $q->posts );

		$res = new \WP_REST_Response(
			[
				'items'       => $items,
				'page'        => $page,
				'per_page'    => $per,
				'total'       => (int) $q->found_posts,
				'total_pages' => (int) $q->max_num_pages,
			]
		);
		$res->header( 'Cache-Control', 'public, max-age=120' );
		return $res;
	}

	public function identity( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		$gid = (int) $req['id'];
		$p   = $this->find_identity( $gid );
		if ( ! $p ) {
			return new \WP_Error( 'gr_not_found', 'Identity not recovered.', [ 'status' => 404 ] );
		}
		$data              = $this->card( $p );
		$data['dossier']   = [];
		foreach ( [ 'face', 'eyes', 'mask', 'implant', 'corruption', 'background', 'recovery_node', 'last_signal' ] as $k ) {
			$data['dossier'][ $k ] = (string) get_post_meta( $p->ID, $k, true );
		}
		$data['fingerprint'] = (string) get_post_meta( $p->ID, 'fingerprint', true );
		$data['history']     = StateManager::history( $p->ID );
		$data['content']     = wp_kses_post( wpautop( $p->post_content ) );

		$res = new \WP_REST_Response( $data );
		$res->header( 'Cache-Control', 'public, max-age=120' );
		return $res;
	}

	public function incidents(): \WP_REST_Response {
		$q   = new \WP_Query(
			[
				'post_type'      => 'incident',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);
		$out = [];
		foreach ( $q->posts as $p ) {
			$out[] = [
				'id'       => (string) ( get_post_meta( $p->ID, 'incident_id', true ) ?: $p->post_name ),
				'title'    => get_the_title( $p ),
				'status'   => (string) get_post_meta( $p->ID, 'status', true ),
				'severity' => (string) get_post_meta( $p->ID, 'severity', true ),
				'date'     => (string) get_post_meta( $p->ID, 'incident_date', true ),
				'url'      => get_permalink( $p ),
			];
		}
		$res = new \WP_REST_Response( $out );
		$res->header( 'Cache-Control', 'public, max-age=120' );
		return $res;
	}

	public function mint_config(): \WP_REST_Response {
		$cfg = Config::mint_config();
		if ( empty( $cfg['collection'] ) ) {
			$cfg['notice'] = 'NOT YET DEPLOYED';
		}
		$res = new \WP_REST_Response( $cfg );
		$res->header( 'Cache-Control', 'public, max-age=30' );
		return $res;
	}

	public function terminal( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		if ( ! RateLimiter::allow( 'terminal', 40, 60 ) ) {
			return new \WP_Error( 'gr_rate', 'RATE LIMIT — slow down.', [ 'status' => 429 ] );
		}
		$command = (string) $req['command'];
		if ( strlen( $command ) > Terminal::MAX_INPUT ) {
			return new \WP_Error( 'gr_too_long', 'input rejected: too long', [ 'status' => 400 ] );
		}
		$result = ( new Terminal() )->run( $command );
		return new \WP_REST_Response( $result );
	}

	public function arg_state( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		$view = Challenges::public_view( (string) $req['challenge'], (int) $req['stage'] );
		if ( ! $view || empty( $view['released'] ) ) {
			return new \WP_Error( 'gr_not_found', 'No such challenge.', [ 'status' => 404 ] );
		}
		$response = new \WP_REST_Response($view);
		$response->header('Cache-Control', 'private, no-store');
		$response->header('X-Robots-Tag', 'noindex, nofollow');
		return $response;
	}

	public function arg_verify( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		if ( ! RateLimiter::allow( 'arg-verify', 12, 900 ) ) {
			return new \WP_Error( 'gr_rate', 'Attempt limit reached. Cooldown active.', [ 'status' => 429 ] );
		}
		$out = Verifier::verify( (string) $req['challenge'], (int) $req['stage'], (string) $req['answer'] );
		$status = match($out['status']) { 'not_found' => 404, 'invalid_stage' => 400, 'cooldown' => 429, default => 200 };
		$response = new \WP_REST_Response($out, $status);
		$response->header('Cache-Control', 'private, no-store');
		return $response;
	}

	public function state_transition( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		$ok = StateManager::transition(
			(int) $req['post_id'],
			(string) $req['to'],
			[ 'trigger' => (string) $req['trigger'], 'actor' => 'ADMIN', 'reference' => 'rest' ],
			(bool) $req['force']
		);
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return new \WP_REST_Response( [ 'ok' => true, 'state' => StateManager::current( (int) $req['post_id'] ) ] );
	}

	/* ---- helpers ---------------------------------------------------- */

	private function find_identity( int $gid ): ?\WP_Post {
		$p = get_posts(
			[
				'post_type'     => 'ghost_identity',
				'post_status'   => 'publish',
				'meta_key'      => 'ghost_id',
				'meta_value'    => $gid,
				'numberposts'   => 1,
				'no_found_rows' => true,
			]
		);
		return $p[0] ?? null;
	}

	private function card( \WP_Post $p ): array {
		$gid = (int) get_post_meta( $p->ID, 'ghost_id', true );
		$g   = static fn( $k ) => (string) get_post_meta( $p->ID, $k, true );
		return [
			'ghost_id'    => $gid,
			'title'       => get_the_title( $p ),
			'url'         => get_permalink( $p ),
			'entity'      => $g( 'entity' ),
			'access'      => $g( 'access' ),
			'state'       => $g( 'state' ),
			'signal'      => $g( 'signal' ),
			'rarity_band' => $g( 'rarity_band' ),
			'archetype'   => $g( 'archetype' ),
			'image'       => $this->portrait_src( $p, $gid ),
		];
	}

	private function portrait_src( \WP_Post $p, int $gid ): string {
		if ( has_post_thumbnail( $p ) ) {
			return (string) get_the_post_thumbnail_url( $p, 'large' );
		}
		$uri = (string) get_post_meta( $p->ID, 'image_uri', true );
		if ( $uri && ! str_contains( $uri, 'EXAMPLE' ) && str_starts_with( $uri, 'http' ) ) {
			return $uri;
		}
		return Portrait::data_uri(
			[
				'ghost_id'    => $gid,
				'fingerprint' => get_post_meta( $p->ID, 'fingerprint', true ),
				'entity'      => get_post_meta( $p->ID, 'entity', true ),
				'face'        => get_post_meta( $p->ID, 'face', true ),
				'eyes'        => get_post_meta( $p->ID, 'eyes', true ),
				'corruption'  => get_post_meta( $p->ID, 'corruption', true ),
				'access'      => get_post_meta( $p->ID, 'access', true ),
				'state'       => get_post_meta( $p->ID, 'state', true ),
			],
			480
		);
	}
}
