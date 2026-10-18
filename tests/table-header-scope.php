<?php
/**
 * Standalone test: the comparison table's header cells get scope="col" on
 * render (inc/table-header-scope.php).
 *
 * Measured 2026-10-16 on WordPress 7.1: a table built in the block editor
 * saves <th> with no scope (core's table state never sets it and the UI has
 * no control for it), so a convention that required scope="col" in stored
 * markup was one its author could not meet. The house style now guarantees
 * it at render, through core's HTML API, and only for is-style-sn-table.
 *
 * Run: php tests/table-header-scope.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
$GLOBALS['__filters'] = array();
function add_filter( $h, $c, $p = 10, $a = 1 ) { $GLOBALS['__filters'][ $h ][] = $c; }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); } }
require_once __DIR__ . '/lib/wp-html-api.php';
snt_require_wp_html_api();
require_once __DIR__ . '/../inc/table-header-scope.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

$editor_made = '<figure class="wp-block-table is-style-sn-table"><table class="has-fixed-layout"><thead><tr><th>Identifier</th><th>What it names</th></tr></thead><tbody><tr><th>ISRC</th><td>One recording</td></tr></tbody></table></figure>';
$styled      = array( 'blockName' => 'core/table', 'attrs' => array( 'className' => 'is-style-sn-table' ) );

ok( in_array( 'sn_table_header_scope', $GLOBALS['__filters']['render_block_core/table'] ?? array(), true ), 'hooked on render_block_core/table' );
$out = sn_table_header_scope( $editor_made, $styled );
ok( 2 === substr_count( $out, '<th scope="col">' ), 'both header cells in <thead> gain scope="col" (' . $out . ')' );
ok( false !== strpos( $out, '<tbody><tr><th>ISRC</th>' ), 'a <th> in <tbody> (a row header) is left alone: it is not a column header' );
ok( strip_tags( $out ) === strip_tags( $editor_made ), 'the text is untouched (nothing the ledger signs changes)' );
$kept = sn_table_header_scope( str_replace( '<th>Identifier</th>', '<th scope="colgroup">Identifier</th>', $editor_made ), $styled );
ok( false !== strpos( $kept, 'scope="colgroup"' ) && 1 === substr_count( $kept, 'scope="col"' ), 'an author-set scope is kept, never overwritten' );
ok( $editor_made === sn_table_header_scope( $editor_made, array( 'blockName' => 'core/table', 'attrs' => array() ) ), 'an unstyled table is left exactly as stored' );
ok( $editor_made === sn_table_header_scope( $editor_made, array( 'blockName' => 'core/table', 'attrs' => array( 'className' => 'is-style-sn-table-x' ) ) ), 'a class that merely starts with the style name is not the style' );
$no_head = '<figure class="wp-block-table is-style-sn-table"><table><tbody><tr><td>a</td></tr></tbody></table></figure>';
ok( $no_head === sn_table_header_scope( $no_head, $styled ), 'no <thead>: nothing to do, returned unchanged' );

echo "\nGroup 2: the scroll box is keyboard reachable (WCAG 2.1.1)\n";
// On a phone the comparison table scrolls sideways inside its figure. Chrome
// and Firefox make a scroll container focusable on their own; Safari does not,
// so a keyboard reader there could never scroll a wide table. The figure gets
// tabindex="0" and a name, which is what axe's scrollable-region-focusable
// rule asks for. No role="region": ARIA in HTML allows no role but a few doc
// roles on a <figure> that has a <figcaption>, and axe's aria-allowed-role
// flagged it on the live sweep (2026-10-18). The figure's own role carries the
// name; the keyboard reach comes from tabindex, not from the role.
ok( in_array( 'sn_table_scroll_region', $GLOBALS['__filters']['render_block_core/table'] ?? array(), true ), 'the region filter is hooked on render_block_core/table' );
$cap = '<figure class="wp-block-table is-style-sn-table"><table><thead><tr><th>A</th></tr></thead><tbody><tr><td>1</td></tr></tbody></table><figcaption class="wp-element-caption">Three identifiers, <em>three</em> things &amp; more</figcaption></figure>';
$out = sn_table_scroll_region( $cap, $styled );
$p = new WP_HTML_Tag_Processor( $out ); $p->next_tag( 'figure' );
ok( '0' === $p->get_attribute( 'tabindex' ), 'the styled figure gets tabindex="0"' );
ok( null === $p->get_attribute( 'role' ), 'and no role: role="region" is not allowed on a figure with a figcaption (axe aria-allowed-role)' );
ok( 'Three identifiers, three things & more' === $p->get_attribute( 'aria-label' ), 'named by its caption, tags stripped and entities decoded once (' . $p->get_attribute( 'aria-label' ) . ')' );
ok( strip_tags( $out ) === strip_tags( $cap ), 'the text is untouched (attributes only)' );
$nocap = sn_table_scroll_region( '<figure class="wp-block-table is-style-sn-table"><table><tbody><tr><td>1</td></tr></tbody></table></figure>', $styled );
$p = new WP_HTML_Tag_Processor( $nocap ); $p->next_tag( 'figure' );
ok( 'Comparison table' === $p->get_attribute( 'aria-label' ) && null === $p->get_attribute( 'role' ), 'with no caption the focusable figure still has a name, and still no role' );
$own = sn_table_scroll_region( '<figure class="wp-block-table is-style-sn-table" tabindex="-1"><table></table></figure>', $styled );
ok( false !== strpos( $own, 'tabindex="-1"' ) && false === strpos( $own, 'aria-label' ), 'a figure that already sets tabindex is left as the author made it' );
ok( $editor_made !== sn_table_scroll_region( $editor_made, $styled ) && $cap === sn_table_scroll_region( $cap, array( 'blockName' => 'core/table', 'attrs' => array() ) ), 'only the comparison style is changed; an unstyled table is left exactly as stored' );
$hostile = sn_table_scroll_region( '<figure class="wp-block-table is-style-sn-table"><table></table><figcaption>x" onfocus="alert(1)</figcaption></figure>', $styled );
preg_match( '/<figure[^>]*>/', $hostile, $fig );
ok( false === strpos( $fig[0], 'onfocus="' ) && false !== strpos( $fig[0], 'aria-label="x&quot; onfocus=&quot;alert(1)"' ), 'a caption with a quote cannot break out of the figure\'s attribute (the HTML API escapes it): ' . $fig[0] );
$css = (string) file_get_contents( __DIR__ . '/../assets/css/article.css' );
ok( 1 === preg_match( '/\.wp-block-table\.is-style-sn-table:focus-visible\s*\{[^}]*outline:\s*2px solid var\(--wp--preset--color--blood\)/', $css ), 'the focused region shows the house ring (2px blood), since base.css rings only links, buttons and their roles' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
