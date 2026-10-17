<?php
/**
 * Standalone test: on a single note, the sidenote keeps its own type size.
 *
 * The notes prose rule `.sn-note-single .wp-block-post-content p` (0,2,1,
 * assets/css/components.css) sets every paragraph in a note to prose size.
 * The sidenote renders as <p class="sn-sidenote">, and its own rules in
 * blocks/sidenote/style.css were (0,2,0), so the prose rule won: measured on
 * 2026-10-16, the margin note rendered at 14.4px, the same as the text it
 * annotates, when its sheet asks for max(0.75rem, 11px) beside the column and
 * 0.85rem below 1280px.
 *
 * This resolves the cascade for that one element at both breakpoints and
 * requires the sidenote's own rule to win ON SPECIFICITY, so the outcome does
 * not depend on which of the two sheets the page happens to load last.
 *
 * Run: php tests/sidenote-note-size.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
require_once __DIR__ . '/lib/css-cascade.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

/**
 * Rules of one sheet: [sel, decls, media] with media '' for top level.
 */
function sns_rules( $file ) {
	$css   = preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( $file ) );
	$out   = array();
	$media = '';
	$depth = 0;
	$pos   = 0;
	while ( preg_match( '/\G\s*(@media([^{]*)\{|\}|([^{}]+)\{([^{}]*)\})/', $css, $m, 0, $pos ) ) {
		$pos = $pos + strlen( $m[0] );
		if ( '' !== ( $m[2] ?? '' ) || 0 === strpos( trim( $m[1] ), '@media' ) ) {
			$media = trim( $m[2] );
			continue;
		}
		if ( '}' === trim( $m[1] ) ) {
			$media = '';
			continue;
		}
		$decls = array();
		foreach ( explode( ';', $m[4] ) as $d ) {
			if ( false !== strpos( $d, ':' ) ) {
				list( $p, $v ) = explode( ':', $d, 2 );
				$decls[ trim( $p ) ] = trim( $v );
			}
		}
		foreach ( array_map( 'trim', explode( ',', $m[3] ) ) as $sel ) {
			$out[] = array( 'sel' => $sel, 'decls' => $decls, 'media' => $media, 'file' => basename( dirname( $file ) ) . '/' . basename( $file ) );
		}
	}
	return $out;
}

$root  = dirname( __DIR__ );
$rules = array_merge( sns_rules( $root . '/assets/css/components.css' ), sns_rules( $root . '/assets/css/article.css' ), sns_rules( $root . '/blocks/sidenote/style.css' ) );
ok( count( $rules ) > 100, count( $rules ) . ' rules parsed (a parser that read nothing would find no competitor and pass)' );

// <main class="sn-note-single"> ... <div class="wp-block-post-content"> <p class="sn-sidenote">
$chain = array( array( 'main', array( 'sn-note-single' ) ), array( 'div', array( 'wp-block-post-content' ) ), array( 'p', array( 'sn-sidenote' ) ) );

$applies = array(
	'>=1280px' => static function ( $media ) { return '' === $media || false !== strpos( $media, 'min-width: 1280px' ); },
	'<1280px'  => static function ( $media ) { return '' === $media || false !== strpos( $media, 'max-width: 1279.98px' ); },
);

foreach ( $applies as $label => $in_media ) {
	foreach ( array( 'font-size', 'line-height' ) as $prop ) {
		$cands = array();
		foreach ( $rules as $r ) {
			if ( ! isset( $r['decls'][ $prop ] ) || ! $in_media( $r['media'] ) ) {
				continue;
			}
			if ( true !== sn_css_selector_matches( $r['sel'], $chain ) ) {
				continue;
			}
			$r['spec'] = sn_css_specificity( $r['sel'] );
			$cands[]   = $r;
		}
		$own   = array_values( array_filter( $cands, static function ( $r ) { return false !== strpos( $r['file'], 'sidenote' ); } ) );
		$other = array_values( array_filter( $cands, static function ( $r ) { return false === strpos( $r['file'], 'sidenote' ); } ) );
		ok( array() !== $own && array() !== $other, "$label $prop: both the sidenote's rule and a prose rule match the element (" . count( $own ) . ' own, ' . count( $other ) . ' competing): the test has something to decide' );
		$best_own   = max( array_map( static function ( $r ) { return $r['spec']; }, $own ?: array( array( 0, 0, 0 ) ) ) );
		$best_other = max( array_map( static function ( $r ) { return $r['spec']; }, $other ?: array( array( 0, 0, 0 ) ) ) );
		ok( $best_own > $best_other, "$label $prop: the sidenote's own rule (" . implode( ',', $best_own ) . ') out-ranks every prose rule (' . implode( ',', $best_other ) . ') by specificity, so load order cannot flip it' );
	}
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
