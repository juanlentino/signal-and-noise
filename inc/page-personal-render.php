<?php
/**
 * Signal & Noise — /contact/personal page, full PHP render.
 *
 * Emits a complete HTML document for the Personal page: the honest, spare note
 * that synchronous-time requests are a no, and why. Server-rendered; usable with
 * JS off. Included only by the route handler in inc/page-personal-template.php.
 *
 * The page body is authored as block markup in sn_personal_content_blocks()
 * (THIS IS THE EDIT SURFACE) and rendered through do_blocks(), so it inherits the
 * theme's typography, colour, spacing and layout presets with no bespoke CSS —
 * the masthead mirrors templates/page-contact.html, and the Casey-Neistat credit
 * footnote uses the smallest font-size preset (`small`) + the muted colour preset
 * (`rust`). ONE contact channel in the body (LinkedIn) — the deliberate
 * scraper/effort friction — plus two outbound REFERENCE links (Paul Graham's
 * "Maker's Schedule" + Ryan Holiday's essay) that make the case for the no; the
 * URL to this page on /contact is itself a worded link, not a contact channel.
 *
 * Mirrors inc/page-uses-render.php (the /about/uses precedent): header + footer
 * are pre-rendered via do_blocks BEFORE wp_head() so their block-layout CSS
 * registers with the style engine in time, and the postless route forces HTTP
 * 200 (WORDPRESS-REFERENCE gotcha #40).
 *
 * @package SignalNoise
 * @since 10.12.0
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

/**
 * Build marker — surfaces as an HTML comment so the deployed version of this
 * file can be verified from the live site:
 * curl -s …/contact/personal | grep sn-personal-build
 */
const SN_PERSONAL_BUILD = '2026-06-18-personal-v01';

/**
 * The Personal page body, as serialized block markup (the edit surface).
 *
 * Pure: returns a literal string, no WP calls — so the content contract
 * (exactly one link, the footnote presets, the credit) is unit-testable without
 * a WordPress load. Rendered through do_blocks() by the document below. The
 * masthead + body groups mirror templates/page-contact.html so the child page is
 * visually continuous with its parent.
 *
 * @return string Serialized Gutenberg block markup (the <main> group).
 */
function sn_personal_content_blocks() {
	return <<<'BLOCKS'
<!-- wp:group {"tagName":"main","layout":{"type":"default"}} -->
<main class="wp-block-group">

	<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|30","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"backgroundColor":"void","layout":{"type":"constrained","contentSize":"760px"}} -->
	<div class="wp-block-group has-void-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--40)">

		<!-- wp:paragraph {"style":{"typography":{"fontSize":"0.75rem","letterSpacing":"0.3em","textTransform":"uppercase"}},"textColor":"blood","fontFamily":"body"} -->
		<p class="has-blood-color has-text-color has-body-font-family" style="font-size:0.75rem;letter-spacing:0.3em;text-transform:uppercase">Dossier · Personal</p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"clamp(3rem, 7vw, 5.5rem)","lineHeight":"1"}}} -->
		<h1 class="wp-block-heading" style="font-size:clamp(3rem, 7vw, 5.5rem);line-height:1">PERSONAL</h1>
		<!-- /wp:heading -->

	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"sn-prose-links","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"backgroundColor":"void","layout":{"type":"constrained","contentSize":"760px"}} -->
	<div class="wp-block-group sn-prose-links has-void-background-color has-background" style="padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)">

		<!-- wp:paragraph {"style":{"typography":{"fontSize":"1rem","lineHeight":"1.8"}},"textColor":"bone","fontFamily":"body"} -->
		<p class="has-bone-color has-text-color has-body-font-family" style="font-size:1rem;line-height:1.8">If you want to say hello or share something you're working on, <a href="https://www.linkedin.com/in/juanlentino/" target="_blank" rel="noopener">LinkedIn</a> is the right place. I see what comes in there, and I read it when I can.</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"style":{"typography":{"fontSize":"1rem","lineHeight":"1.8"}},"textColor":"bone","fontFamily":"body"} -->
		<p class="has-bone-color has-text-color has-body-font-family" style="font-size:1rem;line-height:1.8">If you want a coffee, time on the calendar, an introduction to someone in my network, feedback on a track or a deck, advice on a career move, mentorship, a collaboration, or any version of a request that needs my synchronous time, the answer is no.</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"style":{"typography":{"fontSize":"1rem","lineHeight":"1.8"}},"textColor":"bone","fontFamily":"body"} -->
		<p class="has-bone-color has-text-color has-body-font-family" style="font-size:1rem;line-height:1.8">The no costs me too. A lot of what lands here is legitimate, and some of it I would want to take in another life. That doesn't change the answer.</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"style":{"typography":{"fontSize":"1rem","lineHeight":"1.8"}},"textColor":"bone","fontFamily":"body"} -->
		<p class="has-bone-color has-text-color has-body-font-family" style="font-size:1rem;line-height:1.8">The reason is structural. I run Panacea Studio. I'm building a couple of music infrastructure projects alone. I'm finishing an MBA in August 2026. After all of that, the week is already spent. The hours that remain are few, and they don't take appointments.</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"style":{"typography":{"fontSize":"1rem","lineHeight":"1.8"}},"textColor":"bone","fontFamily":"body"} -->
		<p class="has-bone-color has-text-color has-body-font-family" style="font-size:1rem;line-height:1.8">Two people argued this better than I can. Paul Graham, on why a single meeting fractures a <a href="https://www.paulgraham.com/makersschedule.html" target="_blank" rel="noopener">maker's whole day</a>, not just the hour it takes. And Ryan Holiday, on the real arithmetic of <a href="https://ryanholiday.net/to-everyone-who-asks-for-just-a-little-of-your-time-heres-what-it-costs-to-say-yes/" target="_blank" rel="noopener">"just a little" of your time</a> once everyone asks for it. If the no still reads as cold, read them.</p>
		<!-- /wp:paragraph -->

		<!-- wp:spacer {"height":"var:preset|spacing|40"} -->
		<div style="height:var(--wp--preset--spacing--40)" aria-hidden="true" class="wp-block-spacer"></div>
		<!-- /wp:spacer -->

		<!-- wp:paragraph {"textColor":"rust","fontSize":"small","fontFamily":"body"} -->
		<p class="has-rust-color has-text-color has-small-font-size has-body-font-family">The structure here is borrowed from Casey Neistat's contact page. He worked out the honest version of this first.</p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:group -->

</main>
<!-- /wp:group -->
BLOCKS;
}

if ( defined( 'SN_PERSONAL_RENDER_TEST' ) && SN_PERSONAL_RENDER_TEST ) {
	return;
}

// ── BEGIN PAGE OUTPUT ──────────────────────────────────────────────────
// Pre-render header / main / footer BEFORE wp_head() so their block-layout CSS
// registers with the style engine in time (the /uses + /index two-pass).
ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own header part); escaping would corrupt the markup.
echo do_blocks( '<!-- wp:template-part {"slug":"header","area":"header"} /-->' );
$sn_personal_header_html = ob_get_clean();

ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output of theme-authored block markup (sn_personal_content_blocks); must not be escaped.
echo do_blocks( sn_personal_content_blocks() );
$sn_personal_main_html = ob_get_clean();

ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own footer part); must not be escaped.
echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
$sn_personal_footer_html = ob_get_clean();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'sn-personal-body' ); ?>>
<?php wp_body_open(); ?>

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- captured do_blocks() header markup; must not be re-escaped.
echo $sn_personal_header_html;
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- captured do_blocks() main markup; must not be re-escaped.
echo $sn_personal_main_html;
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- captured do_blocks() footer markup; must not be re-escaped.
echo $sn_personal_footer_html;
?>

<?php wp_footer(); ?>
<!-- sn-personal-build: <?php echo esc_html( SN_PERSONAL_BUILD ); ?> -->
</body>
</html>
