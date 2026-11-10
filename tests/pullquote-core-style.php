<?php
/**
 * Tests: core/pullquote carries the house pull-quote (2026-11-10).
 *
 * The custom signal-noise/pull-quote block stays registered, so anything
 * already written with it renders exactly as before, but it leaves the
 * inserter: the house look is now a block style on core/pullquote
 * (styles/blocks/pull-quote.json, is-style-pull-quote), painted by the same
 * article.css rules, and the editor offers a transform from the old block to
 * core/pullquote. Nothing is migrated in stored content.
 *
 * Run: php tests/pullquote-core-style.php
 */
if ( PHP_SAPI !== 'cli' ) { exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$root = dirname( __DIR__ );

$v = json_decode( (string) @file_get_contents( "$root/styles/blocks/pull-quote.json" ), true );
ok( is_array( $v ) && 'pull-quote' === ( $v['slug'] ?? '' ) && array( 'core/pullquote' ) === ( $v['blockTypes'] ?? null ), 'styles/blocks/pull-quote.json is a core/pullquote style, slug pull-quote' );

$css = (string) file_get_contents( "$root/assets/css/article.css" );
ok( 1 === preg_match( '/\.wp-block-post-content \.sn-pull-quote,\s*\.wp-block-post-content \.wp-block-pullquote\.is-style-pull-quote\s*\{/', $css ), 'the box rule (rules, ground, bleed) paints the core style beside the custom block' );
ok( 1 === preg_match( '/\.sn-pull-quote__body,\s*\.wp-block-pullquote\.is-style-pull-quote blockquote p\s*\{/', $css ), 'the body rule paints the quote text' );
ok( 1 === preg_match( '/\.sn-pull-quote__attribution,\s*\.wp-block-pullquote\.is-style-pull-quote cite\s*\{/', $css ), 'the attribution rule paints the citation' );

$bj = json_decode( (string) file_get_contents( "$root/blocks/pull-quote/block.json" ), true );
ok( false === ( $bj['supports']['inserter'] ?? true ), 'the custom block leaves the inserter' );
ok( array( 'body', 'attribution' ) === array_keys( $bj['attributes'] ?? array() ) && is_file( "$root/blocks/pull-quote/render.php" ), 'and stays registered with the same attributes and render, so written content renders as before' );

$js = (string) file_get_contents( "$root/blocks/editor.js" );
ok( 1 === preg_match( "/blocks: \\[ 'core\\/pullquote' \\]/", $js ) && false !== strpos( $js, "createBlock( 'core/pullquote', { value: a.body, citation: a.attribution, className: 'is-style-pull-quote' } )" ), 'the editor offers a transform to core/pullquote that keeps body, attribution and the style' );

$pat = (string) file_get_contents( "$root/patterns/pull-quote.php" );
ok( false !== strpos( $pat, 'Slug: signal-noise/pull-quote' ) && false !== strpos( $pat, 'Block Types: core/pullquote' ), 'the pattern keeps its slug and is offered for core/pullquote' );
ok( 1 === preg_match( '#<!-- wp:pullquote \{"className":"is-style-pull-quote"\} -->\s*<figure class="wp-block-pullquote is-style-pull-quote"><blockquote><p>[^<]+</p><cite>[^<]+</cite></blockquote></figure>\s*<!-- /wp:pullquote -->#', $pat ) && false === strpos( $pat, 'sn-pattern-pull-quote' ), "the pattern is core/pullquote's saved markup with the style, not the group scaffold" );
$ec = (string) file_get_contents( "$root/inc/editorial-conventions.php" );
ok( false !== strpos( $ec, "'selector'         => 'is-style-pull-quote'" ), 'editorial conventions name the new selector' );
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
