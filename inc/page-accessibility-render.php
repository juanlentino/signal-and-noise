<?php
/**
 * Signal & Noise — /accessibility statement page, full PHP render.
 *
 * Emits a complete HTML document with the accessibility statement. The
 * content model (sn_a11y_sections) is static render code — every claim maps
 * to genuinely shipped work; update it alongside the features it describes.
 * OWNER GATE (spec 2026-07-01): owner copy-reviews before merge.
 *
 * Every field is escaped at output. Included only by the /accessibility
 * route handler in inc/page-accessibility-template.php.
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

/**
 * Build marker — surfaces as an HTML comment so the deployed version of this
 * file can be verified from the live site: curl -s …/accessibility | grep sn-a11y-build
 */
const SN_A11Y_BUILD = '2026-07-01-a11y-v01';

/**
 * The statement content: sections of { label, paragraphs[] }. Static —
 * every claim below maps to shipped work; update alongside the features.
 * The Feedback section's /contact mention renders as a real link via
 * sn_a11y_render_feedback_link() (built in code, never from data strings).
 *
 * @return array<int,array{label:string,paragraphs:array<int,string>}>
 */
function sn_a11y_sections() {
	return array(
		array(
			'label'      => 'Commitment',
			'paragraphs' => array(
				'This site should be readable and operable by everyone. Accessibility is treated as part of the design system, not an afterthought: the same care that goes into typography goes into focus order.',
			),
		),
		array(
			'label'      => 'Conformance target',
			'paragraphs' => array(
				'The target is WCAG 2.1 AA. The site is self-assessed against it; no third-party audit has been commissioned yet.',
			),
		),
		array(
			'label'      => 'What is in place',
			'paragraphs' => array(
				'Semantic HTML with landmark structure (header, main, footer, labelled sections) on every page, including the theme\'s virtual pages.',
				'Full keyboard navigation: a skip link to the content, visible focus states, j/k previous/next movement on notes, and a "?" keyboard cheat-sheet.',
				'Motion respects prefers-reduced-motion in both CSS and JS. Transitions and the reading-progress animation are disabled when you ask for reduced motion.',
				'Windows High Contrast / forced-colors mode is supported: the keyboard focus ring pins to the system Highlight colour and the reading-progress fill stays a system colour.',
				'A contrast-disciplined black-on-white palette with an 11px minimum type floor.',
				'An editorial pipeline that flags missing image alt text before publishing.',
			),
		),
		array(
			'label'      => 'Known limitations',
			'paragraphs' => array(
				'Some embedded SVG diagrams in older notes may not carry complete text alternatives yet; they are being backfilled.',
				'Third-party embeds (for example Spotify players) ship their own markup and may not meet AA on their own.',
			),
		),
		array(
			'label'      => 'Feedback',
			'paragraphs' => array(
				'If anything here is hard to read or operate, please say so via the /contact page. Reports are fixed with the same priority as broken links.',
			),
		),
		array(
			'label'      => 'About this statement',
			'paragraphs' => array(
				'Prepared 2026-07-01. Revised whenever an accessibility-affecting change ships; the theme changelog records those releases.',
			),
		),
	);
}

/**
 * The /contact anchor for the Feedback section — built in code so the data
 * strings above stay plain text (escaped wholesale at the sink).
 *
 * @return string
 */
function sn_a11y_render_feedback_link() {
	return '<a href="' . esc_url( home_url( '/contact/' ) ) . '">' . esc_html( 'the contact page' ) . '</a>';
}

/**
 * Render one statement section. Pure + escapes every field at the sink.
 * The Feedback section's "/contact page" text is upgraded to a real link
 * AFTER escaping (the anchor is code-built, never data).
 *
 * @param string   $label      Section heading (raw).
 * @param string[] $paragraphs Body paragraphs (raw).
 * @param int      $i          Section index (heading anchor).
 * @return string
 */
function sn_a11y_render_section( $label, $paragraphs, $i = 0 ) {
	$out  = '<section class="sn-a11y-section" aria-labelledby="sn-a11y-h-' . (int) $i . '">';
	$out .= '<h2 class="sn-a11y-section-label" id="sn-a11y-h-' . (int) $i . '">' . esc_html( $label ) . '</h2>';
	foreach ( (array) $paragraphs as $p ) {
		$escaped = esc_html( (string) $p );
		// Upgrade the literal "/contact page" mention to a real link. String
		// replacement runs on ESCAPED text; the anchor itself is code-built.
		$escaped = str_replace( 'the /contact page', sn_a11y_render_feedback_link(), $escaped );
		$out    .= '<p class="sn-a11y-paragraph">' . $escaped . '</p>';
	}
	$out .= '</section>';
	return $out;
}

if ( defined( 'SN_A11Y_RENDER_TEST' ) && SN_A11Y_RENDER_TEST ) {
	return;
}

// ── BEGIN PAGE OUTPUT ──────────────────────────────────────────────────
$sn_a11y_sections = sn_a11y_sections();

// Pre-render header/footer BEFORE wp_head() so their block-layout CSS registers
// with the style engine in time (the /notes + /index two-pass).
ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own header part); escaping would corrupt the markup.
echo do_blocks( '<!-- wp:template-part {"slug":"header","area":"header"} /-->' );
$sn_a11y_header_html = ob_get_clean();

ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own footer part); must not be escaped.
echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
$sn_a11y_footer_html = ob_get_clean();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'sn-a11y-body' ); ?>>
<?php wp_body_open(); ?>

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- captured do_blocks() header markup; must not be re-escaped.
echo $sn_a11y_header_html;
?>

<main class="sn-a11y-page" id="wp--skip-link--target">

	<header class="sn-a11y-hero">
		<p class="sn-a11y-eyebrow">Accessibility &middot; Statement</p>
		<h1 class="sn-a11y-headline">Accessibility.</h1>
		<p class="sn-a11y-dek">How this site is built to be readable and operable by everyone &mdash; what is in place, what falls short, and how to tell me.</p>
	</header>

	<?php foreach ( $sn_a11y_sections as $sn_a11y_i => $sn_a11y_section ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sn_a11y_render_section() escapes internally.
		echo sn_a11y_render_section( $sn_a11y_section['label'], $sn_a11y_section['paragraphs'], (int) $sn_a11y_i );
	} ?>

</main>

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- captured do_blocks() footer markup; must not be re-escaped.
echo $sn_a11y_footer_html;
?>

<?php wp_footer(); ?>
<!-- sn-a11y-build: <?php echo esc_html( SN_A11Y_BUILD ); ?> -->
</body>
</html>
