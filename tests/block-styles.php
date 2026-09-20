<?php
/**
 * The four block style variations are theme.json partials, and they paint what
 * the PHP strings painted (#390).
 *
 * Until v13.4.0 inc/block-styles.php registered Hairline, Signal, Epigraph and
 * References with register_block_style() and a hand-concatenated inline_style
 * each. Core appended the string to the block's stylesheet and never parsed it,
 * so nothing that reads theme.json (Styles > Blocks in the Site Editor, the
 * global styles REST route, the schema) could see them. They now live in
 * styles/blocks/<slug>.json, the 6.6 partial form the eyebrow pair and caption
 * already use, which core reads through
 * WP_Theme_JSON_Resolver::get_style_variations( 'block' ).
 *
 * The public page must not move. This suite proves it three ways:
 *
 *   1. THE FIXTURE PIN. $old holds the four inline_style strings exactly as
 *      origin/main's PHP produced them (captured 2026-09-20 at 7f5e9e5, by
 *      running the file under a register_block_style stub). Each partial is
 *      compiled the way core compiles it (structured keys through
 *      WP_Theme_JSON::PROPERTIES_METADATA, the `css` key through
 *      process_blocks_custom_css, both read from class-wp-theme-json.php on
 *      the 7.1 branch), both sides expand their shorthands to longhands, and
 *      the declaration maps must be EQUAL per selector. Two named exceptions,
 *      each asserted on its own: the Hairline's border-top-color drops its
 *      !important (the base rule it fought lost its own, see 3), and the
 *      References print block moves to assets/css/print.css (core drops any
 *      `css` part with nested braces, so an at-rule inside a partial emits
 *      nothing).
 *
 *   2. THE SOURCE-ORDER PIN. Core prints a variation at :root :where(...),
 *      specificity 0-1-0, in block-style-variation-styles, which lands after
 *      global-styles and BEFORE the theme's own sheets. The PHP strings sat at
 *      0-2-0 and 0-3-0 and beat every theme rule on specificity; at 0-1-0 they
 *      lose to any later theme rule of equal specificity that touches the same
 *      property. So every screen rule in assets/css/*.css that matches one of
 *      the styled elements is checked against the variation's declarations.
 *      Against origin/main this finds components.css `.wp-block-separator
 *      { border-color: concrete !important; opacity: 0.6 }`, which would have
 *      faded the Hairline to 0.6.
 *
 *   3. THE BASE RULE. That separator rule now lives in theme.json
 *      styles.blocks.core/separator (border.color and `css: opacity:0.6`),
 *      where core prints it in global-styles before any variation, and the
 *      !important on it and on the Hairline both go.
 *
 * Run from theme root:  php tests/block-styles.php
 *
 * @since theme v9.10.0
 * @since theme v13.4.1 rewritten against the partials (#390)
 */

// SECURITY: CLI / WP-CLI only. Mirrors every other tests/*.php fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

require_once __DIR__ . '/lib/css-cascade.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$root  = dirname( __DIR__ );
$theme = json_decode( (string) file_get_contents( "$root/theme.json" ), true );

// ── The fixture: origin/main's inline_style strings, verbatim ──────────────
$old = array(
	'hairline'   => '.wp-block-separator.is-style-hairline{border:0;border-top:1px solid var(--wp--preset--color--concrete);border-top-color:var(--wp--preset--color--concrete) !important;opacity:1;height:0;margin-block:1.5rem;max-width:none;}',
	'signal'     => '.wp-block-quote.is-style-signal{border-left:4px solid var(--wp--preset--color--blood);background-color:var(--wp--preset--color--asphalt);color:var(--wp--preset--color--bone);padding:1rem 1.25rem;margin-block:1.5rem;font-style:normal;font-weight:600;}.wp-block-quote.is-style-signal cite{display:block;margin-top:0.5rem;font-style:normal;font-weight:400;font-size:max(0.75rem,11px);text-transform:uppercase;letter-spacing:0.08em;color:var(--wp--preset--color--rust);}',
	'epigraph'   => '.wp-block-quote.is-style-epigraph{border:0;border-left:1px solid var(--wp--preset--color--concrete);margin-block:clamp(1.5rem,3vw,2.5rem);margin-left:0 !important;padding:0.1rem 0 0.1rem 1.25rem;max-width:46ch;font-style:italic;font-size:max(0.95rem,14px);line-height:1.6;color:var(--wp--preset--color--rust);}.wp-block-quote.is-style-epigraph cite{display:block;margin-top:0.6rem;font-style:normal;font-size:max(0.7rem,11px);text-transform:uppercase;letter-spacing:0.08em;color:var(--wp--preset--color--rust);}',
	'references' => '.wp-block-list.is-style-references{list-style:none;margin-left:0;padding-left:0;font-size:max(0.85rem,12px);line-height:1.6;}.wp-block-list.is-style-references li{padding-left:1.5rem;text-indent:-1.5rem;margin-bottom:0.6rem;color:var(--wp--preset--color--bone);}.wp-block-list.is-style-references li a{overflow-wrap:break-word;}@media print{.wp-block-list.is-style-references li{break-inside:avoid;}.wp-block-list.is-style-references li a[href]::after{content:" (" attr(href) ")";font-size:0.85em;color:var(--wp--preset--color--rust);word-break:break-all;}}',
);
$block_of  = array( 'hairline' => 'core/separator', 'signal' => 'core/quote', 'epigraph' => 'core/quote', 'references' => 'core/list' );
$label_of  = array( 'hairline' => 'Hairline', 'signal' => 'Signal', 'epigraph' => 'Epigraph', 'references' => 'References' );
$block_sel = array( 'core/separator' => '.wp-block-separator', 'core/quote' => '.wp-block-quote', 'core/list' => '.wp-block-list' );

// ── Core, read not recalled ────────────────────────────────────────────────
// WP_Theme_JSON::PROPERTIES_METADATA, class-wp-theme-json.php on the 7.1
// branch, the entries a block style variation can carry (root padding,
// background image, duotone and aspect ratio omitted: none of the partials
// touches them). Order is core's order, which is the emit order.
$properties_metadata = array(
	'background-color'    => array( 'color', 'background' ),
	'border-radius'       => array( 'border', 'radius' ),
	'border-color'        => array( 'border', 'color' ),
	'border-width'        => array( 'border', 'width' ),
	'border-style'        => array( 'border', 'style' ),
	'border-top-color'    => array( 'border', 'top', 'color' ),
	'border-top-width'    => array( 'border', 'top', 'width' ),
	'border-top-style'    => array( 'border', 'top', 'style' ),
	'border-right-color'  => array( 'border', 'right', 'color' ),
	'border-right-width'  => array( 'border', 'right', 'width' ),
	'border-right-style'  => array( 'border', 'right', 'style' ),
	'border-bottom-color' => array( 'border', 'bottom', 'color' ),
	'border-bottom-width' => array( 'border', 'bottom', 'width' ),
	'border-bottom-style' => array( 'border', 'bottom', 'style' ),
	'border-left-color'   => array( 'border', 'left', 'color' ),
	'border-left-width'   => array( 'border', 'left', 'width' ),
	'border-left-style'   => array( 'border', 'left', 'style' ),
	'color'               => array( 'color', 'text' ),
	'text-align'          => array( 'typography', 'textAlign' ),
	'column-count'        => array( 'typography', 'textColumns' ),
	'font-family'         => array( 'typography', 'fontFamily' ),
	'font-size'           => array( 'typography', 'fontSize' ),
	'font-style'          => array( 'typography', 'fontStyle' ),
	'font-weight'         => array( 'typography', 'fontWeight' ),
	'letter-spacing'      => array( 'typography', 'letterSpacing' ),
	'line-height'         => array( 'typography', 'lineHeight' ),
	'margin'              => array( 'spacing', 'margin' ),
	'margin-top'          => array( 'spacing', 'margin', 'top' ),
	'margin-right'        => array( 'spacing', 'margin', 'right' ),
	'margin-bottom'       => array( 'spacing', 'margin', 'bottom' ),
	'margin-left'         => array( 'spacing', 'margin', 'left' ),
	'min-height'          => array( 'dimensions', 'minHeight' ),
	'min-width'           => array( 'dimensions', 'minWidth' ),
	'outline-color'       => array( 'outline', 'color' ),
	'outline-offset'      => array( 'outline', 'offset' ),
	'outline-style'       => array( 'outline', 'style' ),
	'outline-width'       => array( 'outline', 'width' ),
	'padding'             => array( 'spacing', 'padding' ),
	'padding-top'         => array( 'spacing', 'padding', 'top' ),
	'padding-right'       => array( 'spacing', 'padding', 'right' ),
	'padding-bottom'      => array( 'spacing', 'padding', 'bottom' ),
	'padding-left'        => array( 'spacing', 'padding', 'left' ),
	'text-decoration'     => array( 'typography', 'textDecoration' ),
	'text-shadow'         => array( 'typography', 'textShadow' ),
	'text-transform'      => array( 'typography', 'textTransform' ),
	'text-indent'         => array( 'typography', 'textIndent' ),
	'box-shadow'          => array( 'shadow' ),
	'height'              => array( 'dimensions', 'height' ),
	'width'               => array( 'dimensions', 'width' ),
	'writing-mode'        => array( 'typography', 'writingMode' ),
);

// The keys a variation may carry, from schemas.wp.org/wp/7.1/theme.json
// definitions.stylesVariationProperties (stylesProperties + elements + blocks),
// and the elements stylesElementsPropertiesComplete names.
$variation_keys = array( 'background', 'border', 'color', 'css', 'dimensions', 'filter', 'outline', 'shadow', 'spacing', 'typography', 'elements', 'blocks' );
$element_names  = array( 'button', 'link', 'heading', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'caption', 'cite', 'select', 'textInput' );

/**
 * WP_Theme_JSON::compute_style_properties, the part a partial exercises:
 * walk the property map, skip missing values and array values (longhands
 * carry those), keep "0" (numeric), convert the var: form the way
 * convert_custom_properties does. Returns [ [name, value], ... ] in emit order.
 */
function sn_bs_compute( $node, $map ) {
	$out = array();
	foreach ( $map as $prop => $path ) {
		$v = $node;
		foreach ( $path as $k ) {
			if ( ! is_array( $v ) || ! array_key_exists( $k, $v ) ) { $v = ''; break; }
			$v = $v[ $k ];
		}
		if ( ( '' === $v || null === $v ) || is_array( $v ) ) { continue; }
		if ( empty( $v ) && ! is_numeric( $v ) ) { continue; }
		$v = (string) $v;
		if ( 0 === strpos( $v, 'var:' ) ) {
			$v = 'var(--wp--' . str_replace( '|', '--', substr( $v, 4 ) ) . ')';
		}
		$out[] = array( $prop, $v );
	}
	return $out;
}

/**
 * WP_Theme_JSON::process_blocks_custom_css (7.1), verbatim apart from the two
 * selector helpers, which the single-selector case reduces to concatenation.
 * Returns the emitted CSS string.
 */
function sn_bs_process_custom_css( $css, $selector ) {
	$processed_css = '';
	if ( empty( $css ) ) { return $processed_css; }
	$parts = explode( '&', $css );
	foreach ( $parts as $part ) {
		if ( empty( $part ) ) { continue; }
		$is_root_css = ( ! str_contains( $part, '{' ) );
		if ( $is_root_css ) {
			$processed_css .= ':root :where(' . trim( $selector ) . '){' . trim( $part ) . '}';
		} else {
			$part = explode( '{', str_replace( '}', '', $part ) );
			if ( count( $part ) !== 2 ) { continue; }
			$nested_selector = $part[0];
			$css_value       = $part[1];
			$matches            = array();
			$has_pseudo_element = preg_match( '/([>+~\s]*::[a-zA-Z-]+)/', $nested_selector, $matches );
			$pseudo_part        = $has_pseudo_element ? $matches[1] : '';
			$nested_selector    = $has_pseudo_element ? str_replace( $pseudo_part, '', $nested_selector ) : $nested_selector;
			$part_selector  = str_starts_with( $nested_selector, ' ' )
				? $selector . ' ' . trim( $nested_selector )
				: $selector . $nested_selector;
			$final_selector = ":root :where($part_selector)$pseudo_part";
			$processed_css .= $final_selector . '{' . trim( $css_value ) . '}';
		}
	}
	return $processed_css;
}

/** "sel{a:b;c:d}sel2{...}" to [ sel => [ [a,b], [c,d] ], ... ], in source order. */
function sn_bs_rules( $css ) {
	$out = array();
	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER );
	foreach ( $m as $r ) {
		$sel = trim( $r[1] );
		foreach ( explode( ';', $r[2] ) as $d ) {
			if ( false === strpos( $d, ':' ) ) { continue; }
			list( $p, $v ) = explode( ':', $d, 2 );
			$out[ $sel ][] = array( trim( $p ), trim( $v ) );
		}
	}
	return $out;
}

/**
 * Longhand expansion, last declaration wins. sn_css_expand (tests/lib) does
 * margin and padding; border, border-<side>, border-width/style/color and
 * margin-block are added here. margin-block is read as top+bottom, the
 * horizontal-tb mapping, which is the only writing mode the theme sets.
 */
function sn_bs_expand( $decls ) {
	$sides = array( 'top', 'right', 'bottom', 'left' );
	$map   = array();
	foreach ( $decls as $d ) {
		list( $p, $v ) = $d;
		$imp = ( false !== strpos( $v, '!important' ) ) ? ' !important' : '';
		$bare = trim( str_replace( '!important', '', $v ) );
		$set = array();
		if ( 'margin-block' === $p ) {
			$set = array( 'margin-top' => $v, 'margin-bottom' => $v );
		} elseif ( preg_match( '/^border(?:-(top|right|bottom|left))?$/', $p, $bm ) ) {
			// border / border-<side>: width style color, in any order; `0` alone is width 0.
			$w = '0'; $s = 'none'; $c = 'currentcolor';
			foreach ( preg_split( '/\s+(?![^(]*\))/', $bare ) as $tok ) {
				if ( preg_match( '/^(none|hidden|solid|dashed|dotted|double|groove|ridge|inset|outset)$/', $tok ) ) { $s = $tok; }
				elseif ( preg_match( '/^(\d|\.\d|thin|medium|thick)/', $tok ) ) { $w = $tok; }
				elseif ( '' !== $tok ) { $c = $tok; }
			}
			$for = isset( $bm[1] ) && '' !== $bm[1] ? array( $bm[1] ) : $sides;
			foreach ( $for as $side ) {
				$set[ "border-$side-width" ] = $w . $imp;
				$set[ "border-$side-style" ] = $s . $imp;
				$set[ "border-$side-color" ] = $c . $imp;
			}
		} elseif ( preg_match( '/^border-(width|style|color)$/', $p, $bm ) ) {
			foreach ( $sides as $side ) { $set[ "border-$side-{$bm[1]}" ] = $v; }
		} else {
			$set = sn_css_expand( $p, $v );
		}
		foreach ( $set as $k => $val ) { $map[ $k ] = $val; }
	}
	ksort( $map );
	return $map;
}

/** Resolve the theme's own custom-property tokens so 1.6 and var(--wp--custom--line-height--snug) compare equal. */
function sn_bs_resolve( $map, $theme ) {
	foreach ( $map as $k => $v ) {
		if ( preg_match( '/^var\(--wp--custom--([a-z-]+)--([a-z-]+)\)$/', $v, $m ) ) {
			$group = lcfirst( str_replace( ' ', '', ucwords( str_replace( '-', ' ', $m[1] ) ) ) );
			$slug  = lcfirst( str_replace( ' ', '', ucwords( str_replace( '-', ' ', $m[2] ) ) ) );
			if ( isset( $theme['settings']['custom'][ $group ][ $slug ] ) ) {
				$map[ $k ] = (string) $theme['settings']['custom'][ $group ][ $slug ];
			}
		}
	}
	return $map;
}

echo "Block style variations as theme.json partials (#390)\n\n";

// ── Group 1: the PHP module is gone, the partials exist ───────────────────
echo "Group 1: the registration moved\n";
ok( ! file_exists( "$root/inc/block-styles.php" ), 'inc/block-styles.php no longer exists' );
$php_calls = 0;
foreach ( array_merge( (array) glob( "$root/inc/*.php" ), array( "$root/functions.php" ) ) as $f ) {
	$php_calls += preg_match_all( '/\bregister_block_style\s*\(/', (string) file_get_contents( $f ) );
}
ok( 0 === $php_calls, "no register_block_style() call in inc/*.php or functions.php (found $php_calls)" );
ok( false === strpos( (string) file_get_contents( "$root/functions.php" ), 'block-styles.php' ), 'functions.php does not require the deleted module' );

$partials = array();
foreach ( $old as $slug => $css ) {
	$path = "$root/styles/blocks/$slug.json";
	ok( file_exists( $path ), "styles/blocks/$slug.json exists" );
	$v = file_exists( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null;
	ok( is_array( $v ), "$slug.json parses" );
	if ( ! is_array( $v ) ) { continue; }
	$partials[ $slug ] = $v;
	ok( ( $v['slug'] ?? '' ) === $slug, "$slug: slug equals the basename" );
	ok( ( $v['title'] ?? '' ) === $label_of[ $slug ], "$slug: title is {$label_of[ $slug ]}, the label the PHP registered" );
	ok( ( $v['blockTypes'] ?? array() ) === array( $block_of[ $slug ] ), "$slug: blockTypes names {$block_of[ $slug ]} and nothing else" );
	ok( 3 === ( $v['version'] ?? 0 ), "$slug: version 3" );
	ok( array() === array_diff( array_keys( $v['styles'] ?? array() ), $variation_keys ),
		"$slug: every styles key is one the 7.1 schema names for a variation (" . implode( ', ', array_keys( $v['styles'] ?? array() ) ) . ')' );
	foreach ( array_keys( $v['styles']['elements'] ?? array() ) as $el ) {
		ok( in_array( $el, $element_names, true ), "$slug: element '$el' is a theme.json element" );
	}
	ok( ! isset( $v['isDefault'] ) && ! isset( $v['styles']['isDefault'] ), "$slug: opt-in, no default flag" );
}

// Every partial (the three typography ones too) pins its schema to the same
// version as theme.json, not to trunk.
$want_schema = $theme['$schema'] ?? '';
ok( 'https://schemas.wp.org/wp/7.1/theme.json' === $want_schema, 'theme.json pins the 7.1 schema (guard: the comparison below has a fixed target)' );
foreach ( (array) glob( "$root/styles/blocks/*.json" ) as $f ) {
	$j = json_decode( (string) file_get_contents( $f ), true );
	ok( ( $j['$schema'] ?? '' ) === $want_schema, basename( $f ) . ' carries the same $schema as theme.json' );
}
$hc = json_decode( (string) file_get_contents( "$root/styles/high-contrast.json" ), true );
ok( ( $hc['$schema'] ?? '' ) === $want_schema, 'styles/high-contrast.json carries the same $schema as theme.json' );

// ── Group 2: THE FIXTURE PIN, declarations equal per selector ─────────────
echo "\nGroup 2: the partial paints what the PHP string painted\n";
$new_css = array(); // slug => the CSS core emits, by selector role
foreach ( $partials as $slug => $v ) {
	$styles = $v['styles'];
	$sel    = $block_sel[ $block_of[ $slug ] ] . ".is-style-$slug";

	// Root: structured declarations, then the css key, each at :root :where(sel).
	$root_decls = sn_bs_compute( $styles, $properties_metadata );
	$root_css   = sn_bs_process_custom_css( $styles['css'] ?? '', $sel );
	$new_rules  = array();
	foreach ( $root_decls as $d ) { $new_rules[ $sel ][] = $d; }
	foreach ( sn_bs_rules( $root_css ) as $s => $ds ) {
		// :root :where(X) -> X, so the two sides key alike.
		$plain = preg_replace( '/^:root :where\((.*)\)$/', '$1', $s );
		foreach ( $ds as $d ) { $new_rules[ $plain ][] = $d; }
	}
	// Elements: core scopes each under the variation's instance class; keyed
	// here as "<sel> <element>" the way the PHP string wrote it.
	foreach ( $styles['elements'] ?? array() as $el => $node ) {
		foreach ( sn_bs_compute( $node, $properties_metadata ) as $d ) { $new_rules[ "$sel $el" ][] = $d; }
		foreach ( sn_bs_rules( sn_bs_process_custom_css( $node['css'] ?? '', "$sel $el" ) ) as $s => $ds ) {
			$plain = preg_replace( '/^:root :where\((.*)\)$/', '$1', $s );
			foreach ( $ds as $d ) { $new_rules[ $plain ][] = $d; }
		}
	}

	// The old side, minus the print block (pinned separately below).
	$old_screen = preg_replace( '/@media print\{.*\}\}$/s', '', $old[ $slug ] );
	$old_rules  = sn_bs_rules( $old_screen );

	ok( array_keys( $old_rules ) === array_keys( $new_rules ),
		"$slug: same selectors, same order: [" . implode( ' | ', array_keys( $new_rules ) ) . ']' );

	foreach ( $old_rules as $s => $ds ) {
		$o = sn_bs_resolve( sn_bs_expand( $ds ), $theme );
		$n = sn_bs_resolve( sn_bs_expand( $new_rules[ $s ] ?? array() ), $theme );
		if ( 'hairline' === $slug && $sel === $s ) {
			// Named exception 1: the !important goes with the base rule it fought.
			ok( ( $o['border-top-color'] ?? '' ) === 'var(--wp--preset--color--concrete) !important'
				&& ( $n['border-top-color'] ?? '' ) === 'var(--wp--preset--color--concrete)',
				'hairline: border-top-color keeps its value and drops the !important the old base rule forced' );
			$o['border-top-color'] = $n['border-top-color'] ?? '';
		}
		$diff = array();
		foreach ( array_unique( array_merge( array_keys( $o ), array_keys( $n ) ) ) as $k ) {
			if ( ( $o[ $k ] ?? null ) !== ( $n[ $k ] ?? null ) ) {
				$diff[] = $k . ': ' . var_export( $o[ $k ] ?? null, true ) . ' -> ' . var_export( $n[ $k ] ?? null, true );
			}
		}
		ok( array() === $diff, "$slug: $s paints the same " . count( $o ) . ' longhands' . ( $diff ? ' (' . implode( '; ', $diff ) . ')' : '' ) );
	}
	$new_css[ $slug ] = $new_rules;
}

// !important survives only where the cascade still needs it: the Epigraph's
// margin-left against the constrained layout's `margin-left: auto !important`,
// also 0-1-0, also in global-styles, which core prints before any variation.
foreach ( $partials as $slug => $v ) {
	$imp = preg_match_all( '/!important/', json_encode( $v ) );
	if ( 'epigraph' === $slug ) {
		ok( 1 === $imp && false !== strpos( $v['styles']['css'] ?? '', 'margin-left:0 !important' ),
			'epigraph: exactly one !important, margin-left:0, in the css key (the layout rule it beats is !important too)' );
	} else {
		ok( 0 === $imp, "$slug: no !important anywhere" );
	}
}

// Named exception 2: the References print block lives in print.css now.
echo "\nGroup 3: the References print rule moved to print.css\n";
$print = (string) file_get_contents( "$root/assets/css/print.css" );
$print = preg_replace( '~/\*.*?\*/~s', '', $print );
$print_rules = sn_bs_rules( $print );
preg_match( '/@media print\{(.*)\}$/s', $old['references'], $pm );
$old_print = sn_bs_rules( $pm[1] ?? '' );
ok( 2 === count( $old_print ), 'fixture: the old print block had two rules (guard: the loop below has subjects)' );
foreach ( $old_print as $s => $ds ) {
	$o = sn_bs_expand( $ds );
	$n = sn_bs_expand( $print_rules[ $s ] ?? array() );
	ok( isset( $print_rules[ $s ] ) && $o === $n, "print.css carries `$s` with the same " . count( $o ) . ' declarations' );
}
foreach ( $partials as $slug => $v ) {
	$all_css = json_encode( $v );
	ok( false === strpos( $all_css, '@media' ) && false === strpos( $all_css, '@' ),
		"$slug: no at-rule inside a css key (core drops any part with nested braces)" );
}
$enq = (string) file_get_contents( "$root/inc/assets-frontend.php" );
ok( 1 === preg_match( "/'sn-print',.*?'print'\s*\)/s", $enq ), 'print.css is still enqueued with media=print' );

// ── Group 4: THE SOURCE-ORDER PIN ─────────────────────────────────────────
echo "\nGroup 4: no theme sheet re-paints a variation's property at 0-1-0 or above\n";
// The styled elements as they sit on a Note: body > main.sn-note-single >
// div.wp-block-post-content (templates/single.html) > the block.
$seed = array(
	array( 'body', array( 'single-post' ) ),
	array( 'main', array( 'wp-block-group', 'sn-note-single', 'is-layout-constrained' ) ),
	array( 'div', array( 'entry-content', 'wp-block-post-content', 'is-layout-constrained' ) ),
);
$targets = array(
	'hairline'   => array( array( 'hr', array( 'wp-block-separator', 'is-style-hairline' ) ) ),
	'signal'     => array( array( 'blockquote', array( 'wp-block-quote', 'is-style-signal' ) ), array( 'cite', array() ) ),
	'epigraph'   => array( array( 'blockquote', array( 'wp-block-quote', 'is-style-epigraph' ) ), array( 'cite', array() ) ),
	'references' => array( array( 'ul', array( 'wp-block-list', 'is-style-references' ) ), array( 'li', array() ), array( 'a', array() ) ),
);
$screen = sn_css_harvest_rules( $root );
ok( count( $screen ) > 500, 'harvested ' . count( $screen ) . ' screen rules from assets/css/ (guard: the sweep has subjects)' );
$examined = 0; $skipped = array();
foreach ( $targets as $slug => $chain_tail ) {
	$rules = $new_css[ $slug ] ?? array();
	$sel   = $block_sel[ $block_of[ $slug ] ] . ".is-style-$slug";
	$chain = $seed;
	foreach ( $chain_tail as $i => $node ) {
		$chain[] = $node;
		// The variation rule that paints THIS element: the root for the block,
		// "<sel> <tag>" (or "<sel> li a") for the descendants.
		$key = 0 === $i ? $sel : $sel . ' ' . implode( ' ', array_map( function ( $n ) { return $n[0]; }, array_slice( $chain_tail, 1, $i ) ) );
		if ( ! isset( $rules[ $key ] ) ) { continue; }
		$props = array_keys( sn_bs_expand( $rules[ $key ] ) );
		$fights = array();
		foreach ( $screen as $r ) {
			if ( 'screen' !== $r['media'] && false === strpos( $r['media'], 'min-width' ) && false === strpos( $r['media'], 'max-width' ) ) { continue; }
			$m = sn_css_selector_matches( $r['sel'], $chain );
			if ( null === $m ) {
				if ( preg_match( '/is-style-|wp-block-(separator|quote|list)\b|\bcite\b/', $r['sel'] ) ) { $skipped[] = $r['sheet'] . ':' . $r['line'] . ' ' . $r['sel']; }
				continue;
			}
			if ( ! $m ) { continue; }
			$examined++;
			$spec = sn_css_specificity( $r['sel'] );
			if ( $spec[0] === 0 && $spec[1] === 0 ) { continue; } // below 0-1-0: the variation wins on specificity
			$theirs = array();
			foreach ( $r['decls'] as $p => $v ) { $theirs = array_merge( $theirs, array_keys( sn_bs_expand( array( array( $p, $v ) ) ) ) ); }
			$shared = array_intersect( $props, $theirs );
			if ( $shared ) { $fights[] = $r['sheet'] . ':' . $r['line'] . ' ' . $r['sel'] . ' {' . implode( ', ', $shared ) . '}'; }
		}
		ok( array() === $fights, "$slug: `$key` is not re-painted by a later theme rule at 0-1-0 or above" . ( $fights ? ' (' . implode( '; ', $fights ) . ')' : '' ) );
	}
}
ok( $examined > 0, "the sweep matched $examined rule/element pairs (guard: a zero here would make Group 4 vacuous)" );
echo '  skipped (selectors the resolver cannot judge, listed so the silence is honest): ' . ( $skipped ? implode( '; ', array_unique( $skipped ) ) : 'none' ) . "\n";

// ── Group 5: THE BASE RULE lives in theme.json now ────────────────────────
echo "\nGroup 5: the separator base rule is theme.json's, printed before any variation\n";
$sep = $theme['styles']['blocks']['core/separator'] ?? array();
ok( ( $sep['border']['color'] ?? '' ) === 'var(--wp--preset--color--concrete)', 'theme.json core/separator: border.color is concrete' );
ok( ( $sep['border']['width'] ?? '' ) === '1px' && ( $sep['color']['text'] ?? '' ) === 'var(--wp--preset--color--concrete)', 'theme.json core/separator: the pre-existing width and text color are untouched' );
ok( isset( $sep['css'] ) && 1 === preg_match( '/^opacity:\s*0?\.6;?$/', $sep['css'] ), 'theme.json core/separator: css carries the 0.6 opacity the old base rule set' );
ok( false === strpos( json_encode( $sep ), '!important' ), 'theme.json core/separator: no !important' );
$components = preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( "$root/assets/css/components.css" ) );
ok( 0 === preg_match( '/(^|[\s,}])\.wp-block-separator\s*[{,]/m', $components ), 'components.css has no .wp-block-separator rule' );
$article = (string) file_get_contents( "$root/assets/css/article.css" );
preg_match( '/\.sn-post-closing__rule\s*\{([^}]*)\}/s', $article, $cm );
ok( isset( $cm[1] ) && false === strpos( $cm[1], '!important' ), '.sn-post-closing__rule carries no !important (nothing important is left to fight)' );
ok( isset( $cm[1] ) && 1 === preg_match( '/border-top:\s*1px solid var\(--wp--preset--color--bone\)/', $cm[1] ) && 1 === preg_match( '/opacity:\s*1\b/', $cm[1] ),
	'.sn-post-closing__rule still paints bone at full opacity (#320), now by source order' );
// The theme's sheets print after global-styles: both are enqueued on
// wp_enqueue_scripts at the default priority, and core hooks its callback
// first (default-filters.php loads before functions.php).
ok( 1 === preg_match( "/add_action\(\s*'wp_enqueue_scripts',\s*function\(\)\s*\{[^}]*'sn-styles'/s", $enq ), 'sn-styles is enqueued on wp_enqueue_scripts at the default priority, after core enqueues global-styles' );

// ── Group 6: fluid typography cannot re-derive a partial's font size ──────
echo "\nGroup 6: fluid typography leaves these sizes alone\n";
ok( ! empty( $theme['settings']['typography']['fluid'] ), 'theme.json has fluid typography on (guard: the check below is live)' );
foreach ( $partials as $slug => $v ) {
	$sizes = array();
	if ( isset( $v['styles']['typography']['fontSize'] ) ) { $sizes[ $slug ] = $v['styles']['typography']['fontSize']; }
	foreach ( $v['styles']['elements'] ?? array() as $el => $node ) {
		if ( isset( $node['typography']['fontSize'] ) ) { $sizes[ "$slug $el" ] = $node['typography']['fontSize']; }
	}
	foreach ( $sizes as $where => $size ) {
		// wp_get_typography_value_and_unit() bails on anything but <number><unit>;
		// a bare rem or px above 14px would ship as a clamp() (archive, 12.6.0).
		ok( 0 === preg_match( '/^(\d*\.?\d+)([a-zA-Z]+|%)$/', $size ), "$where: font-size `$size` is not a value fluid typography can coerce" );
	}
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
