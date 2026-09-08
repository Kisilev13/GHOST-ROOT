<?php
/**
 * Deterministic procedural "forensic portrait" SVG generator.
 *
 * There is no rendered collection art yet. Until real portraits exist, each identity
 * gets a stable, trait-derived SVG so the dossier and grid read as a collection of
 * distinct recovered entities rather than one repeated mannequin.
 *
 * The construction language (a chrome-reconstructed face on a near-black forensic
 * ground, wireframe topology, sensor pads, diagnostic annotation) is fixed. Every
 * proportion and feature is then driven by identity metadata — entity, face, eyes,
 * mask, implant, corruption, access, background, archetype, rarity band, signal,
 * state — plus a PRNG seeded purely from ghost_id + fingerprint + the structural
 * traits, so output never changes between requests. Templates prefer a real
 * featured image / image_uri when one is set.
 *
 * @package GhostRoot
 */

namespace GhostRoot\Metadata;

defined( 'ABSPATH' ) || exit;

final class Portrait {

	/** Hard ceiling on generated markup; decorative layers are dropped past this. */
	private const SIZE_BUDGET = 15500;

	/**
	 * Build an inline SVG string for an identity.
	 *
	 * @param array<string,string> $traits ghost_id, fingerprint, entity, face, eyes, mask,
	 *                                     implant, corruption, access, background, archetype,
	 *                                     rarity_band, signal, state.
	 */
	public static function svg( array $traits, int $size = 640 ): string {
		$size = min( 1280, max( 128, $size ) );
		$t    = self::normalize( $traits );
		$seed = self::seed( $traits );
		$rng  = self::prng( $seed );
		$uid  = 'p' . substr( $seed, 0, 10 );

		$r      = static fn( float $a = 0.0, float $b = 1.0 ): float => $a + ( $b - $a ) * $rng();
		$ri     = static fn( int $a, int $b ): int => (int) floor( $a + ( $b - $a + 1 ) * $rng() );
		$chance = static fn( float $p ): bool => $rng() < $p;
		$pick   = static fn( array $o ) => $o[ (int) floor( $rng() * count( $o ) ) % max( 1, count( $o ) ) ];

		/* ---- Archetype composition bias --------------------------------- */
		$arch = $t['archetype'];
		$bias = static function ( string $k ) use ( $arch ): float {
			static $map = [
				'THE OBSERVER'     => [ 'eyes' => 0.5 ],
				'THE FRACTURE'     => [ 'split' => 0.8, 'reconstruct' => 0.6 ],
				'THE HOLLOW'       => [ 'knockout' => 0.7 ],
				'THE BREACH'       => [ 'burn' => 0.6, 'glitch' => 0.4 ],
				'THE SIGNAL'       => [ 'antenna' => 0.6 ],
				'THE OVERMIND'     => [ 'mesh' => 0.6, 'antenna' => 0.4 ],
				'THE CROWN'        => [ 'crown' => 0.85 ],
				'THE TRANSCENDENT' => [ 'halo' => 0.7, 'clean' => 0.6 ],
				'THE ARCHIVIST'    => [ 'annotate' => 0.7 ],
				'THE NULL'         => [ 'null' => 0.8, 'clean' => 0.3 ],
				'THE WITNESS'      => [ 'multieye' => 0.75 ],
				'THE ROOT'         => [ 'port' => 0.7, 'gold' => 0.6 ],
			];
			return (float) ( $map[ $arch ][ $k ] ?? 0.0 );
		};

		/* ---- Palette + rarity treatment ------------------------------- */
		$access  = $t['access'];
		$band    = $t['rarity_band'];
		$corrupt = 'CORRUPTED' === $band || in_array( $t['corruption'], [ 'FRAGMENTATION', 'BURN', 'MEMORY BLEED', 'GLITCH', 'SIGNAL LOSS' ], true )
					|| 'THE BREACH' === $arch;
		$goldRar = 'ADMIN' === $band || 'GENESIS' === $band || 'ROOT' === $band || 'ROOT' === $access
					|| $bias( 'gold' ) > 0 || in_array( $access, [ 'ADMIN', 'SYSTEM' ], true );

		$accent = $goldRar ? '#c8ac6c' : '#a6aaa2';
		$eyeCol = in_array( $t['eyes'], [ 'THERMAL', 'SIGNAL-BURN' ], true ) || $corrupt ? '#c04f4f' : $accent;
		$crack  = $corrupt && 'ADMIN' !== $band ? '#b24a44' : '#c9a253';
		$rim    = $corrupt && 'ADMIN' !== $band ? '#c04f4f' : '';

		$g0 = 'ROOTED' === $t['state'] ? '#22151a' : ( $corrupt ? '#1c1a19' : '#212420' );
		$g1 = 'ROOTED' === $t['state'] ? '#0a0506' : '#060707';

		$mat = self::material( $t['face'] );
		if ( 'ADMIN' === $band ) {
			$mat = self::goldPlate( $mat );
		}
		$lightL = $r() < 0.5;
		$haloOn = 'GENESIS' === $band || 'ROOT' === $band || $bias( 'halo' ) > 0 || 'THE TRANSCENDENT' === $arch;
		$veilOn = 'THE TRANSCENDENT' === $arch || 'SPECTER' === $t['entity'] || $r() < 0.05;

		$crackI = 0;
		if ( in_array( $t['face'], [ 'PORCELAIN', 'CERAMIC', 'RECONSTRUCTED' ], true ) ) {
			$crackI = 2;
		}
		if ( 'FRAGMENTATION' === $t['corruption'] || $bias( 'split' ) > 0.4 || $bias( 'reconstruct' ) > 0 ) {
			$crackI += 1;
		}
		if ( 'GENESIS' === $band ) {
			$crackI += 1;
		}
		if ( 0 === $crackI && $r() < 0.28 ) {
			$crackI = 1;
		}
		$crackI = min( 4, $crackI );

		$tendrilN = 0;
		if ( 'NEURAL CABLE' === $t['implant'] ) {
			$tendrilN += $ri( 4, 7 );
		}
		if ( in_array( $arch, [ 'THE BREACH', 'THE SIGNAL', 'THE OVERMIND' ], true ) ) {
			$tendrilN += $ri( 2, 4 );
		}
		if ( in_array( $t['corruption'], [ 'GLITCH', 'PACKET GHOSTING', 'SIGNAL LOSS' ], true ) ) {
			$tendrilN += $ri( 1, 3 );
		}
		if ( 0 === $tendrilN && $r() < 0.3 ) {
			$tendrilN = $ri( 1, 2 );
		}
		$tendrilN = min( 12, $tendrilN );

		/* ---- Face proportions (seeded deltas around the base rig) ------ */
		$hx   = 170 + $r( -12, 22 );          // temple x  (base 170)
		$chx  = 283 + $r( -24, 22 );          // chin-corner x (base 283)
		$yCr  = 109 + $r( -8, 8 );            // crown y
		$yWd  = 292 + $r( -10, 10 );          // widest y
		$yJw  = 370 + $r( -12, 16 );          // jaw-corner y
		$yCh  = 466 + $r( -14, 20 );          // chin y
		$asym = $r( -11, 11 );                // left-side x offset
		$skew = $r( -0.045, 0.045 );          // gentle 3/4 shear
		$zoom = $r( 0.9, 1.08 );
		$py   = $r( -14, 16 );

		$Y    = [ 'cr' => $yCr, 'mid' => 181 + $r( -10, 10 ), 'wd' => $yWd, 'jw' => $yJw, 'ch' => $yCh ];
		$face = self::facePath( $hx, $chx, $asym, $Y );

		// feature anchors, scaled to the rig
		$eyeY   = $yWd - $r( 30, 42 );
		$eyeGap = ( 76 + $r( -12, 16 ) ) * ( 1 + $bias( 'eyes' ) * 0.18 );
		$eyeSz  = $r( 0.85, 1.2 ) * ( 1 + $bias( 'eyes' ) * 0.25 );
		$noseTop = $eyeY - $r( 18, 30 );
		$noseBot = $eyeY + $r( 70, 100 );
		$noseW   = $r( 24, 40 );
		$mouthY  = ( $yJw + $yCh ) / 2 - $r( 2, 14 );
		$mouthW  = $r( 34, 52 );

		/* ---- defs ---------------------------------------------------- */
		$defs = '<defs>'
			. '<radialGradient id="' . $uid . 'bg" cx="' . ( $lightL ? '.34' : '.66' ) . '" cy=".42" r=".95">'
				. '<stop stop-color="' . $g0 . '"/><stop offset=".6" stop-color="#0e100e"/><stop offset="1" stop-color="' . $g1 . '"/></radialGradient>'
			. '<linearGradient id="' . $uid . 'fc" x1="' . ( $lightL ? 0 : 1 ) . '" x2="' . ( $lightL ? 1 : 0 ) . '" y1=".12" y2=".82">'
				. '<stop stop-color="' . $mat[0] . '"/><stop offset=".26" stop-color="' . $mat[1] . '"/><stop offset=".44" stop-color="' . $mat[2] . '"/><stop offset=".6" stop-color="' . $mat[1] . '"/><stop offset=".82" stop-color="' . $mat[3] . '"/><stop offset="1" stop-color="#050807"/></linearGradient>'
			. '<linearGradient id="' . $uid . 'nk" x1="0" x2="0" y1="0" y2="1"><stop stop-color="#101512"/><stop offset=".42" stop-color="' . $mat[1] . '"/><stop offset=".72" stop-color="#1b231d"/><stop offset="1" stop-color="#080c09"/></linearGradient>'
			. '<radialGradient id="' . $uid . 'lt"><stop stop-color="#bcc3ae" stop-opacity="' . self::nn( $r( 0.1, 0.19 ) ) . '"/><stop offset="1" stop-color="#555" stop-opacity="0"/></radialGradient>'
			. ( $rim ? '<radialGradient id="' . $uid . 'rm" cx=".5" cy=".52" r=".6"><stop offset=".82" stop-color="' . $rim . '" stop-opacity="0"/><stop offset="1" stop-color="' . $rim . '" stop-opacity=".42"/></radialGradient>' : '' )
			. '<clipPath id="' . $uid . 'cl"><path d="' . $face . '"/></clipPath>'
			. '<pattern id="' . $uid . 'ht" width="6" height="6" patternTransform="rotate(' . $ri( 22, 66 ) . ')" patternUnits="userSpaceOnUse"><line x1="0" y1="0" x2="0" y2="6" stroke="#aab29b" stroke-width="1" stroke-opacity=".55"/></pattern>'
			. '</defs>';

		/* ---- Ground + backdrop ------------------------------------- */
		$out  = '<rect width="640" height="640" fill="url(#' . $uid . 'bg)"/>';
		$out .= self::backdrop( $t['background'], $ri, $r );
		$out .= self::halo( $haloOn, $accent, $Y );

		/* ---- Head group (pose transform) -------------------------- */
		$tf  = 'matrix(' . self::nn( $zoom ) . ',0,' . self::nn( $skew ) . ',' . self::nn( $zoom )
			. ',' . self::nn( 320 - 320 * $zoom - $skew * 300 ) . ',' . self::nn( 300 - 300 * $zoom + $py ) . ')';
		$h   = '<g transform="' . $tf . '">';

		// cable tendrils behind the head
		if ( $tendrilN > 0 ) {
			$h .= self::tendrils( $chx, $yJw, $yCh, (int) ceil( $tendrilN * 0.7 ), $r, $ri, true );
		}
		// veil drape behind
		if ( $veilOn ) {
			$h .= self::veil( $hx, $Y, true );
		}
		// neck + shoulders
		$h .= self::neck( $uid, $chx, $yJw, $r );
		// sensor pads on the temples
		$h .= self::pads( $hx, $yWd, $mat, $accent, $r );
		// face plane
		$h .= '<path d="' . $face . '" fill="url(#' . $uid . 'fc)" stroke="#7c8974" stroke-width="1.2"/>';

		// clipped interior: topology + material detail + key light
		$h .= '<g clip-path="url(#' . $uid . 'cl)">';
		$h .= self::topology( $hx, $chx, $Y, $ri, $r, $bias( 'mesh' ) );
		$h .= self::materialDetail( $t['face'], $Y, $r, $ri );
		$h .= '<ellipse cx="' . ( $lightL ? 250 : 390 ) . '" cy="' . self::nn( $Y['mid'] + 60 ) . '" rx="180" ry="250" fill="url(#' . $uid . 'lt)"/>';
		$h .= '</g>';

		// planar shading (cheek hollows, temple planes) — echoes the original
		$h .= self::planes( $hx, $chx, $Y, $eyeY );

		// brow ridge
		$h .= '<path d="M' . self::nn( 320 - $eyeGap - 22 ) . ' ' . self::nn( $eyeY - 14 ) . 'Q320 ' . self::nn( $eyeY - 22 - $r( 0, 8 ) ) . ' ' . self::nn( 320 + $eyeGap + 22 ) . ' ' . self::nn( $eyeY - 14 ) . '" fill="none" stroke="#9aa389" stroke-width="1.6" opacity=".6"/>';

		// eyes
		$h .= self::eyes( $t['eyes'], $eyeGap, $eyeY, $eyeSz, $eyeCol, $ri, $r, $bias );
		// nose
		$h .= self::nose( $noseTop, $noseBot, $noseW, $r );
		// mouth / lower face
		$h .= self::mouth( $t['mask'], $mouthY, $mouthW, $yCh );
		// mask
		$h .= self::mask( $t['mask'], $uid, $hx, $chx, $Y, $eyeY, $mouthY, $mat, $accent, $r, $ri );
		// implant
		$h .= self::implant( $t['implant'], $hx, $yCr, $eyeY, $yJw, $accent, $r, $ri, $bias );
		// forehead / chin contour highlights
		$h .= '<path d="M' . self::nn( 320 - $eyeGap ) . ' ' . self::nn( $Y['mid'] + 6 ) . 'Q320 ' . self::nn( $Y['mid'] - 6 ) . ' ' . self::nn( 320 + $eyeGap ) . ' ' . self::nn( $Y['mid'] + 6 ) . 'M' . self::nn( 320 - $mouthW ) . ' ' . self::nn( $yCh - 44 ) . 'Q320 ' . self::nn( $yCh - 30 ) . ' ' . self::nn( 320 + $mouthW ) . ' ' . self::nn( $yCh - 44 ) . '" fill="none" stroke="#c6ceb6" stroke-opacity=".5"/>';

		// reconstruction knockout
		$h .= self::knockout( $uid, $t['face'], $Y, $r, $ri, $bias );
		// kintsugi fracture network (gold, or red when corrupted)
		if ( $crackI > 0 ) {
			$h .= self::kintsugi( $uid, $Y, $crack, $crackI, $r, $ri );
		}
		// corruption
		$h .= self::corruption( $t['corruption'], $uid, $face, $accent, $Y, $r, $ri, $bias );
		// red signal rim-light for corrupted identities — a thin edge glow, not a wash
		if ( $rim ) {
			$h .= '<g clip-path="url(#' . $uid . 'cl)"><path d="' . $face . '" fill="url(#' . $uid . 'rm)"/>'
				. '<path d="' . $face . '" fill="none" stroke="' . $rim . '" stroke-width="2" opacity=".35"/></g>';
		}
		// rarity mark
		$h .= self::rarityMark( $t['rarity_band'], $Y, $accent );

		// veil + front tendrils over the head
		if ( $veilOn ) {
			$h .= self::veil( $hx, $Y, false );
		}
		if ( $tendrilN > 0 ) {
			$h .= self::tendrils( $chx, $yJw, $yCh, (int) floor( $tendrilN * 0.4 ) + 1, $r, $ri, false );
		}
		$h .= '</g>'; // close pose group

		/* ---- Diagnostic overlay (screen space) -------------------- */
		$ov = self::overlay( $t, $seed, $accent, $r, $ri, $chance, $bias, false );

		$open = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="' . $size . '" height="' . $size
			. '" role="img" aria-label="' . esc_attr( self::alt_text( $traits ) ) . '">';
		$full = $open . $defs . $out . $h . $ov . '</svg>';
		if ( strlen( $full ) > self::SIZE_BUDGET ) {
			$full = $open . $defs . $out . $h . self::overlay( $t, $seed, $accent, $r, $ri, $chance, $bias, true ) . '</svg>';
		}
		return $full;
	}

	/** Data-URI form for use in <img src>. Cached — output is a pure function of the traits. */
	public static function data_uri( array $traits, int $size = 640 ): string {
		$ver = defined( 'GHOST_ROOT_VERSION' ) ? GHOST_ROOT_VERSION : '0';
		$key = 'grp_' . substr( md5( $ver . '|' . self::seed( $traits ) . '|' . (int) $size ), 0, 26 );
		if ( function_exists( 'get_transient' ) ) {
			$hit = get_transient( $key );
			if ( is_string( $hit ) && '' !== $hit ) {
				return $hit;
			}
		}
		$uri = 'data:image/svg+xml;base64,' . base64_encode( self::svg( $traits, $size ) );
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $key, $uri, defined( 'WEEK_IN_SECONDS' ) ? WEEK_IN_SECONDS : 604800 );
		}
		return $uri;
	}

	public static function alt_text( array $traits ): string {
		$id = str_pad( (string) ( $traits['ghost_id'] ?? 0 ), 4, '0', STR_PAD_LEFT );
		return sprintf(
			'Forensic portrait reconstruction for GHOST//%s — %s entity, %s access, %s state, %s corruption.',
			$id,
			$traits['entity'] ?? 'UNKNOWN',
			$traits['access'] ?? 'UNKNOWN',
			$traits['state'] ?? 'UNKNOWN',
			$traits['corruption'] ?? 'NONE'
		);
	}

	/* ================================================================ */

	private static function seed( array $traits ): string {
		return md5(
			wp_json_encode(
				[
					$traits['ghost_id'] ?? 0,
					$traits['fingerprint'] ?? '',
					$traits['entity'] ?? '',
					$traits['face'] ?? '',
					$traits['eyes'] ?? '',
					$traits['mask'] ?? '',
					$traits['implant'] ?? '',
					$traits['corruption'] ?? '',
					$traits['access'] ?? '',
					$traits['background'] ?? '',
					$traits['archetype'] ?? '',
					$traits['rarity_band'] ?? '',
				]
			)
		);
	}

	/** @return array<string,string> */
	private static function normalize( array $traits ): array {
		$u = static fn( $v ) => strtoupper( trim( (string) $v ) );
		return [
			'ghost_id'    => (string) ( $traits['ghost_id'] ?? 0 ),
			'entity'      => $u( $traits['entity'] ?? 'SYNTHETIC' ),
			'face'        => $u( $traits['face'] ?? 'PORCELAIN' ),
			'eyes'        => $u( $traits['eyes'] ?? 'VOID' ),
			'mask'        => $u( $traits['mask'] ?? 'NONE' ),
			'implant'     => $u( $traits['implant'] ?? 'NEURAL CABLE' ),
			'corruption'  => $u( $traits['corruption'] ?? 'NONE' ),
			'access'      => $u( $traits['access'] ?? 'USER' ),
			'background'  => $u( $traits['background'] ?? 'SERVER' ),
			'archetype'   => $u( $traits['archetype'] ?? '' ),
			'rarity_band' => $u( $traits['rarity_band'] ?? 'STANDARD' ),
			'signal'      => $u( $traits['signal'] ?? 'UNKNOWN' ),
			'state'       => $u( $traits['state'] ?? 'DORMANT' ),
		];
	}

	private static function prng( string $seed ): callable {
		$c = 0;
		return static function () use ( $seed, &$c ): float {
			return hexdec( substr( hash( 'sha256', $seed . ':' . $c++ ), 0, 8 ) ) / 4294967296;
		};
	}

	private static function nn( float $v, int $p = 1 ): string {
		$s = number_format( $v, $p, '.', '' );
		return str_contains( $s, '.' ) ? rtrim( rtrim( $s, '0' ), '.' ) : $s;
	}

	/**
	 * The reconstruction-rig face outline. Base numbers match the original portrait;
	 * $hx (temple x), $chx (chin-corner x), per-station $Y and $aL (left asymmetry)
	 * are seeded.
	 *
	 * @param array<string,float> $Y
	 */
	private static function facePath( float $hx, float $chx, float $aL, array $Y ): string {
		$n   = [ self::class, 'nn' ];
		$hxL = $hx + $aL;
		$chL = $chx + $aL * 0.6;
		return 'M320 ' . $n( $Y['cr'] )
			. 'C' . $n( $hxL + 13 ) . ' ' . $n( $Y['cr'] - 5 ) . ' ' . $n( $hxL - 25 ) . ' ' . $n( $Y['mid'] ) . ' ' . $n( $hxL ) . ' ' . $n( $Y['wd'] )
			. 'L' . $n( $hxL + 22 ) . ' ' . $n( $Y['jw'] )
			. 'Q' . $n( $hxL + 60 ) . ' ' . $n( $Y['jw'] + 68 ) . ' ' . $n( $chL ) . ' ' . $n( $Y['ch'] )
			. 'L' . $n( 640 - $chx ) . ' ' . $n( $Y['ch'] )
			. 'Q' . $n( 580 - $hx ) . ' ' . $n( $Y['jw'] + 68 ) . ' ' . $n( 618 - $hx ) . ' ' . $n( $Y['jw'] )
			. 'L' . $n( 640 - $hx ) . ' ' . $n( $Y['wd'] )
			. 'C' . $n( 665 - $hx ) . ' ' . $n( $Y['mid'] ) . ' ' . $n( 627 - $hx ) . ' ' . $n( $Y['cr'] - 5 ) . ' 320 ' . $n( $Y['cr'] )
			. 'Z';
	}

	/** @return array{0:string,1:string,2:string,3:string} face gradient stops */
	private static function material( string $face ): array {
		switch ( $face ) {
			case 'CHROME':
				return [ '#2a2f34', '#8b939c', '#eef0ec', '#171b21' ];
			case 'CARBON':
				return [ '#171916', '#464b45', '#606559', '#0c0e0c' ];
			case 'RECONSTRUCTED':
				return [ '#252821', '#7a8072', '#c6c8b8', '#12150f' ];
			case 'CERAMIC':
				return [ '#26261f', '#8f8d82', '#dedbcd', '#141310' ];
			case 'BIO-SYNTH':
				return [ '#1c211550', '#727a5b', '#bcc298', '#0d1109' ];
			default: // PORCELAIN
				return [ '#262622', '#8c8a80', '#dbd9cb', '#141310' ];
		}
	}

	private static function backdrop( string $bg, callable $ri, callable $r ): string {
		$o = '<g fill="none" stroke="#586050" opacity=".12"><circle cx="320" cy="286" r="248"/><circle cx="320" cy="286" r="' . $ri( 205, 232 ) . '"/><path d="M30 286H610M320 20V612"/></g>';
		$o .= '<g fill="none" stroke="#4c5347" opacity=".2">';
		switch ( $bg ) {
			case 'SATELLITE':
				$o .= '<path d="M40 470Q320 350 600 470"/><circle cx="500" cy="120" r="26"/>';
				break;
			case 'SIGNAL CHAMBER':
				$o .= '<circle cx="320" cy="286" r="150"/><circle cx="320" cy="286" r="98"/>';
				break;
			case 'COLD STORAGE':
				$o .= '<path d="M46 74h34v34M594 74h-34v34M46 566h34v-34M594 566h-34v-34"/>';
				break;
			case 'ARCHIVE':
				$o .= '<path d="M40 150h560M40 330h560M40 510h560"/>';
				break;
			case 'BLACKSITE':
				for ( $i = 0; $i < 6; $i++ ) {
					$o .= '<path d="M0 ' . ( 60 + $i * 96 ) . 'h640" opacity=".55"/>';
				}
				break;
			case 'VOID':
				$o .= '<circle cx="320" cy="290" r="232" opacity=".5"/>';
				break;
			default: // SERVER
				$o .= '<path d="M64 96v452M120 96v452M520 96v452M576 96v452M64 210h56M520 350h56"/>';
		}
		$o .= '</g>';
		$o .= '<g stroke="#a5a895" opacity=".3" fill="none"><path d="M80 80h25m-25 0v25m430 0h25v-25M80 550v25h25m430 0h25v-25"/></g>';
		return $o;
	}

	/** @param array<string,float> $Y */
	private static function halo( bool $on, string $accent, array $Y ): string {
		if ( ! $on ) {
			return '';
		}
		$cy = $Y['cr'] + 120;
		return '<circle cx="320" cy="' . self::nn( $cy ) . '" r="164" fill="none" stroke="' . $accent . '" stroke-width="2.4" opacity=".5"/>'
			. '<circle cx="320" cy="' . self::nn( $cy ) . '" r="164" fill="none" stroke="#f2e8cc" stroke-width=".7" opacity=".55"/>';
	}

	/** Blend a face material toward gold plating (ADMIN). @param array{0:string,1:string,2:string,3:string} $mat */
	private static function goldPlate( array $mat ): array {
		return [ '#22201a', '#8a7a4e', '#e6d19a', '#161208' ];
	}

	/**
	 * Kintsugi fracture network — the signature cracked-mask motif. A few branching
	 * seams down the face: dark relief underneath, metal fill, bright highlight.
	 *
	 * @param array<string,float> $Y
	 */
	private static function kintsugi( string $uid, array $Y, string $col, int $intensity, callable $r, callable $ri ): string {
		// one primary fracture with fine hairline branches — porcelain, not scaffolding
		$primary = '';
		$fine    = '';
		$x       = 320 + $r( -40, 40 );
		$y       = $Y['cr'] + $r( 2, 26 );
		$dir     = $r( -0.3, 0.3 );
		$nodes   = [];
		$steps   = $ri( 6, 10 );
		$primary = 'M' . self::nn( $x ) . ' ' . self::nn( $y );
		for ( $s = 0; $s < $steps; $s++ ) {
			$len  = $r( 24, 46 );
			$dir += $r( -0.5, 0.5 );
			$x   += sin( $dir ) * $len * 0.55 + $r( -6, 6 );
			$y   += $len;
			$primary .= 'L' . self::nn( $x ) . ' ' . self::nn( $y );
			$nodes[]  = [ $x, $y, $dir ];
			if ( $y > $Y['ch'] - 10 ) {
				break;
			}
		}
		$branches = $ri( $intensity, $intensity + 2 );
		for ( $b = 0; $b < $branches && $nodes; $b++ ) {
			[ $bx, $by, $bd ] = $nodes[ (int) floor( $r( 0, count( $nodes ) - 0.01 ) ) ];
			$bd += ( $r() < 0.5 ? -1 : 1 ) * $r( 0.5, 1.2 );
			$p   = 'M' . self::nn( $bx ) . ' ' . self::nn( $by );
			for ( $s = 0; $s < $ri( 2, 4 ); $s++ ) {
				$len = $r( 16, 34 );
				$bd += $r( -0.5, 0.5 );
				$bx += sin( $bd ) * $len;
				$by += cos( $bd ) * $len * 0.7 + $len * 0.3;
				$p  .= 'L' . self::nn( $bx ) . ' ' . self::nn( $by );
			}
			$fine .= $p;
		}
		return '<g clip-path="url(#' . $uid . 'cl)" fill="none" stroke-linecap="round" stroke-linejoin="round">'
			. '<path d="' . $primary . '" stroke="#0a0805" stroke-width="3.4"/>'
			. '<path d="' . $primary . '" stroke="' . $col . '" stroke-width="1.5"/>'
			. '<path d="' . $primary . '" stroke="#f2e6be" stroke-width=".5" opacity=".6"/>'
			. ( '' !== $fine ? '<path d="' . $fine . '" stroke="#0a0805" stroke-width="1.8"/><path d="' . $fine . '" stroke="' . $col . '" stroke-width=".8" opacity=".9"/>' : '' )
			. '</g>';
	}

	/** Wet-black cable tendrils flowing off the head/neck. */
	private static function tendrils( float $chx, float $yJw, float $yCh, int $n, callable $r, callable $ri, bool $behind ): string {
		if ( $n < 1 ) {
			return '';
		}
		$o = '<g fill="none" stroke="#0b0d0b" stroke-linecap="round" opacity="' . ( $behind ? '.85' : '.92' ) . '">';
		for ( $i = 0; $i < $n; $i++ ) {
			$sideL = $r() < 0.5;
			$x     = 320 + ( $sideL ? -1 : 1 ) * $r( 14, max( 30, $chx - 300 ) );
			$y     = $yJw + $r( -20, 70 );
			$dir   = ( $sideL ? -1 : 1 ) * $r( 0.08, 0.4 );
			$w     = $r( 2, 5.5 );
			$d     = 'M' . self::nn( $x ) . ' ' . self::nn( $y );
			$segs  = $ri( 3, 6 );
			for ( $s = 0; $s < $segs; $s++ ) {
				$dir += $r( -0.28, 0.28 );
				$nx   = $x + sin( $dir ) * $r( 16, 40 ) + ( $sideL ? -4 : 4 );
				$ny   = $y + $r( 40, 80 );
				$d   .= 'Q' . self::nn( ( $x + $nx ) / 2 + $r( -22, 22 ) ) . ' ' . self::nn( ( $y + $ny ) / 2 ) . ' ' . self::nn( $nx ) . ' ' . self::nn( $ny );
				$x    = $nx;
				$y    = $ny;
				if ( $y > 660 ) {
					break;
				}
			}
			$o .= '<path d="' . $d . '" stroke-width="' . self::nn( $w ) . '"/>';
		}
		return $o . '</g>';
	}

	/** Draped veil over the head (SPECTER / THE TRANSCENDENT). @param array<string,float> $Y */
	private static function veil( float $hx, array $Y, bool $behind ): string {
		if ( $behind ) {
			return '<path d="M' . self::nn( $hx - 46 ) . ' ' . self::nn( $Y['cr'] - 6 )
				. 'Q320 ' . self::nn( $Y['cr'] - 74 ) . ' ' . self::nn( 686 - $hx ) . ' ' . self::nn( $Y['cr'] - 6 )
				. 'L' . self::nn( 706 - $hx ) . ' 640 L' . self::nn( $hx - 66 ) . ' 640 Z" fill="#0b0e0c" opacity=".6"/>';
		}
		return '<g fill="none" stroke="#aab29b" opacity=".22">'
			. '<path d="M' . self::nn( $hx - 30 ) . ' ' . self::nn( $Y['cr'] + 6 ) . 'Q320 ' . self::nn( $Y['wd'] - 30 ) . ' ' . self::nn( 670 - $hx ) . ' ' . self::nn( $Y['cr'] + 6 ) . '"/>'
			. '<path d="M' . self::nn( $hx - 6 ) . ' ' . self::nn( $Y['mid'] ) . 'Q320 ' . self::nn( $Y['jw'] ) . ' ' . self::nn( 646 - $hx ) . ' ' . self::nn( $Y['mid'] ) . '"/>'
			. '<path d="M' . self::nn( $hx + 20 ) . ' ' . self::nn( $Y['ch'] ) . 'Q320 ' . self::nn( $Y['ch'] + 40 ) . ' ' . self::nn( 620 - $hx ) . ' ' . self::nn( $Y['ch'] ) . '"/></g>';
	}

	private static function neck( string $uid, float $chx, float $yJw, callable $r ): string {
		$nw  = $chx - $r( 30, 44 );
		$sl  = $r( 108, 138 );
		$sy  = $r( 500, 542 );
		$o   = '<path d="M' . self::nn( $nw + 6 ) . ' ' . self::nn( $yJw + 40 )
			. 'L' . self::nn( $nw ) . ' ' . self::nn( $sy - 44 )
			. 'L' . self::nn( 320 - $sl - 60 ) . ' ' . self::nn( $sy )
			. 'L' . self::nn( 320 - $sl - 60 ) . ' 640 L' . self::nn( 320 + $sl + 60 ) . ' 640'
			. 'L' . self::nn( 320 + $sl + 60 ) . ' ' . self::nn( $sy )
			. 'L' . self::nn( 640 - $nw ) . ' ' . self::nn( $sy - 44 )
			. 'L' . self::nn( 640 - $nw - 6 ) . ' ' . self::nn( $yJw + 40 ) . 'Z" fill="url(#' . $uid . 'nk)" stroke="#525c4e" stroke-width="1"/>';
		$o  .= '<g fill="none" stroke="#8b917f" opacity=".26"><path d="M' . self::nn( 320 - $sl * 0.5 ) . ' ' . self::nn( $sy - 30 ) . 'L320 ' . self::nn( $sy + 40 ) . 'L' . self::nn( 320 + $sl * 0.5 ) . ' ' . self::nn( $sy - 30 ) . 'M320 ' . self::nn( $yJw + 48 ) . 'V' . self::nn( $sy + 30 ) . '"/></g>';
		return $o;
	}

	private static function pads( float $hx, float $yWd, array $mat, string $accent, callable $r ): string {
		$py = $yWd - $r( 34, 54 );
		$ph = $r( 84, 110 );
		return '<g fill="' . $mat[3] . '" stroke="#727c68" stroke-width="1">'
			. '<rect x="' . self::nn( $hx - 30 ) . '" y="' . self::nn( $py ) . '" width="34" height="' . self::nn( $ph ) . '" rx="13"/>'
			. '<rect x="' . self::nn( 636 - $hx ) . '" y="' . self::nn( $py ) . '" width="34" height="' . self::nn( $ph ) . '" rx="13"/>'
			. '<circle cx="' . self::nn( $hx - 13 ) . '" cy="' . self::nn( $py + $ph * 0.45 ) . '" r="16"/>'
			. '<circle cx="' . self::nn( 653 - $hx ) . '" cy="' . self::nn( $py + $ph * 0.45 ) . '" r="16"/></g>';
	}

	/** @param array<string,float> $Y */
	private static function topology( float $hx, float $chx, array $Y, callable $ri, callable $r, float $mesh ): string {
		$rows = (int) round( $ri( 11, 17 ) + $mesh * 5 );
		$o    = '<g fill="none" stroke="#d3d9be" stroke-width=".6" opacity="' . self::nn( $r( 0.15, 0.27 ), 2 ) . '">';
		$top  = $Y['cr'] + 12;
		$span = ( $Y['ch'] - 8 ) - $top;
		for ( $i = 0; $i < $rows; $i++ ) {
			$y    = $top + $span * ( $i / max( 1, $rows - 1 ) );
			$ins  = max( 0, $i - $rows * 0.5 ) * 6;
			$o   .= '<path d="M' . self::nn( $hx + 8 + $ins ) . ' ' . self::nn( $y ) . 'Q320 ' . self::nn( $y - 46 ) . ' ' . self::nn( 632 - $hx - $ins ) . ' ' . self::nn( $y ) . '"/>';
		}
		// short construction diagonals across the brow only
		$o .= '<path d="M' . self::nn( $hx + 40 ) . ' ' . self::nn( $Y['cr'] + 20 ) . 'L' . self::nn( 320 - 8 ) . ' ' . self::nn( $Y['mid'] + 30 )
			. 'M' . self::nn( 600 - $hx ) . ' ' . self::nn( $Y['cr'] + 20 ) . 'L' . self::nn( 320 + 8 ) . ' ' . self::nn( $Y['mid'] + 30 ) . '"/>';
		return $o . '</g>';
	}

	/** @param array<string,float> $Y */
	private static function materialDetail( string $face, array $Y, callable $r, callable $ri ): string {
		switch ( $face ) {
			case 'CERAMIC':
				$o = '<g fill="none" stroke="#0b0d0b" stroke-width="1" opacity=".5">';
				for ( $i = 0; $i < 4; $i++ ) {
					$x = 320 + $r( -84, 84 );
					$y = $r( $Y['mid'], $Y['ch'] - 30 );
					$o .= '<path d="M' . self::nn( $x ) . ' ' . self::nn( $y ) . 'l' . self::nn( $r( -26, 26 ) ) . ' ' . self::nn( $r( 22, 48 ) ) . 'l' . self::nn( $r( -22, 22 ) ) . ' ' . self::nn( $r( 16, 40 ) ) . '"/>';
				}
				return $o . '</g>';
			case 'PORCELAIN':
				return '<path d="M' . self::nn( 320 + $r( -26, 26 ) ) . ' ' . self::nn( $Y['mid'] ) . 'q' . self::nn( $r( -12, 12 ) ) . ' ' . self::nn( $r( 70, 130 ) ) . ' ' . self::nn( $r( -6, 6 ) ) . ' ' . self::nn( $r( 150, 220 ) ) . '" fill="none" stroke="#0a0c0a" stroke-width="1" opacity=".38"/>';
			case 'RECONSTRUCTED':
				return '<g fill="none" stroke="#aeb69d" stroke-width="1" stroke-dasharray="5 3" opacity=".6">'
					. '<path d="M' . self::nn( 320 - 130 ) . ' ' . self::nn( $Y['mid'] + 70 ) . 'H' . self::nn( 320 + 130 ) . '"/>'
					. '<path d="M' . self::nn( 320 + $r( -18, 18 ) ) . ' ' . self::nn( $Y['cr'] + 20 ) . 'V' . self::nn( $Y['ch'] - 10 ) . '"/>'
					. '<path d="M' . self::nn( 320 - 90 ) . ' ' . self::nn( $Y['wd'] ) . 'l70 64"/></g>';
			case 'BIO-SYNTH':
				$o = '<g fill="none" stroke="#828c5e" stroke-width="1" opacity=".4">';
				for ( $i = 0; $i < 3; $i++ ) {
					$o .= '<path d="M' . self::nn( 320 + $r( -64, 64 ) ) . ' ' . self::nn( $Y['mid'] ) . 'q' . self::nn( $r( -18, 18 ) ) . ' 70 ' . self::nn( $r( -8, 8 ) ) . ' 150"/>';
				}
				return $o . '</g>';
			case 'CHROME':
				return '<path d="M' . self::nn( 320 - 60 ) . ' ' . self::nn( $Y['cr'] + 30 ) . 'l34 210l22 0l-26-210z" fill="#f3f4ef" opacity=".2"/>';
			default:
				return '';
		}
	}

	/**
	 * Cheek / temple planar shading. Mirrors the original's dark cheek polygons.
	 *
	 * @param array<string,float> $Y
	 */
	private static function planes( float $hx, float $chx, array $Y, float $eyeY ): string {
		$cheekTop = $eyeY + 20;
		return '<g fill="#141c16" opacity=".5">'
			. '<path d="M' . self::nn( $hx + 30 ) . ' ' . self::nn( $cheekTop ) . 'Q' . self::nn( 320 - 90 ) . ' ' . self::nn( $cheekTop + 50 ) . ' ' . self::nn( 320 - 46 ) . ' ' . self::nn( $Y['jw'] - 6 )
				. 'L' . self::nn( 320 - 78 ) . ' ' . self::nn( $Y['jw'] + 28 ) . 'Q' . self::nn( $hx + 26 ) . ' ' . self::nn( $Y['jw'] - 20 ) . ' ' . self::nn( $hx + 30 ) . ' ' . self::nn( $cheekTop ) . 'Z"/>'
			. '<path d="M' . self::nn( 610 - $hx ) . ' ' . self::nn( $cheekTop ) . 'Q' . self::nn( 320 + 90 ) . ' ' . self::nn( $cheekTop + 50 ) . ' ' . self::nn( 320 + 46 ) . ' ' . self::nn( $Y['jw'] - 6 )
				. 'L' . self::nn( 320 + 78 ) . ' ' . self::nn( $Y['jw'] + 28 ) . 'Q' . self::nn( 614 - $hx ) . ' ' . self::nn( $Y['jw'] - 20 ) . ' ' . self::nn( 610 - $hx ) . ' ' . self::nn( $cheekTop ) . 'Z"/></g>';
	}

	private static function eyes( string $eyes, float $gap, float $ey, float $sz, string $col, callable $ri, callable $r, callable $bias ): string {
		$socket = static function ( int $dir ) use ( $gap, $ey, $sz ) {
			$cx = 320 + $dir * $gap;
			$w  = 52 * $sz;
			$h  = 26 * $sz;
			return '<path d="M' . self::nn( $cx - $w / 2 ) . ' ' . self::nn( $ey ) . 'L' . self::nn( $cx - $w * 0.18 ) . ' ' . self::nn( $ey - $h * 0.55 )
				. 'L' . self::nn( $cx + $w * 0.32 ) . ' ' . self::nn( $ey - $h * 0.32 ) . 'L' . self::nn( $cx + $w / 2 ) . ' ' . self::nn( $ey + $h * 0.1 )
				. 'L' . self::nn( $cx + $w * 0.12 ) . ' ' . self::nn( $ey + $h * 0.5 ) . 'L' . self::nn( $cx - $w * 0.34 ) . ' ' . self::nn( $ey + $h * 0.3 ) . 'Z"'
				. ' fill="#08100b" stroke="#8f998233" stroke-width="1"/>';
		};

		if ( 'WITNESS ARRAY' === $eyes || $bias( 'multieye' ) > 0.6 ) {
			$o   = '';
			$cnt = $ri( 3, 5 );
			for ( $i = 0; $i < $cnt; $i++ ) {
				$f  = $i / max( 1, $cnt - 1 );
				$x  = 320 + ( $f - 0.5 ) * $gap * 3.1;
				$y  = $ey - 6 + abs( $f - 0.5 ) * 26 + $r( -5, 5 );
				$rr = self::nn( ( 7 - abs( $f - 0.5 ) * 5 ) * $sz );
				$o .= '<circle cx="' . self::nn( $x ) . '" cy="' . self::nn( $y ) . '" r="' . $rr . '" fill="#08100b" stroke="#8f9982" stroke-width="1"/>'
					. '<circle cx="' . self::nn( $x ) . '" cy="' . self::nn( $y ) . '" r="2.4" fill="' . $col . '"/>';
			}
			return $o;
		}

		$o = $socket( -1 ) . $socket( 1 );
		$lx = 320 - $gap;
		$rx = 320 + $gap;
		switch ( $eyes ) {
			case 'NULL APERTURE':
				$o .= '<circle cx="' . self::nn( $lx ) . '" cy="' . self::nn( $ey ) . '" r="' . self::nn( 7 * $sz ) . '" fill="#000"/><circle cx="' . self::nn( $rx ) . '" cy="' . self::nn( $ey ) . '" r="' . self::nn( 7 * $sz ) . '" fill="#000"/>';
				break;
			case 'FRACTURED':
				$o .= '<circle cx="' . self::nn( $lx ) . '" cy="' . self::nn( $ey ) . '" r="' . self::nn( 5 * $sz ) . '" fill="' . $col . '"/>'
					. '<path d="M' . self::nn( $rx - 10 ) . ' ' . self::nn( $ey - 9 ) . 'l20 5-7 15-15-7z" fill="' . $col . '" opacity=".85"/>';
				break;
			case 'REVOCATION LENS':
				$o .= '<path d="M' . self::nn( $lx - 11 ) . ' ' . self::nn( $ey - 11 ) . 'l22 22m0-22l-22 22M' . self::nn( $rx - 11 ) . ' ' . self::nn( $ey - 11 ) . 'l22 22m0-22l-22 22" stroke="' . $col . '" stroke-width="2.4"/>';
				break;
			case 'BIOMETRIC':
			case 'OPTICAL ARRAY':
				foreach ( [ $lx, $rx ] as $x ) {
					$o .= '<rect x="' . self::nn( $x - 15 ) . '" y="' . self::nn( $ey - 3 ) . '" width="30" height="4" fill="' . $col . '"/>'
						. '<path d="M' . self::nn( $x - 17 ) . ' ' . self::nn( $ey - 10 ) . 'v-5h6m22 5v-5h-6m-22 20v5h6m22-5v5h-6" fill="none" stroke="' . $col . '" stroke-width="1.6"/>';
				}
				break;
			case 'VOID':
				$o .= '<circle cx="' . self::nn( $lx ) . '" cy="' . self::nn( $ey ) . '" r="3.4" fill="' . $col . '" opacity=".55"/><circle cx="' . self::nn( $rx ) . '" cy="' . self::nn( $ey ) . '" r="3.4" fill="' . $col . '" opacity=".55"/>';
				break;
			case 'THERMAL':
			case 'SIGNAL-BURN':
				foreach ( [ $lx, $rx ] as $x ) {
					$o .= '<circle cx="' . self::nn( $x ) . '" cy="' . self::nn( $ey ) . '" r="' . self::nn( 13 * $sz ) . '" fill="' . $col . '" opacity=".22"/>'
						. '<circle cx="' . self::nn( $x ) . '" cy="' . self::nn( $ey ) . '" r="' . self::nn( 5.5 * $sz ) . '" fill="' . $col . '"/>';
				}
				break;
			default:
				$o .= '<circle cx="' . self::nn( $lx ) . '" cy="' . self::nn( $ey ) . '" r="' . self::nn( 4.6 * $sz ) . '" fill="' . $col . '"/><circle cx="' . self::nn( $rx ) . '" cy="' . self::nn( $ey ) . '" r="' . self::nn( 4.6 * $sz ) . '" fill="' . $col . '"/>';
		}
		return $o;
	}

	private static function nose( float $top, float $bot, float $w, callable $r ): string {
		$flare = $r( 6, 16 );
		return '<path d="M' . self::nn( 320 - 3 ) . ' ' . self::nn( $top ) . 'L' . self::nn( 320 - $w * 0.55 ) . ' ' . self::nn( $bot - 10 )
			. 'L' . self::nn( 320 - $w * 0.55 - $flare ) . ' ' . self::nn( $bot ) . 'L' . self::nn( 320 - $w * 0.2 ) . ' ' . self::nn( $bot + 8 )
			. 'L' . self::nn( 320 + $w * 0.2 ) . ' ' . self::nn( $bot + 8 ) . 'L' . self::nn( 320 + $w * 0.55 + $flare ) . ' ' . self::nn( $bot )
			. 'L' . self::nn( 320 + $w * 0.55 ) . ' ' . self::nn( $bot - 10 ) . 'L' . self::nn( 320 + 3 ) . ' ' . self::nn( $top ) . 'Z"'
			. ' fill="#141a15" fill-opacity=".45" stroke="#9aa389" stroke-opacity=".5"/>'
			. '<path d="M' . self::nn( 320 - $w * 0.3 ) . ' ' . self::nn( ( $top + $bot ) / 2 ) . 'Q320 ' . self::nn( $bot - 6 ) . ' ' . self::nn( 320 + $w * 0.3 ) . ' ' . self::nn( ( $top + $bot ) / 2 ) . '" fill="none" stroke="#c0c8b0" stroke-opacity=".4"/>';
	}

	private static function mouth( string $mask, float $my, float $w, float $yCh ): string {
		if ( in_array( $mask, [ 'RESPIRATOR', 'NULL MASK', 'FORENSIC PLATE', 'SKELETAL INTERFACE' ], true ) ) {
			return '';
		}
		return '<path d="M' . self::nn( 320 - $w ) . ' ' . self::nn( $my ) . 'Q320 ' . self::nn( $my + 3 ) . ' ' . self::nn( 320 + $w ) . ' ' . self::nn( $my )
			. 'Q320 ' . self::nn( $my + 9 ) . ' ' . self::nn( 320 - $w ) . ' ' . self::nn( $my ) . 'Z" fill="#0f1510" stroke="#82907780" stroke-width=".8"/>'
			. '<path d="M' . self::nn( 320 - $w * 0.7 ) . ' ' . self::nn( $my + 1 ) . 'H' . self::nn( 320 + $w * 0.7 ) . '" stroke="#7f8c73" stroke-opacity=".45"/>';
	}

	/** @param array<string,float> $Y */
	private static function mask( string $mask, string $uid, float $hx, float $chx, array $Y, float $eyeY, float $my, array $mat, string $accent, callable $r, callable $ri ): string {
		$lowT = $eyeY + $r( 26, 44 );
		switch ( $mask ) {
			case 'FORENSIC PLATE':
				// segmented lower-face plate that follows the jawline
				$mid = ( $lowT + $Y['ch'] ) / 2;
				$o   = '<g clip-path="url(#' . $uid . 'cl)">';
				$o  .= '<path d="M' . self::nn( $hx + 20 ) . ' ' . self::nn( $lowT + 6 )
					. 'Q320 ' . self::nn( $lowT - 12 ) . ' ' . self::nn( 620 - $hx ) . ' ' . self::nn( $lowT + 6 )
					. 'Q' . self::nn( 600 - $hx ) . ' ' . self::nn( $Y['jw'] + 30 ) . ' ' . self::nn( 640 - $chx ) . ' ' . self::nn( $Y['ch'] + 4 )
					. 'Q320 ' . self::nn( $Y['ch'] + 24 ) . ' ' . self::nn( $chx ) . ' ' . self::nn( $Y['ch'] + 4 )
					. 'Q' . self::nn( $hx + 40 ) . ' ' . self::nn( $Y['jw'] + 30 ) . ' ' . self::nn( $hx + 20 ) . ' ' . self::nn( $lowT + 6 )
					. 'Z" fill="' . $mat[1] . '" stroke="#0c0e0c" stroke-width="1" opacity=".94"/>';
				$o  .= '<g fill="none" stroke="#0c0e0c" stroke-opacity=".55"><path d="M320 ' . self::nn( $lowT + 4 ) . 'V' . self::nn( $Y['ch'] + 8 ) . 'M' . self::nn( $hx + 44 ) . ' ' . self::nn( $mid ) . 'Q320 ' . self::nn( $mid + 10 ) . ' ' . self::nn( 596 - $hx ) . ' ' . self::nn( $mid ) . '"/></g>';
				$o  .= '<g fill="none" stroke="#cdd4bd" stroke-opacity=".28"><path d="M' . self::nn( $hx + 30 ) . ' ' . self::nn( $lowT + 2 ) . 'Q320 ' . self::nn( $lowT - 14 ) . ' ' . self::nn( 610 - $hx ) . ' ' . self::nn( $lowT + 2 ) . '"/></g>';
				$o  .= '<circle cx="320" cy="' . self::nn( $mid ) . '" r="4.5" fill="' . $accent . '"/></g>';
				return $o;
			case 'RESPIRATOR':
				return '<g stroke="#7d8873" stroke-width="1.4" fill="' . $mat[3] . '">'
					. '<rect x="' . self::nn( 320 - 30 ) . '" y="' . self::nn( $my - 24 ) . '" width="60" height="52" rx="12"/>'
					. '<path d="M' . self::nn( 320 - 30 ) . ' ' . self::nn( $my - 8 ) . 'L' . self::nn( $hx + 44 ) . ' ' . self::nn( $my - 34 ) . 'M' . self::nn( 320 + 30 ) . ' ' . self::nn( $my - 8 ) . 'L' . self::nn( 596 - $hx ) . ' ' . self::nn( $my - 34 ) . '"/>'
					. '<circle cx="320" cy="' . self::nn( $my + 2 ) . '" r="11" fill="none"/><path d="M' . self::nn( 320 - 18 ) . ' ' . self::nn( $my - 12 ) . 'h36m-36 12h36m-36 12h36" stroke-opacity=".4"/></g>';
			case 'SKELETAL INTERFACE':
				// exposed mandible + tooth row — angular, not a smooth arc
				$jw = $Y['jw'] + 10;
				$o  = '<g fill="none" stroke="#c9cfba" stroke-width="1.3" opacity=".82">';
				$o .= '<path d="M' . self::nn( $hx + 38 ) . ' ' . self::nn( $lowT ) . 'L' . self::nn( 320 - 54 ) . ' ' . self::nn( $jw ) . 'L' . self::nn( 320 - 20 ) . ' ' . self::nn( $Y['ch'] - 6 )
					. 'H' . self::nn( 320 + 20 ) . 'L' . self::nn( 320 + 54 ) . ' ' . self::nn( $jw ) . 'L' . self::nn( 602 - $hx ) . ' ' . self::nn( $lowT ) . '"/>';
				$o .= '<path d="M' . self::nn( 320 - 60 ) . ' ' . self::nn( $lowT + 6 ) . 'H' . self::nn( 320 + 60 ) . '"/>';
				for ( $i = -4; $i <= 4; $i++ ) {
					$o .= '<path d="M' . self::nn( 320 + $i * 13 ) . ' ' . self::nn( $lowT + 6 ) . 'v11"/>';
				}
				$o .= '<path d="M320 ' . self::nn( $lowT + 20 ) . 'V' . self::nn( $Y['ch'] - 8 ) . 'M' . self::nn( 320 - 40 ) . ' ' . self::nn( $lowT - 10 ) . 'l14 18M' . self::nn( 320 + 40 ) . ' ' . self::nn( $lowT - 10 ) . 'l-14 18"/></g>';
				return $o;
			case 'NEURAL VEIL':
				$o = '<g clip-path="url(#' . $uid . 'cl)" fill="none" stroke="' . $accent . '" stroke-width=".7" opacity=".42">';
				for ( $i = 0; $i <= 7; $i++ ) {
					$o .= '<path d="M' . self::nn( $hx + $i * ( ( 640 - 2 * $hx ) / 7 ) ) . ' ' . self::nn( $eyeY - 20 ) . 'Q320 ' . self::nn( $Y['ch'] + 6 ) . ' ' . self::nn( $hx + $i * ( ( 640 - 2 * $hx ) / 7 ) + $r( -20, 20 ) ) . ' ' . self::nn( $Y['ch'] ) . '"/>';
				}
				for ( $i = 0; $i < 5; $i++ ) {
					$o .= '<path d="M' . self::nn( $hx + 10 ) . ' ' . self::nn( $eyeY + $i * 30 ) . 'H' . self::nn( 630 - $hx ) . '"/>';
				}
				return $o . '</g>';
			case 'NULL MASK':
				// a smooth featureless half-mask that erases the mid-face
				$mt = $eyeY - $r( 30, 44 );
				$mb = $Y['ch'] - $r( 20, 44 );
				return '<path d="M320 ' . self::nn( $mt )
					. 'C' . self::nn( $hx + 44 ) . ' ' . self::nn( $mt + 6 ) . ' ' . self::nn( $hx + 30 ) . ' ' . self::nn( ( $mt + $mb ) / 2 ) . ' ' . self::nn( $hx + 56 ) . ' ' . self::nn( $mb )
					. 'Q320 ' . self::nn( $mb + 30 ) . ' ' . self::nn( 584 - $hx ) . ' ' . self::nn( $mb )
					. 'C' . self::nn( 610 - $hx ) . ' ' . self::nn( ( $mt + $mb ) / 2 ) . ' ' . self::nn( 596 - $hx ) . ' ' . self::nn( $mt + 6 ) . ' 320 ' . self::nn( $mt )
					. 'Z" fill="' . $mat[2] . '" stroke="#7c8974" stroke-width="1.1" opacity=".96"/>'
					. '<path d="M' . self::nn( 320 - 60 ) . ' ' . self::nn( ( $mt + $mb ) / 2 - 6 ) . 'Q320 ' . self::nn( ( $mt + $mb ) / 2 - 14 ) . ' ' . self::nn( 320 + 60 ) . ' ' . self::nn( ( $mt + $mb ) / 2 - 6 ) . '" fill="none" stroke="#0c0e0c" stroke-opacity=".35"/>';
			default:
				return '';
		}
	}

	private static function implant( string $implant, float $hx, float $yCr, float $eyeY, float $yJw, string $accent, callable $r, callable $ri, callable $bias ): string {
		if ( $bias( 'crown' ) > 0.7 && 'SIGNAL CROWN' !== $implant ) {
			$implant = 'SIGNAL CROWN';
		}
		$side = ( $bias( 'port' ) > 0 || $r() < 0.5 ) ? 1 : -1;
		$tx   = 320 + $side * ( 152 - $hx + 60 );
		$ty   = $eyeY + $r( -14, 26 );
		switch ( $implant ) {
			case 'SIGNAL CROWN':
				$b  = $yCr + 2;
				$pk = $yCr - $r( 44, 72 );
				$m  = ( $b + $pk ) / 2;
				return '<g fill="none" stroke="' . $accent . '" stroke-width="1.5" opacity=".72">'
					. '<path d="M' . self::nn( 320 - 132 ) . ' ' . self::nn( $b ) . 'L' . self::nn( 320 - 92 ) . ' ' . self::nn( $m ) . 'L' . self::nn( 320 - 54 ) . ' ' . self::nn( $b )
					. 'L' . self::nn( 320 - 26 ) . ' ' . self::nn( $pk + 10 ) . 'L320 ' . self::nn( $pk ) . 'L' . self::nn( 320 + 26 ) . ' ' . self::nn( $pk + 10 )
					. 'L' . self::nn( 320 + 54 ) . ' ' . self::nn( $b ) . 'L' . self::nn( 320 + 92 ) . ' ' . self::nn( $m ) . 'L' . self::nn( 320 + 132 ) . ' ' . self::nn( $b ) . '"/>'
					. '<circle cx="320" cy="' . self::nn( $pk - 8 ) . '" r="5"/></g>';
			case 'ANTENNA':
				$o   = '<g stroke="' . $accent . '" stroke-width="1.8" fill="' . $accent . '" stroke-linecap="round">';
				$cnt = $ri( 2, 4 );
				for ( $i = 0; $i < $cnt; $i++ ) {
					$f   = $cnt > 1 ? $i / ( $cnt - 1 ) - 0.5 : 0;
					$bx  = 320 + $f * 70;
					$len = $r( 70, 120 );
					$tx  = $bx + $f * 60 + $r( -10, 10 );
					$ty  = $yCr - $len;
					$o  .= '<path d="M' . self::nn( $bx ) . ' ' . self::nn( $yCr + 4 ) . 'Q' . self::nn( $bx + $f * 20 ) . ' ' . self::nn( $yCr - $len * 0.5 ) . ' ' . self::nn( $tx ) . ' ' . self::nn( $ty ) . '" fill="none"/>'
						. '<circle cx="' . self::nn( $tx ) . '" cy="' . self::nn( $ty ) . '" r="3.4"/>';
				}
				return $o . '<path d="M' . self::nn( 320 - 40 ) . ' ' . self::nn( $yCr + 2 ) . 'h80" fill="none"/></g>';
			case 'NEURAL CABLE':
				$o = '<g fill="none" stroke="#7f887a" stroke-width="2.6" opacity=".7">';
				for ( $i = 0; $i < $ri( 1, 2 ); $i++ ) {
					$sx = 320 + $r( -50, 50 );
					$o .= '<path d="M' . self::nn( $sx ) . ' ' . self::nn( $yCr + 26 ) . 'Q' . self::nn( $sx + $side * 100 ) . ' ' . self::nn( $eyeY + 40 ) . ' ' . self::nn( $sx + $side * 230 ) . ' ' . self::nn( $yJw + $r( -30, 90 ) ) . '"/>';
				}
				return $o . '</g>';
			case 'SPINAL BUS':
				$o = '<g stroke="' . $accent . '" stroke-width="1.6" fill="' . $accent . '" opacity=".66"><path d="M320 ' . self::nn( $yJw + 20 ) . 'V640" fill="none"/>';
				for ( $y = $yJw + 34; $y < 632; $y += 24 ) {
					$o .= '<circle cx="320" cy="' . self::nn( $y ) . '" r="3.6"/><path d="M306 ' . self::nn( $y ) . 'h28" fill="none"/>';
				}
				return $o . '</g>';
			case 'ROOT PORT':
				return '<g stroke="' . $accent . '" stroke-width="1.5" fill="#0b0d0b">'
					. '<path d="M' . self::nn( $tx ) . ' ' . self::nn( $ty - 15 ) . 'l15 9v18l-15 9-15-9v-18z"/>'
					. '<circle cx="' . self::nn( $tx ) . '" cy="' . self::nn( $ty ) . '" r="4" fill="' . $accent . '"/>'
					. '<path d="M' . self::nn( $tx - $side * 15 ) . ' ' . self::nn( $ty - 4 ) . 'h' . self::nn( -$side * 16 ) . 'm' . self::nn( $side * 16 ) . ' 8h' . self::nn( -$side * 16 ) . '" fill="none"/></g>';
			case 'OPTICAL ARRAY':
				$o = '<g stroke="' . $accent . '" stroke-width="1.4" fill="none" opacity=".8">';
				for ( $i = 0; $i < 3; $i++ ) {
					$o .= '<circle cx="' . self::nn( $tx - $side * $i * 4 ) . '" cy="' . self::nn( $ty + $i * 15 ) . '" r="' . self::nn( 8 - $i * 2 ) . '"/>';
				}
				return $o . '</g>';
			default:
				return '';
		}
	}

	/** @param array<string,float> $Y */
	private static function knockout( string $uid, string $facePath, array $Y, callable $r, callable $ri, callable $bias ): string {
		$p = 0.12 + $bias( 'knockout' ) + $bias( 'reconstruct' );
		if ( $r() > $p ) {
			return '';
		}
		// a "reconstructed area" marker that sits ON the material — hatch + dashed
		// outline + faint recess shadow, never a solid black box
		$o = '<g clip-path="url(#' . $uid . 'cl)">';
		for ( $k = 0; $k < $ri( 1, 2 ); $k++ ) {
			$x = 320 + $r( -66, 66 );
			$y = $r( $Y['mid'] + 20, $Y['ch'] - 60 );
			$w = $r( 40, 78 );
			$h = $r( 36, 70 );
			$d = 'M' . self::nn( $x ) . ' ' . self::nn( $y ) . 'l' . self::nn( $w ) . ' ' . self::nn( $r( -6, 6 ) ) . 'l' . self::nn( $r( -6, 10 ) ) . ' ' . self::nn( $h ) . 'l' . self::nn( -$w ) . ' ' . self::nn( $r( -4, 6 ) ) . 'z';
			$o .= '<path d="' . $d . '" fill="#0a0c0a" opacity=".32"/><path d="' . $d . '" fill="url(#' . $uid . 'ht)" opacity=".5"/><path d="' . $d . '" fill="none" stroke="#b3bba1" stroke-width="1" stroke-dasharray="4 3" opacity=".7"/>';
		}
		return $o . '</g>';
	}

	/** @param array<string,float> $Y */
	private static function corruption( string $c, string $uid, string $face, string $accent, array $Y, callable $r, callable $ri, callable $bias ): string {
		if ( 'NONE' === $c && $bias( 'glitch' ) > 0.3 ) {
			$c = 'GLITCH';
		}
		if ( 'NONE' === $c && $bias( 'burn' ) > 0.3 ) {
			$c = 'BURN';
		}
		if ( 'NONE' === $c && $bias( 'split' ) > 0.4 ) {
			$c = 'FRAGMENTATION';
		}
		$clip = '<g clip-path="url(#' . $uid . 'cl)">';
		switch ( $c ) {
			case 'GLITCH':
				$o = $clip;
				for ( $i = 0; $i < $ri( 2, 4 ); $i++ ) {
					$y  = $r( $Y['mid'], $Y['ch'] );
					$hh = $r( 5, 18 );
					$dx = $r( -22, 22 );
					$o .= '<rect x="0" y="' . self::nn( $y ) . '" width="640" height="' . self::nn( $hh ) . '" fill="#0a0c0a" opacity=".5"/>'
						. '<rect x="' . self::nn( $dx ) . '" y="' . self::nn( $y ) . '" width="640" height="' . self::nn( $hh ) . '" fill="' . $accent . '" opacity=".14"/>';
				}
				return $o . '</g>';
			case 'FRAGMENTATION':
				// the seam itself is drawn by the kintsugi layer; here just a faint
				// tectonic offset of one half so the plates read as displaced
				$gap = $r( 4, 12 );
				return $clip . '<path d="M' . self::nn( 320 - $gap ) . ' ' . self::nn( $Y['cr'] ) . 'V' . self::nn( $Y['ch'] + 10 ) . '" stroke="#060706" stroke-width="' . self::nn( $gap * 1.6 ) . '"/></g>';
			case 'PACKET GHOSTING':
				$gx = $r( 8, 20 ) * ( $r() < 0.5 ? -1 : 1 );
				return '<path d="' . $face . '" transform="translate(' . self::nn( $gx ) . ' ' . self::nn( $r( -6, 6 ) ) . ')" fill="none" stroke="' . $accent . '" stroke-width="1" opacity=".38"/>';
			case 'SIGNAL LOSS':
				$o = $clip;
				for ( $i = 0; $i < $ri( 2, 4 ); $i++ ) {
					$o .= '<rect x="0" y="' . self::nn( $r( $Y['mid'], $Y['ch'] ) ) . '" width="640" height="' . self::nn( $r( 3, 9 ) ) . '" fill="url(#' . $uid . 'bg)"/>';
				}
				return $o . '</g>';
			case 'MEMORY BLEED':
				$right = $r() < 0.5;
				$edge  = $right ? 400 : 240;
				return $clip . '<path d="M' . ( $right ? 640 : 0 ) . ' 0H' . self::nn( $edge ) . 'V640H' . ( $right ? 640 : 0 ) . 'Z" fill="#050505" opacity=".5"/>'
					. '<path d="M' . self::nn( $edge ) . ' 0V640" stroke="' . $accent . '" stroke-width="1" opacity=".3"/></g>';
			case 'BURN':
				$q  = $ri( 0, 3 );
				$bx = ( $q % 2 ) ? 430 : 210;
				$by = ( $q > 1 ) ? 430 : 210;
				return $clip . '<ellipse cx="' . self::nn( $bx ) . '" cy="' . self::nn( $by ) . '" rx="140" ry="160" fill="#050505" opacity=".68"/>'
					. '<ellipse cx="' . self::nn( $bx ) . '" cy="' . self::nn( $by ) . '" rx="140" ry="160" fill="none" stroke="' . $accent . '" stroke-width="2" opacity=".3"/></g>';
			default:
				return '';
		}
	}

	/** @param array<string,float> $Y */
	private static function rarityMark( string $band, array $Y, string $accent ): string {
		switch ( $band ) {
			case 'GENESIS':
				return '<path d="M320 ' . self::nn( $Y['cr'] + 20 ) . 'V' . self::nn( $Y['ch'] - 10 ) . '" stroke="' . $accent . '" stroke-width="1" opacity=".32"/>'
					. '<g font-family="monospace" font-size="9" fill="' . $accent . '" opacity=".8"><text x="30" y="560">ORIGIN RECORD</text></g>';
			case 'ROOT':
				return '<g font-family="monospace" font-size="8" fill="' . $accent . '" opacity=".7"><text x="592" y="44" text-anchor="end">ROOT</text></g>';
			case 'ADMIN':
				return '<path d="M556 34l14 10-14 10M572 34l14 10-14 10" stroke="' . $accent . '" stroke-width="1.4" fill="none" opacity=".6"/>';
			default:
				return '';
		}
	}

	private static function overlay( array $t, string $seed, string $accent, callable $r, callable $ri, callable $chance, callable $bias, bool $minimal ): string {
		$id = str_pad( $t['ghost_id'], 4, '0', STR_PAD_LEFT );
		$o  = '<g font-family="monospace" font-size="8" fill="#939984" opacity=".72">'
			. '<text x="30" y="' . ( 'GENESIS' === $t['rarity_band'] ? 120 : 34 ) . '">RECONSTRUCTION // ' . esc_html( $id ) . '</text>'
			. '<text x="610" y="612" text-anchor="end">' . esc_html( substr( strtoupper( $seed ), 0, 10 ) ) . '</text></g>';
		if ( $minimal ) {
			return $o;
		}
		$dense = $chance( 0.4 ) || $bias( 'annotate' ) > 0.4;
		$o    .= '<g fill="none" stroke="#8b9280" stroke-width="1" opacity=".4">';
		for ( $i = 0; $i < ( $dense ? $ri( 2, 3 ) : $ri( 0, 1 ) ); $i++ ) {
			$x = $r( 70, 560 );
			$y = $r( 100, 520 );
			$o .= '<path d="M' . self::nn( $x - 12 ) . ' ' . self::nn( $y ) . 'h8m8 0h8M' . self::nn( $x ) . ' ' . self::nn( $y - 12 ) . 'v8m0 8v8"/>';
		}
		if ( $dense ) {
			$o .= '<path d="M197 175H136V124H80M443 340H504V395H558"/><circle cx="197" cy="175" r="3"/><circle cx="443" cy="340" r="3"/>';
		}
		$o .= '</g>';
		if ( $chance( 0.5 ) ) {
			$o .= '<text x="610" y="34" text-anchor="end" font-family="monospace" font-size="8" fill="' . $accent . '" opacity=".6">CONFIDENCE 0.' . $ri( 41, 92 ) . '</text>';
		}
		return $o;
	}
}
