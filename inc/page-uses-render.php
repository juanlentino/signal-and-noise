<?php
/**
 * Signal & Noise — /uses gear page, full PHP render (D6).
 *
 * Emits a complete HTML document listing the kit behind the work, grouped by
 * category, in the brutalist row idiom. Server-rendered; usable with JS off.
 * Included only by the /uses route handler in inc/page-uses-template.php. The
 * gear data comes from inc/uses-data.php (sn_uses_groups), filterable.
 *
 * Every field is escaped at output. Standalone-safe: an empty group list (e.g. a
 * filter that returns nothing) renders the page with no sections, not a fatal.
 *
 * @package SignalNoise
 * @since 10.10.0
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

require_once __DIR__ . '/uses-data.php';

/**
 * Build marker — surfaces as an HTML comment so the deployed version of this
 * file can be verified from the live site: curl -s …/about/uses | grep sn-uses-build
 */
const SN_USES_BUILD = '2026-06-15-uses-v01';

/**
 * Render one gear item. Pure + escapes every field, so callers pass raw strings.
 * The optional note renders as a quiet qualifier after the name.
 *
 * @param string $name Item name (raw).
 * @param string $note Optional qualifier (raw).
 * @return string Item HTML (one <li>).
 */
function sn_uses_render_item( $name, $note = '' ) {
	$out  = '<li class="sn-uses-item">';
	$out .= '<span class="sn-uses-item-name">' . esc_html( $name ) . '</span>';
	if ( '' !== (string) $note ) {
		$out .= '<span class="sn-uses-item-note">' . esc_html( $note ) . '</span>';
	}
	$out .= '</li>';
	return $out;
}

if ( defined( 'SN_USES_RENDER_TEST' ) && SN_USES_RENDER_TEST ) {
	return;
}

// ── BEGIN PAGE OUTPUT ──────────────────────────────────────────────────
$sn_uses_groups = sn_uses_groups();
$sn_uses_count  = sn_uses_item_count();

// Pre-render header/footer BEFORE wp_head() so their block-layout CSS registers
// with the style engine in time (the /notes + /index two-pass).
ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own header part); escaping would corrupt the markup.
echo do_blocks( '<!-- wp:template-part {"slug":"header","area":"header"} /-->' );
$sn_uses_header_html = ob_get_clean();

ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own footer part); must not be escaped.
echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
$sn_uses_footer_html = ob_get_clean();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'sn-uses-body' ); ?>>
<?php wp_body_open(); ?>

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- captured do_blocks() header markup; must not be re-escaped.
echo $sn_uses_header_html;
?>

<main class="sn-uses-page" id="wp--skip-link--target">

	<header class="sn-uses-hero">
		<p class="sn-uses-eyebrow">Uses &middot; The kit behind the work</p>
		<h1 class="sn-uses-headline">Uses.</h1>
		<p class="sn-uses-dek">The hardware and software I actually reach for &mdash; the studio, the instruments, and the tools that keep the signal clean.</p>
		<p class="sn-uses-meta"><?php echo esc_html( sprintf( _n( '%d item', '%d items', $sn_uses_count, 'signal-noise' ), $sn_uses_count ) ); ?></p>
	</header>

	<?php foreach ( $sn_uses_groups as $sn_uses_i => $sn_uses_group ) : ?>
	<section class="sn-uses-section" aria-labelledby="sn-uses-h-<?php echo (int) $sn_uses_i; ?>">
		<div class="sn-uses-section-head">
			<h2 class="sn-uses-section-label" id="sn-uses-h-<?php echo (int) $sn_uses_i; ?>"><?php echo esc_html( $sn_uses_group['label'] ); ?></h2>
			<span class="sn-uses-section-count"><?php echo esc_html( sprintf( '%02d', count( $sn_uses_group['items'] ) ) ); ?></span>
		</div>
		<ul class="sn-uses-list">
			<?php foreach ( $sn_uses_group['items'] as $sn_uses_item ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sn_uses_render_item() escapes every field internally.
				echo sn_uses_render_item( $sn_uses_item['name'], $sn_uses_item['note'] );
			} ?>
		</ul>
	</section>
	<?php endforeach; ?>

</main>

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- captured do_blocks() footer markup; must not be re-escaped.
echo $sn_uses_footer_html;
?>

<?php wp_footer(); ?>
<!-- sn-uses-build: <?php echo esc_html( SN_USES_BUILD ); ?> -->
</body>
</html>
