<?php
/**
 * Standalone test: the second round of design details (2026-10-17), built after
 * the owner approved the rendered mockups.
 *
 *   1. House glyphs: arrows and the numero sign come from small generated
 *      fonts (tools/build-house-glyphs.py) declared as extra faces of DM Mono
 *      and Bebas with a unicode-range, instead of falling back to Courier New.
 *   2. Section numerals on notes with three or more sections, and in the TOC.
 *   3. Prose list markers: rust 01. and en rules for plain lists in notes.
 *   5. Print running head and folio, plus orphans and widows.
 *   6. A blood caret in the site's text fields; the contact message box grows
 *      with its text and never starts shorter than it does today.
 *
 * Run: php tests/house-detail.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
define( 'SN_PRINT_HEAD_TEST', true );

$root = dirname( __DIR__ );
$css  = static function ( $f ) use ( $root ) {
	return (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $root . '/assets/css/' . $f ) );
};

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

/**
 * Code points a WOFF (zlib) font maps to a real glyph, read from its cmap.
 *
 * @param string $file
 * @return int[]
 */
function woff_cmap( $file ) {
	$b = (string) @file_get_contents( $file );
	if ( strlen( $b ) < 44 || 'wOFF' !== substr( $b, 0, 4 ) ) {
		return array();
	}
	$n    = unpack( 'n', substr( $b, 12, 2 ) )[1];
	$cmap = null;
	for ( $i = 0; $i < $n; $i++ ) {
		$e = unpack( 'a4tag/Noff/Ncomp/Norig', substr( $b, 44 + 20 * $i, 16 ) );
		if ( 'cmap' === $e['tag'] ) {
			$raw  = substr( $b, $e['off'], $e['comp'] );
			$cmap = $e['comp'] < $e['orig'] ? gzuncompress( $raw ) : $raw;
		}
	}
	if ( ! is_string( $cmap ) ) {
		return array();
	}
	$cps = array();
	$nt  = unpack( 'n', substr( $cmap, 2, 2 ) )[1];
	for ( $i = 0; $i < $nt; $i++ ) {
		$r   = unpack( 'nplat/nenc/Noff', substr( $cmap, 4 + 8 * $i, 8 ) );
		$sub = substr( $cmap, $r['off'] );
		if ( 4 !== unpack( 'n', substr( $sub, 0, 2 ) )[1] ) {
			continue;
		}
		$seg = unpack( 'n', substr( $sub, 6, 2 ) )[1] / 2;
		for ( $s = 0; $s < $seg; $s++ ) {
			$end   = unpack( 'n', substr( $sub, 14 + 2 * $s, 2 ) )[1];
			$start = unpack( 'n', substr( $sub, 16 + 2 * $seg + 2 * $s, 2 ) )[1];
			$delta = unpack( 'n', substr( $sub, 16 + 4 * $seg + 2 * $s, 2 ) )[1];
			$rng   = unpack( 'n', substr( $sub, 16 + 6 * $seg + 2 * $s, 2 ) )[1];
			for ( $cp = $start; $cp <= $end && 0xFFFF !== $cp; $cp++ ) {
				if ( 0 === $rng ) {
					$gid = ( $cp + $delta ) & 0xFFFF;
				} else {
					// glyphIdArray entry: idRangeOffset is relative to its own slot.
					$at  = 16 + 6 * $seg + 2 * $s + $rng + 2 * ( $cp - $start );
					$gid = unpack( 'n', substr( $sub, $at, 2 ) )[1];
					$gid = $gid ? ( $gid + $delta ) & 0xFFFF : 0;
				}
				if ( 0 !== $gid ) {
					$cps[] = $cp;
				}
			}
		}
	}
	return array_values( array_unique( $cps ) );
}

echo "Group 1: house glyphs\n";
$arrows = array( 0x2190, 0x2191, 0x2192, 0x2193, 0x2196, 0x2197, 0x2198, 0x2199 );
foreach ( array( 300, 400, 500 ) as $w ) {
	$got = woff_cmap( "$root/assets/fonts/sn-glyphs-dm-mono-$w.woff" );
	ok( array() === array_diff( array_merge( $arrows, array( 0x2116 ) ), $got ), "DM Mono $w: all eight arrows and the numero sign have glyphs (" . count( $got ) . ' mapped)' );
	ok( ! in_array( ord( 'A' ), $got, true ), "DM Mono $w: nothing else, so it can never stand in for a letter" );
}
$bebas = woff_cmap( "$root/assets/fonts/sn-glyphs-bebas.woff" );
ok( array() === array_diff( array( 0x2190, 0x2192, 0x2196, 0x2197, 0x2198, 0x2199 ), $bebas ), 'Bebas: the six arrows it lacks' );
ok( ! in_array( 0x2191, $bebas, true ) && ! in_array( 0x2193, $bebas, true ), 'Bebas: its own up and down arrows are left alone' );

$theme = json_decode( (string) file_get_contents( "$root/theme.json" ), true );
$faces = array();
foreach ( $theme['settings']['typography']['fontFamilies'] as $fam ) {
	foreach ( $fam['fontFace'] ?? array() as $i => $f ) {
		$faces[] = array( 'family' => $fam['slug'], 'i' => $i ) + $f;
	}
}
$glyph_faces = array_values( array_filter( $faces, static fn( $f ) => false !== strpos( implode( ' ', (array) $f['src'] ), 'sn-glyphs-' ) ) );
ok( 4 === count( $glyph_faces ), 'theme.json declares the four glyph faces' );
foreach ( $glyph_faces as $f ) {
	$name = basename( (string) $f['src'][0] );
	ok( isset( $f['unicodeRange'] ) && false !== strpos( $f['unicodeRange'], 'U+2190' ), "$name is limited by unicodeRange to the glyphs it carries" );
	$main = array_filter( $faces, static fn( $g ) => $g['family'] === $f['family'] && false === strpos( implode( ' ', (array) $g['src'] ), 'sn-glyphs-' ) && ( $g['fontWeight'] ?? '' ) === ( $f['fontWeight'] ?? '' ) );
	ok( array() !== $main && max( array_column( $main, 'i' ) ) < $f['i'], "$name is declared after its family's main face (the last declared face is checked first)" );
}
ok( 2 === substr_count( (string) file_get_contents( "$root/blocks/pillar-essays/render.php" ), 'aria-hidden="true">&#8470; ' ), 'the numero sign stays in the markup: the font draws it, nothing inside a signed region changes' );
ok( is_file( "$root/tools/build-house-glyphs.py" ), 'the generator is in the repo, so the fonts can be rebuilt' );

echo "\nGroup 2: section numerals\n";
$article = $css( 'article.css' );
ok( 1 === preg_match( '/\.wp-block-post-content:has\(> h2\.wp-block-heading:nth-of-type\(3\)\)\s*>\s*h2\.wp-block-heading:not\(#references\)::before\s*\{[^}]*counter-increment:\s*sn-sec[^}]*content:\s*counter\(sn-sec,\s*decimal-leading-zero\)\s*\/\s*""/s', $article ), 'notes with three or more sections number them 01, 02 (alt text "" so a screen reader does not read the number); References is not numbered' );
ok( 1 === preg_match( '/h2\.wp-block-heading:not\(#references\)::before\s*\{[^}]*color:\s*var\(--wp--preset--color--rust\)/s', $article ), 'in rust, never blood' );
ok( 1 === preg_match( '/body\.single-post:has\(\.wp-block-post-content > h2\.wp-block-heading:nth-of-type\(3\)\)\s*\.sn-article-toc__list > li:not\(:has\(> a\[href="#references"\]\)\)::before\s*\{[^}]*content:\s*counter\(sn-toc,\s*decimal-leading-zero\)\s*\/\s*""/s', $article ), 'the TOC carries the same numbers under the same gate' );

echo "\nGroup 3: prose list markers\n";
ok( 1 === preg_match( '/\.wp-block-post-content > ol\.wp-block-list:not\(\[class\*="is-style-"\]\)\s*\{[^}]*list-style-type:\s*decimal-leading-zero/s', $article ), 'plain ordered lists count 01.' );
ok( 1 === preg_match( '/\.wp-block-post-content > ul\.wp-block-list:not\(\[class\*="is-style-"\]\)\s*\{[^}]*list-style-type:\s*"\\\\2013/s', $article ), 'plain bullets become an en rule' );
ok( 1 === preg_match( '/:is\(ol, ul\)\.wp-block-list:not\(\[class\*="is-style-"\]\) > li::marker\s*\{[^}]*color:\s*var\(--wp--preset--color--rust\)/s', $article ), 'markers in rust; styled lists (references, checklist) keep their own' );

echo "\nGroup 5: print running head\n";
$print = $css( 'print.css' );
ok( 1 === preg_match( '/@page\s*\{[^@]*@top-left\s*\{[^}]*content:\s*var\(--sn-print-title,\s*""\)/s', $print ), 'the note title runs in the top-left margin' );
ok( 1 === preg_match( '/@bottom-right\s*\{[^}]*content:\s*counter\(page,\s*decimal-leading-zero\)\s*" \/ "\s*counter\(pages,\s*decimal-leading-zero\)/s', $print ), 'and 01 / 05 in the bottom-right' );
ok( 1 === preg_match( '/orphans:\s*3;\s*widows:\s*3/', $print ), 'no single stranded lines at a page break' );

$GLOBALS['__inline'] = array();
function add_action( $h, $c, $p = 10 ) { $GLOBALS['__actions'][ $h ][] = $c; }
function wp_style_is( $h, $s ) { return 'sn-print' === $h; }
function wp_add_inline_style( $h, $css ) { $GLOBALS['__inline'][ $h ] = $css; }
function is_singular( $t = '' ) { return true; }
function get_queried_object_id() { return 7; }
function get_the_title( $id ) { return $GLOBALS['__title']; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
require "$root/inc/print-running-head.php";
$GLOBALS['__title'] = 'Who&#8217;s signing? </style><script>alert(1)</script> "x" \\';
sn_print_running_head();
$inline = $GLOBALS['__inline']['sn-print'] ?? '';
ok( 0 === strpos( $inline, ':root{--sn-print-title:"' ), 'the title rides the print sheet as a custom property' );
ok( false === strpos( $inline, '<' ) && false === strpos( $inline, '\\\\ ' ) && 2 === substr_count( $inline, '"' ), 'every character outside a plain set is hex-escaped: no tag, quote or backslash can close the string or the style element' );
ok( false !== strpos( $inline, '\\002019' ), 'entities are decoded first, then escaped (the right single quote is \\002019)' );
ok( in_array( 'sn_print_running_head', $GLOBALS['__actions']['wp_enqueue_scripts'] ?? array(), true ), 'wired after the print sheet is enqueued' );

echo "\nGroup 6: red caret\n";
ok( 1 === preg_match( '/\.sn-notes-search input\[type="search"\]\s*\{[^}]*caret-color:\s*var\(--wp--preset--color--blood\)/s', $css( 'notes.css' ) ), '/notes search' );
ok( 1 === preg_match( '/\.sn-cmdk-input\s*\{[^}]*caret-color:\s*var\(--wp--preset--color--blood\)/s', $css( 'command-palette.css' ) ), 'the command palette' );
$comp = $css( 'components.css' );
ok( 1 === preg_match( '/\.atf-form \.atf-input\s*\{[^}]*caret-color:\s*var\(--wp--preset--color--blood\)/s', $comp ), 'the contact form fields' );
ok( 1 === preg_match( '/@supports\s*\(field-sizing:\s*content\)\s*\{\s*\.atf-form \.atf-textarea\s*\{[^}]*field-sizing:\s*content;[^}]*min-height:\s*calc\(5lh/s', $comp ), 'the message box grows with its text, from today\'s five-line height (rows stops counting under field-sizing)' );

echo "\nGroup 7: quiet fold\n";
$notes = $css( 'notes.css' );
ok( 1 === preg_match( '/\.sn-notes-month::details-content\s*\{[^}]*opacity:\s*0;[^}]*transition:\s*opacity 0\.12s linear,\s*content-visibility 0\.12s allow-discrete/s', $notes ), 'a collapsed month fades its rows in over 120ms, opacity only' );
ok( 1 === preg_match( '/\.sn-notes-month\[open\]::details-content\s*\{[^}]*opacity:\s*1/s', $notes ), '...to full opacity when open' );
ok( false === strpos( $notes, 'sn-note-counter' ), 'the orphaned sn-note-counter reset is gone: catalog numbers come from PHP, from the real published order' );

echo "\nGroup 8: fixes from the 2026-10-17 live sweep\n";
$article = $css( 'article.css' );
ok( 0 === preg_match( '/(^|\})\s*\.wp-block-post-content:has\(> h2\.wp-block-heading:nth-of-type\(3\)\)/', $article ), 'section numerals are never unscoped: /provenance (a page with four h2s and no TOC) showed 01 to 04' );
ok( 2 === preg_match_all( '/body\.single-post \.wp-block-post-content:has\(> h2\.wp-block-heading:nth-of-type\(3\)\)/', $article ), 'notes only, for the counter reset and the numbers' );
ok( 3 === preg_match_all( '/body\.single-post:has\(\.wp-block-post-content > h2\.wp-block-heading:nth-of-type\(3\)\) \.sn-article-toc__list/', $article ), '...and the TOC (its reset, its slots, its numbers) under the same gate' );
ok( 1 === preg_match( '/\.sn-article-toc__list > li::before\s*\{[^}]*content:\s*""\s*\/\s*"";[^}]*display:\s*inline-block;[^}]*width:\s*2\.6em/s', $article ), 'every TOC entry gets the same numeral slot, References an empty one, so all entries align (References sat 25px left)' );
ok( 1 === preg_match( '/\.wp-block-button:has\(> \.wp-block-button__link:focus-visible\)::before\s*\{[^}]*inset:\s*-9px/s', $css( 'base.css' ) ), 'crop marks sit 9px out: clear of the 2px outline and its 3px offset, off a neighbour 11px away' );
$print = $css( 'print.css' );
ok( 1 === preg_match( '/@page resume\s*\{\s*@top-left\s*\{\s*content:\s*none;?\s*\}\s*@top-right\s*\{\s*content:\s*none;?\s*\}\s*@bottom-right\s*\{\s*content:\s*none;?\s*\}\s*\}/s', $print ), 'the resume prints without the running head and folio (it printed RESUME / juanlentino.com / 01 / 03)' );
ok( 1 === preg_match( '/\.atf-form \.atf-textarea\s*\{[^}]*field-sizing:\s*content;[^}]*max-block-size:\s*70svh/s', $css( 'components.css' ) ), 'the contact box stops growing at 70% of the screen and scrolls inside (40 lines made it 1414px tall)' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
