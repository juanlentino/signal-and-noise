<?php
/**
 * Test: the closing rule (`.sn-post-closing__rule`) keeps its own color
 * against the separator base rule (issue #320, re-pinned in #390).
 *
 * Until #390 `components.css` set `.wp-block-separator { border-color:
 * concrete !important; opacity: .6 }`, and an `!important` declaration beats a
 * plain one regardless of specificity, so `article.css` had to answer with
 * `border-top-color: bone !important` of its own. The base rule now lives in
 * theme.json `styles.blocks.core/separator` (border.color and `css:
 * opacity:0.6`), which core prints in global-styles at `:root :where(...)`,
 * specificity 0-1-0, BEFORE the theme's own sheets. `.sn-post-closing__rule`
 * is 0-1-0 too and prints later, so bone at full opacity wins on source order
 * and neither side needs `!important`. The markup still carries both classes
 * (parts/post-closing.html): `<hr class="wp-block-separator ...
 * sn-post-closing__rule"/>`.
 *
 * Run: php tests/post-closing-rule-color.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$root       = dirname( __DIR__ );
$article    = (string) file_get_contents( $root . '/assets/css/article.css' );
$components = preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( $root . '/assets/css/components.css' ) );
$theme      = json_decode( (string) file_get_contents( $root . '/theme.json' ), true );

ok( '' !== $article && '' !== $components && is_array( $theme ), 'article.css, components.css and theme.json are readable' );

// The base rule is theme.json's now, and carries no !important.
$sep = $theme['styles']['blocks']['core/separator'] ?? array();
ok( ( $sep['border']['color'] ?? '' ) === 'var(--wp--preset--color--concrete)' && isset( $sep['css'] ) && 1 === preg_match( '/^opacity:\s*0?\.6;?$/', (string) $sep['css'] ),
	'theme.json core/separator paints concrete at 60% opacity, the old components.css base rule' );
ok( false === strpos( json_encode( $sep ), '!important' ), 'the base rule carries no !important' );
ok( 0 === preg_match( '/(^|[\s,}])\.wp-block-separator\s*[{,]/m', $components ), 'components.css no longer carries a .wp-block-separator rule' );

// #320: the closing rule still wins, now by source order at equal specificity.
preg_match( '/\.sn-post-closing__rule\s*\{([^}]*)\}/s', $article, $m );
$rule = $m[1] ?? '';
ok( '' !== $rule, '.sn-post-closing__rule is still a rule in article.css' );
ok( 1 === preg_match( '/border-top:\s*1px solid var\(--wp--preset--color--bone\)/', $rule ), '#320: .sn-post-closing__rule sets a 1px bone top border' );
ok( 1 === preg_match( '/opacity:\s*1\b/', $rule ), '#320: .sn-post-closing__rule resets opacity to 1, undoing the base rule\'s 60%' );
ok( false === strpos( $rule, '!important' ), 'the closing rule carries no !important: with the base rule un-important, source order is enough' );

// The theme's sheets print after global-styles: both enqueue on
// wp_enqueue_scripts at the default priority and core's callback is hooked
// first. article.css must also still load AFTER components.css so the
// (now unneeded) tie-break keeps resolving in the closing rule's favor.
$assets_frontend = (string) file_get_contents( $root . '/inc/assets-frontend.php' );
ok( 1 === preg_match( "/add_action\(\s*'wp_enqueue_scripts',\s*function\(\)\s*\{[^}]*'sn-styles'/s", $assets_frontend ),
	'sn-styles is enqueued on wp_enqueue_scripts at the default priority' );
ok( strpos( $assets_frontend, "'sn-article" ) !== false && strpos( $assets_frontend, "array( 'sn-responsive' )" ) !== false,
	'article.css still depends on (and therefore loads after) the components stylesheet chain' );

echo sprintf( "\nResult: %d passed, %d failed.\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
