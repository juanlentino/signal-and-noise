<?php
/**
 * Tests: the header and footer in Spanish on Spanish pages (inc/lang-chrome.php,
 * inc/lang-chrome-footer.php), and English pages untouched.
 */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; } else { $fail++; echo "FAIL: $label\n"; }
}
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function add_filter() {}
function __( $s ) { return $s; }

require __DIR__ . '/../inc/lang-toggle.php';
require __DIR__ . '/../inc/lang-chrome.php';

echo "Group: a header nav link\n";
$li = '<li class="wp-block-navigation-item"><a class="wp-block-navigation-item__content" href="/music"><span class="wp-block-navigation-item__label">Music</span></a></li>';
$es = sn_lang_nav_link_html( $li, 'Music', '/music', 'https://x.test/musica/', '/musica' );
ok( false !== strpos( $es, '>Música</span>' ), 'the label is Spanish' );
ok( false !== strpos( $es, 'href="https://x.test/musica/"' ), 'it points at the published Spanish page' );
ok( false !== strpos( $es, '<a aria-current="page"' ), 'aria-current marks the Spanish page being viewed' );
$notes = sn_lang_nav_link_html( str_replace( array( '/music', 'Music' ), array( '/notes', 'Notes' ), $li ), 'Notes', '/notes', '', '/musica' );
ok( false !== strpos( $notes, '>Notas</span>' ) && false !== strpos( $notes, 'href="/notes"' ), 'a page with no Spanish version keeps its English href under a Spanish label' );
ok( false === strpos( $notes, 'aria-current' ), 'not current: no aria-current' );
ok( $li === sn_lang_nav_link_html( $li, 'Gallery', '/gallery', '', '/musica' ), 'an unknown link is left alone' );

echo "\nGroup: the chrome strings\n";
$logo = '<a href="/" class="sn-logo-link" aria-label="Juan Lentino — home">';
ok( '<a href="https://x.test/inicio/" class="sn-logo-link" aria-label="Juan Lentino, inicio">' === sn_lang_chrome_strings( $logo, 'https://x.test/inicio/' ), 'the logo goes to /inicio/ and says so' );
ok( false !== strpos( sn_lang_chrome_strings( '<nav aria-label="Main navigation"><button aria-label="Open menu"></button><button aria-label="Close menu"></button></nav>', '' ), 'aria-label="Navegación principal"><button aria-label="Abrir el menú"></button><button aria-label="Cerrar el menú">' ), 'the nav and menu buttons are named in Spanish' );
ok( false !== strpos( sn_lang_chrome_strings( '<span class="sn-cmdk-trigger-label">Search</span>', '' ), '>Buscar<' ), 'Search becomes Buscar' );

echo "\nGroup: the footer\n";
$nav = (string) shell_exec( 'php -r ' . escapeshellarg( 'define("ABSPATH","/"); require "' . __DIR__ . '/../blocks/meta-nav/render.php";' ) );
$es_nav = sn_lang_meta_nav( $nav );
ok( '' !== $nav && 5 === substr_count( $es_nav, 'hreflang="en"' ), 'all five meta links switch, each marked hreflang="en"' );
ok( false !== strpos( $es_nav, 'title="Colofón"' ) && false !== strpos( $es_nav, '(en inglés)' ), 'labels are Spanish and say the page is English' );
ok( false === strpos( $nav, 'Colofón' ), 'the English meta-nav is untouched (the pinned bytes render without the plugin)' );
$footer = (string) file_get_contents( __DIR__ . '/../parts/footer.html' );
ok( false === strpos( $footer, 'lang-toggle' ) && false === strpos( $footer, 'sn-lang-footer' ), 'the footer carries no language link: the header switch is the one place' );
ok( ! function_exists( 'sn_lang_footer_link_html' ), 'the footer link builder is gone' );
ok( 'Light' === sn_theme_t( 'Light', 'Claro' ), 'without the plugin, the theme speaks English' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail ? 1 : 0 );
