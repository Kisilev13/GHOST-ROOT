<?php
/**
 * Simulated terminal. Pure application logic over an explicit command allowlist.
 *
 * SECURITY MODEL
 *  - Input is matched against a fixed allowlist. Unknown input returns "command not found".
 *  - Input NEVER reaches exec/shell_exec/system/passthru/proc_open/popen, SQL, the
 *    filesystem, or include/require. Numeric arguments are cast with absint().
 *  - Output is plain text; the frontend renders it as textContent, not HTML.
 *  - Rate limited + length capped by the REST layer.
 *
 * @package GhostRoot
 */

namespace GhostRoot\Terminal;

use GhostRoot\Settings\Config;
use GhostRoot\Support\Vocab;

defined( 'ABSPATH' ) || exit;

final class Terminal {

	public const MAX_INPUT = 256;

	/**
	 * Run a command string. Returns an array of output lines.
	 *
	 * @return array{lines:string[],clear:bool}
	 */
	public function run( string $raw ): array {
		$raw = trim( $raw );
		if ( strlen( $raw ) > self::MAX_INPUT ) {
			return $this->out( [ 'input rejected: exceeds ' . self::MAX_INPUT . ' characters' ] );
		}

		// Normalise whitespace; split head + optional numeric arg only.
		$parts = preg_split( '/\s+/', strtolower( $raw ), 2 );
		$cmd   = $parts[0] ?? '';
		$arg   = isset( $parts[1] ) ? trim( $parts[1] ) : '';
		if ($arg !== '' && (!in_array($cmd, ['identity','incident'], true) || !preg_match('/^[0-9]{1,4}$/D', $arg))) {
			return $this->out(['input rejected: invalid command arguments']);
		}

		switch ( $cmd ) {
			case '':
				return $this->out( [] );
			case 'help':
				return $this->out( $this->help() );
			case 'clear':
				return [ 'lines' => [], 'clear' => true ];
			case 'whoami':
				return $this->out( [ 'guest@ghost-root', 'access: USER', 'you were never supposed to be here.' ] );
			case 'status':
				return $this->out( $this->status() );
			case 'scan':
				return $this->out( $this->scan() );
			case 'nodes':
				return $this->out( $this->nodes() );
			case 'signal':
				return $this->out( $this->signal() );
			case 'archive':
				return $this->out( [ 'ARCHIVE INDEX', str_repeat( '-', 32 ), 'classified records: ' . wp_count_posts( 'archive_record' )->publish, 'open /archive/ for the full index.' ] );
			case 'protocol':
				return $this->out( $this->protocol() );
			case 'root':
				return $this->out( [ 'ROOT ACCESS DENIED', 'this incident has been logged.', 'SOURCE: [REDACTED]' ] );
			case 'incident':
				return $this->out( $this->incident( absint( $arg ) ) );
			case 'identity':
				return $this->out( $this->identity( absint( $arg ) ) );
			default:
				return $this->out( [ $cmd . ': command not found', "type 'help' for the command list" ] );
		}
	}

	/**
	 * @param string[] $lines
	 * @return array{lines:string[],clear:bool}
	 */
	private function out( array $lines ): array {
		return [ 'lines' => array_map( 'strval', $lines ), 'clear' => false ];
	}

	/** @return string[] */
	private function help(): array {
		return [
			'AVAILABLE COMMANDS',
			str_repeat( '-', 32 ),
			'help              this list',
			'status            network + system state',
			'whoami            current session',
			'scan              probe recovered nodes',
			'nodes             list responding nodes',
			'signal            signal telemetry',
			'incident <n>      open incident record',
			'identity <n>      open recovered identity',
			'archive           archive index',
			'protocol          collection protocol',
			'root              attempt privilege escalation',
			'clear             clear the screen',
		];
	}

	/** @return string[] */
	private function status(): array {
		$c     = Config::all();
		$count = (int) wp_count_posts( 'ghost_identity' )->publish;
		return [
			'GHOST//ROOT SYSTEM STATUS',
			str_repeat( '-', 32 ),
			'network:              ROOT',
			'system state:         ' . Config::system_state(),
			'network status:       ' . strtoupper( (string) $c['network_status'] ),
			'identities recovered: ' . $count . ' / ' . Vocab::SUPPLY,
			'active nodes:         ' . (int) $c['active_nodes'],
			'signal strength:      ' . (int) $c['signal_strength'] . '%',
			'mint state:           ' . strtoupper( (string) $c['mint_state'] ),
		];
	}

	/** @return string[] */
	private function scan(): array {
		$nodes = min( 8, max( 1, (int) Config::get( 'active_nodes' ) ) );
		$lines = [ 'SCANNING RECOVERED SUBNET...', '' ];
		for ( $i = 1; $i <= $nodes; $i++ ) {
			$n       = str_pad( (string) $i, 2, '0', STR_PAD_LEFT );
			$lat     = 40 + ($i * 137) % 861;
			$lines[] = sprintf( 'NODE-%s   %s   %dms', $n, $lat > 600 ? 'DEGRADED' : 'RESPONDING', $lat );
		}
		$lines[] = '';
		$lines[] = 'simulated scan complete. ' . $nodes . ' narrative nodes responded.';
		return $lines;
	}

	/** @return string[] */
	private function nodes(): array {
		$active = (int) Config::get( 'active_nodes' );
		$lines  = [ 'RESPONDING NODES', str_repeat( '-', 32 ) ];
		for ( $i = 1; $i <= min( 12, $active ); $i++ ) {
			$lines[] = 'NODE-' . str_pad( (string) $i, 2, '0', STR_PAD_LEFT );
		}
		$lines[] = '';
		$lines[] = 'NODE-31 IS RESPONDING.';
		$lines[] = 'THIS SHOULD NOT BE POSSIBLE.';
		return $lines;
	}

	/** @return string[] */
	private function signal(): array {
		$s = (int) Config::get( 'signal_strength' );
		return [
			'SIGNAL TELEMETRY',
			str_repeat( '-', 32 ),
			'strength:   ' . $s . '%',
			'condition:  ' . ( $s > 70 ? 'DEGRADED' : ( $s > 40 ? 'CORRUPTED' : 'LOST' ) ),
			'last verified: ' . get_option( 'ghost_root_last_signal', gmdate( 'Y-m-d H:i', time() - HOUR_IN_SECONDS ) ) . ' UTC',
		];
	}

	/** @return string[] */
	private function protocol(): array {
		$lines = [ 'COLLECTION PROTOCOL', str_repeat( '-', 32 ), 'total identities: ' . Vocab::SUPPLY ];
		foreach ( Vocab::RARITY_BANDS as $band => $n ) {
			$lines[] = str_pad( $band, 12 ) . $n;
		}
		$lines[] = '';
		$lines[] = 'network: SOLANA   standard: METAPLEX CORE   mint: CORE CANDY MACHINE';
		$lines[] = 'open /protocol/ for the full document.';
		return $lines;
	}

	/**
	 * @return string[]
	 */
	private function incident( int $n ): array {
		if ( $n < 1 ) {
			return [ 'usage: incident <number>' ];
		}
		$q = new \WP_Query(
			[
				'post_type'      => 'incident',
				'name'           => (string) $n,
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			]
		);
		if ( ! $q->have_posts() ) {
			$q = new \WP_Query(
				[
					'post_type'      => 'incident',
					'meta_key'       => 'incident_id',
					'meta_value'     => str_pad( (string) $n, 3, '0', STR_PAD_LEFT ),
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				]
			);
		}
		if ( ! $q->have_posts() ) {
			return [ 'INCIDENT // ' . str_pad( (string) $n, 3, '0', STR_PAD_LEFT ), 'NO SIGNAL' ];
		}
		$p = $q->posts[0];
		return [
			'INCIDENT // ' . esc_html( (string) ( get_post_meta( $p->ID, 'incident_id', true ) ?: $n ) ),
			str_repeat( '-', 32 ),
			strtoupper( get_the_title( $p ) ),
			'',
			'STATUS: ' . strtoupper( (string) get_post_meta( $p->ID, 'status', true ) ),
			'SEVERITY: ' . strtoupper( (string) get_post_meta( $p->ID, 'severity', true ) ),
			'',
			wp_trim_words( wp_strip_all_tags( $p->post_content ), 60 ),
			'',
			'full record: ' . wp_make_link_relative( get_permalink( $p ) ),
		];
	}

	/**
	 * @return string[]
	 */
	private function identity( int $n ): array {
		if ( $n < 1 || $n > Vocab::SUPPLY ) {
			return [ 'usage: identity <1-' . Vocab::SUPPLY . '>' ];
		}
		$q = new \WP_Query(
			[
				'post_type'      => 'ghost_identity',
				'meta_key'       => 'ghost_id',
				'meta_value'     => $n,
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			]
		);
		if ( ! $q->have_posts() ) {
			return [ 'GHOST//' . str_pad( (string) $n, 4, '0', STR_PAD_LEFT ), 'NO SIGNAL — identity not recovered on this node.' ];
		}
		$p = $q->posts[0];
		$g = static fn( $k ) => strtoupper( (string) get_post_meta( $p->ID, $k, true ) );
		return [
			'GHOST//' . str_pad( (string) $n, 4, '0', STR_PAD_LEFT ),
			str_repeat( '-', 32 ),
			'ENTITY:      ' . $g( 'entity' ),
			'ACCESS:      ' . $g( 'access' ),
			'STATE:       ' . $g( 'state' ),
			'SIGNAL:      ' . $g( 'signal' ),
			'CORRUPTION:  ' . $g( 'corruption' ),
			'FINGERPRINT: 0x' . $g( 'fingerprint' ),
			'',
			'dossier: ' . wp_make_link_relative( get_permalink( $p ) ),
		];
	}
}
