<?php
/**
 * The dynamic-block RENDER CONTRACT — enforcement for sn-normalize-v2.
 *
 * The plugin's provenance normalizer (sn-normalize-v2, signal-and-noise-tools
 * v13.4.0) signs the text of every VOID signal-noise/* block by expanding its
 * top-level STRING attributes, in the serialized JSON's order, empty strings
 * skipped, as paragraphs. Public verification then compares that signed prose
 * BYTE-EQUAL against the rendered page. The whole scheme therefore rests on a
 * contract this theme must uphold:
 *
 *   A dynamic block whose text lives in attributes must RENDER exactly its
 *   string attributes' text, in attribute order, empty slots omitted, and
 *   nothing else. A block that renders text derived from anywhere else
 *   (site state, queries, meta — pillar-essays is the standing example) can
 *   never appear in a SIGNED subject's content: its rendered words cannot be
 *   in the signed record by construction.
 *
 * This suite drives every blocks/[star]/block.json + render.php pair — the REAL
 * files, never a re-typed copy — and fails the moment a block breaks the
 *  contract, which is authoring time, not verification time. A future block
 * that obeys it is signed on registration with zero plugin edits.
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── Stubs the render.php files require ───────────────────────────────────
if ( ! function_exists( 'get_block_wrapper_attributes' ) ) {
	function get_block_wrapper_attributes( $extra = array() ) {
		return 'class="' . ( $extra['class'] ?? '' ) . '"';
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	// Faithful-enough: kses keeps inline markup and text; the comparison below
	// strips tags anyway, so only text survival matters here.
	function wp_kses_post( $s ) { return (string) $s; }
}

/**
 * "Same words in the same order" comparison form — tags stripped, entities
 * decoded once, whitespace collapsed. Mirrors the flatten semantics the
 * plugin's integrity sweep and /verify JS use for text equivalence.
 */
function rp_text( $s ) {
	$s = preg_replace( '/<!--.*?-->/s', '', (string) $s );
	// Restore block boundaries before stripping — the REAL verification chain
	// does exactly this (extract-content.mjs restoreBlockEndings) because
	// rendered HTML carries no source newlines; without it, adjacent
	// </p><p> would concatenate words that are separate in the signed prose.
	$s = preg_replace( '#</(p|h[1-6]|blockquote|li|ul|ol|figure)>#i', "$0\n\n", $s );
	$s = strip_tags( $s );
	$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

/** Render one block's render.php with $attributes in scope; return output. */
function rp_render( $render_file, $attributes ) {
	ob_start();
	( static function () use ( $render_file, $attributes ) {
		include $render_file;
	} )();
	return (string) ob_get_clean();
}

echo "blocks/ render parity — the sn-normalize-v2 contract\n\n";

$block_jsons = glob( __DIR__ . '/../blocks/*/block.json' );
ok( count( $block_jsons ) >= 3, 'found the blocks/ directory (' . count( $block_jsons ) . ' block.json files)' );

// pillar-essays is the documented exception: NO text-bearing attributes, and
// its render derives from site state — it must never appear in a signed
// subject's content. The pin below fails if it ever GROWS string attributes
// without joining the contract.
$derived_render_blocks = array( 'signal-noise/pillar-essays' );

foreach ( $block_jsons as $bj_path ) {
	$bj   = json_decode( (string) file_get_contents( $bj_path ), true );
	$name = (string) ( $bj['name'] ?? basename( dirname( $bj_path ) ) );
	$render_rel = (string) ( $bj['render'] ?? '' );
	ok( is_array( $bj ), "$name: block.json parses" );
	ok( 0 === strpos( $name, 'signal-noise/' ), "$name: stays in the signal-noise namespace (the v2 expansion rule keys on it)" );
	ok( 0 === strpos( $render_rel, 'file:' ), "$name: dynamic (render file declared) — attribute text must come through render.php" );

	$string_attrs = array();
	foreach ( (array) ( $bj['attributes'] ?? array() ) as $attr_name => $def ) {
		if ( 'string' === ( $def['type'] ?? '' ) ) {
			$string_attrs[ $attr_name ] = $def;
		}
	}

	if ( in_array( $name, $derived_render_blocks, true ) ) {
		ok( array() === $string_attrs, "$name: the derived-render exception carries NO string attributes (it can never appear in a signed subject; growing one means joining the contract instead)" );
		continue;
	}

	if ( array() === $string_attrs ) {
		continue; // nothing text-bearing to verify
	}

	$render_file = dirname( $bj_path ) . '/' . substr( $render_rel, strlen( 'file:./' ) );
	ok( file_exists( $render_file ), "$name: render.php exists" );

	// Fixture values: the block.json example when present (the author's own
	// canonical usage), else a per-attribute marker string.
	$example = (array) ( $bj['example']['attributes'] ?? array() );
	$values  = array();
	foreach ( $string_attrs as $attr_name => $def ) {
		$values[ $attr_name ] = isset( $example[ $attr_name ] ) && '' !== $example[ $attr_name ]
			? (string) $example[ $attr_name ]
			: "Fixture text for {$attr_name} attribute.";
	}

	// CONTRACT 1: render output's text == the string attrs' text, in
	// declaration order — exactly what sn-normalize-v2 will sign.
	$rendered = rp_render( $render_file, $values );
	$expected = rp_text( implode( "\n\n", array_values( $values ) ) );
	ok( rp_text( $rendered ) === $expected, "$name: renders EXACTLY its string attributes' text in attribute order (signed prose == rendered prose)" );

	// CONTRACT 2: an empty slot is OMITTED (matches v2 skipping '' values —
	// otherwise signed and rendered prose diverge on partially-filled blocks).
	foreach ( array_keys( $values ) as $omit ) {
		$partial          = $values;
		$partial[ $omit ] = '';
		$rendered_partial = rp_render( $render_file, $partial );
		$expected_partial = rp_text( implode( "\n\n", array_values( array_filter( $partial, static function ( $v ) { return '' !== $v; } ) ) ) );
		ok( rp_text( $rendered_partial ) === $expected_partial, "$name: empty '{$omit}' renders NO stray text (slot omission mirrored both sides)" );
	}

	// CONTRACT 3: declaration order in block.json is the order the editor
	// serializes — pin the JSON key order matches what render emits (already
	// covered by contract 1, but assert the example agrees when present).
	if ( array() !== $example ) {
		ok( array_keys( array_intersect_key( $example, $string_attrs ) ) === array_values( array_intersect( array_keys( $string_attrs ), array_keys( $example ) ) ), "$name: example attribute order agrees with declaration order" );
	}
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
