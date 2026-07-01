<?php
/**
 * Signal & Noise — /now page, full PHP render.
 *
 * Emits a complete HTML document listing what I'm focused on right now,
 * grouped by section, in the brutalist row idiom. Server-rendered; usable
 * with JS off. Included only by the /now route handler in
 * inc/page-now-template.php. The content comes from inc/now-data.php
 * (sn_now_sections), filterable; the page renders its own updated date so
 * staleness stays honest.
 *
 * Every field is escaped at output. Standalone-safe: an empty section list
 * (e.g. a filter that returns nothing) renders the page with no sections,
 * not a fatal.
 *
 * @package SignalNoise
 * @since 10.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Force HTTP 200 for the postless virtual route: WP's handle_404() ran in
// $wp->main() (before template_redirect) and committed a 404. Without this the
// page would serve its body under a 404 and crawlers would ignore it
// (WORDPRESS-REFERENCE gotcha #40). Placed before the test guard so the suite
// can assert it without executing the full document.
if ( function_exists( 'status_header' ) ) {
	status_header( 200 );
}

require_once __DIR__ . '/now-data.php';

/**
 * Build marker — surfaces as an HTML comment so the deployed version of this
 * file can be verified from the live site: curl -s …/now | grep sn-now-build
 */
const SN_NOW_BUILD = '2026-07-01-now-v01';

/**
 * Render one /now item. Pure + escapes at the sink, so callers pass raw strings.
 *
 * @param string $text Item text (raw).
 * @return string Item HTML (one <li>).
 */
function sn_now_render_item( $text ) {
	return '<li class="sn-now-item"><span class="sn-now-item-text">' . esc_html( $text ) . '</span></li>';
}

if ( defined( 'SN_NOW_RENDER_TEST' ) && SN_NOW_RENDER_TEST ) {
	return;
}

// ── BEGIN PAGE OUTPUT ──────────────────────────────────────────────────
$sn_now_sections = sn_now_sections();

// Pre-render header/footer BEFORE wp_head() so their block-layout CSS registers
// with the style engine in time (the /notes + /index two-pass).
ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own header part); escaping would corrupt the markup.
echo do_blocks( '<!-- wp:template-part {"slug":"header","area":"header"} /-->' );
$sn_now_header_html = ob_get_clean();

ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own footer part); must not be escaped.
echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
$sn_now_footer_html = ob_get_clean();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'sn-now-body' ); ?>>
<?php wp_body_open(); ?>

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- captured do_blocks() header markup; must not be re-escaped.
echo $sn_now_header_html;
?>

<main class="sn-now-page" id="wp--skip-link--target">

	<header class="sn-now-hero">
		<p class="sn-now-eyebrow">Now &middot; What I&rsquo;m focused on</p>
		<h1 class="sn-now-headline">Now.</h1>
		<p class="sn-now-dek">A public answer to &ldquo;what are you doing these days?&rdquo; &mdash; the projects, writing, and inputs that have my attention right now.</p>
		<p class="sn-now-meta">Updated <?php echo esc_html( sn_now_updated() ); ?></p>
	</header>

	<?php foreach ( $sn_now_sections as $sn_now_i => $sn_now_section ) : ?>
	<section class="sn-now-section" aria-labelledby="sn-now-h-<?php echo (int) $sn_now_i; ?>">
		<div class="sn-now-section-head">
			<h2 class="sn-now-section-label" id="sn-now-h-<?php echo (int) $sn_now_i; ?>"><?php echo esc_html( $sn_now_section['label'] ); ?></h2>
			<span class="sn-now-section-count"><?php echo esc_html( sprintf( '%02d', count( $sn_now_section['items'] ) ) ); ?></span>
		</div>
		<ul class="sn-now-list">
			<?php foreach ( $sn_now_section['items'] as $sn_now_item ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sn_now_render_item() escapes internally.
				echo sn_now_render_item( $sn_now_item );
			} ?>
		</ul>
	</section>
	<?php endforeach; ?>

</main>

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- captured do_blocks() footer markup; must not be re-escaped.
echo $sn_now_footer_html;
?>

<?php wp_footer(); ?>
<!-- sn-now-build: <?php echo esc_html( SN_NOW_BUILD ); ?> -->
</body>
</html>
