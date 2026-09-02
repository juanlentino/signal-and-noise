<?php
/**
 * Every class the /notes/tags/ renderer emits must actually EXIST.
 *
 * v12.15.0 shipped three invented container classes — sn-notes-main (real:
 * sn-notes-page), sn-notes-hero-main (real: sn-notes-hero-title) and
 * sn-notes-title (real: sn-notes-headline). The page rendered, every test
 * passed, and the content sat flush against the left viewport edge because the
 * class carrying the page gutter was never applied. An invented class is
 * SILENT: CSS has no such thing as an unresolved selector.
 *
 * A class is legitimate if notes.css defines it, or if the index renderer —
 * the page this one borrows its shell from — already emits it.
 *
 * @since 12.15.1
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

$pass = 0;
$fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS  $label\n";
	} else {
		++$fail;
		echo "FAIL  $label\n";
	}
}

/** Strip PHP and CSS comments so a class NAMED in prose is never counted. */
function strip_comments( $src ) {
	$src = preg_replace( '#/\*.*?\*/#s', '', $src );
	return preg_replace( '#^\s*//.*$#m', '', $src );
}

$dir       = __DIR__ . '/..';
$renderer  = strip_comments( file_get_contents( $dir . '/inc/page-notes-tags-render.php' ) );
$index     = strip_comments( file_get_contents( $dir . '/inc/page-notes-render.php' ) );
$css       = strip_comments( file_get_contents( $dir . '/assets/css/notes.css' ) );

/** Class tokens inside class="..." attributes. */
function classes_in( $src ) {
	$out = array();
	if ( preg_match_all( '/class="([^"]*)"/', $src, $m ) ) {
		foreach ( $m[1] as $attr ) {
			foreach ( preg_split( '/\s+/', trim( $attr ) ) as $c ) {
				if ( '' !== $c && false === strpos( $c, '<' ) ) {
					$out[ $c ] = true;
				}
			}
		}
	}
	return $out;
}

$emitted = classes_in( $renderer );
$known   = classes_in( $index );
if ( preg_match_all( '/\.([a-zA-Z][a-zA-Z0-9_-]*)/', $css, $m ) ) {
	foreach ( $m[1] as $c ) {
		$known[ $c ] = true;
	}
}

// VACUITY GUARDS: an extraction that finds nothing would pass everything.
ok( count( $emitted ) >= 15, 'extracted a plausible number of emitted classes (' . count( $emitted ) . ')' );
ok( count( $known ) >= 50, 'extracted a plausible known-class vocabulary (' . count( $known ) . ')' );
ok( isset( $known['sn-notes-page'] ), 'the real page container class is in the vocabulary' );
ok( ! isset( $known['sn-notes-main'] ), 'the INVENTED class from v12.15.0 is NOT in the vocabulary' );

// The property under test.
$unknown = array();
foreach ( array_keys( $emitted ) as $c ) {
	if ( ! isset( $known[ $c ] ) ) {
		$unknown[] = $c;
	}
}
ok( array() === $unknown, 'every emitted class exists in notes.css or the index renderer' . ( $unknown ? ' — orphans: ' . implode( ', ', $unknown ) : '' ) );

// The specific regressions, pinned by name so they can never come back.
ok( isset( $emitted['sn-notes-page'] ), 'the tags page uses the real container class' );
ok( isset( $emitted['sn-notes-hero-title'] ), 'the tags hero uses the real hero-title class' );
ok( isset( $emitted['sn-notes-headline'] ), 'the tags headline uses the real headline class' );
ok( false !== strpos( $renderer, 'id="wp--skip-link--target"' ), 'the skip-link target is present' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
