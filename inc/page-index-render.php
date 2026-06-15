<?php
/**
 * Signal & Noise — /index whole-site dossier, full PHP render (C3).
 *
 * Emits a complete HTML document aggregating the site into one brutalist
 * dossier: Notes (post_type=post), Pages (post_type=page), and the
 * discography (sn_discography_entries filter), each as a tabular list reusing
 * the row idiom. Server-rendered; usable with JS off. Included only by the
 * /index route handler in inc/page-index-template.php.
 *
 * Standalone-safe: the music section reads the same cross-package filter the
 * /music page uses, so it is empty (and omitted) when the plugin is absent.
 * Every external-data field is escaped at output.
 *
 * @package SignalNoise
 * @since 10.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Force HTTP 200 for the postless virtual route: WP's handle_404() ran in
// $wp->main() (before template_redirect) and committed a 404. Without this the
// dossier would serve its body under a 404 and crawlers would ignore it
// (WORDPRESS-REFERENCE gotcha #40). Placed before the test guard so the suite
// can assert it without executing the full document.
if ( function_exists( 'status_header' ) ) {
	status_header( 200 );
}

/**
 * Build marker — surfaces as an HTML comment so the deployed version of this
 * file can be verified from the live site: curl -s …/index | grep sn-index-build
 */
const SN_INDEX_BUILD = '2026-06-14-index-v01';

/**
 * All published notes, newest first. posts_per_page is capped (filterable) so a
 * runaway corpus can't render an unbounded page.
 *
 * @return WP_Query
 */
function sn_index_notes_query() {
	return new WP_Query( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => (int) apply_filters( 'sn_index_max_notes', 300 ),
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	) );
}

/**
 * All published standalone Pages, A–Z.
 *
 * @return WP_Query
 */
function sn_index_pages_query() {
	return new WP_Query( array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => (int) apply_filters( 'sn_index_max_pages', 100 ),
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	) );
}

/**
 * Normalized discography entries off the cross-package filter (same source as
 * the /music page). [] when the plugin is absent or unsynced.
 *
 * @return array<int,array<string,mixed>>
 */
function sn_index_music_entries() {
	$entries = apply_filters( 'sn_discography_entries', array() );
	return is_array( $entries ) ? $entries : array();
}

/**
 * Render one dossier row. Pure + escapes every field here, so callers pass raw
 * strings. $spec is the mono left column (date / year / blank); $sub an optional
 * dek line; external links open in a new tab with rel=noopener.
 *
 * @param string $spec     Left-column label (raw).
 * @param string $title    Row title (raw).
 * @param string $href     Link target (raw URL); '' renders an unlinked title.
 * @param string $sub      Optional sub-line (raw).
 * @param bool   $external Whether the link is off-site.
 * @return string Row HTML (one <li>).
 */
function sn_index_render_row( $spec, $title, $href, $sub = '', $external = false ) {
	$rel = $external ? ' target="_blank" rel="noopener"' : '';
	$out = '<li class="sn-index-row">';
	$out .= '<div class="sn-index-row-spec">' . esc_html( $spec ) . '</div>';
	$out .= '<div class="sn-index-row-content">';
	if ( '' !== (string) $href ) {
		$out .= '<h3 class="sn-index-row-title"><a href="' . esc_url( $href ) . '"' . $rel . '>' . esc_html( $title ) . '</a></h3>';
	} else {
		$out .= '<h3 class="sn-index-row-title">' . esc_html( $title ) . '</h3>';
	}
	if ( '' !== (string) $sub ) {
		$out .= '<p class="sn-index-row-sub">' . esc_html( $sub ) . '</p>';
	}
	$out .= '</div></li>';
	return $out;
}

if ( defined( 'SN_INDEX_RENDER_TEST' ) && SN_INDEX_RENDER_TEST ) {
	return;
}

// ── BEGIN PAGE OUTPUT ──────────────────────────────────────────────────
$sn_notes = sn_index_notes_query();
$sn_pages = sn_index_pages_query();
$sn_music = sn_index_music_entries();

$sn_notes_n = count( $sn_notes->posts );
$sn_pages_n = count( $sn_pages->posts );
$sn_music_n = count( $sn_music );
$sn_total   = $sn_notes_n + $sn_pages_n + $sn_music_n;

// Pre-render header/footer BEFORE wp_head() so their block-layout CSS registers
// with the style engine in time (the /notes two-pass; see page-notes-render.php).
ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own header part); escaping would corrupt the markup.
echo do_blocks( '<!-- wp:template-part {"slug":"header","area":"header"} /-->' );
$sn_header_html = ob_get_clean();

ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own footer part); must not be escaped.
echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
$sn_footer_html = ob_get_clean();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'sn-index-body' ); ?>>
<?php wp_body_open(); ?>

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- captured do_blocks() header markup; must not be re-escaped.
echo $sn_header_html;
?>

<main class="sn-index-page" id="wp--skip-link--target">

	<header class="sn-index-hero">
		<p class="sn-index-eyebrow">Index &middot; The whole site on one page</p>
		<h1 class="sn-index-headline">Index.</h1>
		<p class="sn-index-dek">Everything published here &mdash; notes, pages, and the discography &mdash; collected into a single dossier.</p>
		<p class="sn-index-meta"><?php echo esc_html( sprintf( _n( '%d entry', '%d entries', $sn_total, 'signal-noise' ), $sn_total ) ); ?></p>
	</header>

	<?php if ( $sn_notes_n ) : ?>
	<section class="sn-index-section" aria-labelledby="sn-index-notes-h">
		<div class="sn-index-section-head">
			<h2 class="sn-index-section-label" id="sn-index-notes-h">Notes</h2>
			<span class="sn-index-section-count"><?php echo esc_html( sprintf( '%02d', $sn_notes_n ) ); ?></span>
		</div>
		<ol class="sn-index-list">
			<?php foreach ( $sn_notes->posts as $sn_p ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sn_index_render_row() escapes every field internally.
				echo sn_index_render_row( get_the_date( 'Y.m.d', $sn_p ), get_the_title( $sn_p ), get_permalink( $sn_p ) );
			} ?>
		</ol>
	</section>
	<?php endif; ?>

	<?php if ( $sn_pages_n ) : ?>
	<section class="sn-index-section" aria-labelledby="sn-index-pages-h">
		<div class="sn-index-section-head">
			<h2 class="sn-index-section-label" id="sn-index-pages-h">Pages</h2>
			<span class="sn-index-section-count"><?php echo esc_html( sprintf( '%02d', $sn_pages_n ) ); ?></span>
		</div>
		<ol class="sn-index-list">
			<?php foreach ( $sn_pages->posts as $sn_pg ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sn_index_render_row() escapes every field internally.
				echo sn_index_render_row( '', get_the_title( $sn_pg ), get_permalink( $sn_pg ) );
			} ?>
		</ol>
	</section>
	<?php endif; ?>

	<?php if ( $sn_music_n ) : ?>
	<section class="sn-index-section" aria-labelledby="sn-index-music-h">
		<div class="sn-index-section-head">
			<h2 class="sn-index-section-label" id="sn-index-music-h">Discography</h2>
			<span class="sn-index-section-count"><?php echo esc_html( sprintf( '%02d', $sn_music_n ) ); ?></span>
		</div>
		<ol class="sn-index-list">
			<?php foreach ( $sn_music as $sn_m ) {
				$sn_year   = isset( $sn_m['year'] ) ? (string) (int) $sn_m['year'] : '';
				$sn_roles  = ( isset( $sn_m['roles'] ) && is_array( $sn_m['roles'] ) ) ? implode( ' · ', array_map( 'strval', $sn_m['roles'] ) ) : '';
				$sn_artist = (string) ( $sn_m['artist'] ?? '' );
				$sn_sub    = trim( $sn_artist . ( ( '' !== $sn_artist && '' !== $sn_roles ) ? ' — ' : '' ) . $sn_roles );
				$sn_href   = (string) ( $sn_m['spotify_url'] ?? '' );
				if ( '' === $sn_href ) {
					$sn_href = (string) ( $sn_m['muso_url'] ?? '' );
				}
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sn_index_render_row() escapes every field internally.
				echo sn_index_render_row( $sn_year, (string) ( $sn_m['title'] ?? '' ), $sn_href, $sn_sub, true );
			} ?>
		</ol>
	</section>
	<?php endif; ?>

</main>

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- captured do_blocks() footer markup; must not be re-escaped.
echo $sn_footer_html;
?>

<?php wp_footer(); ?>
<!-- sn-index-build: <?php echo esc_html( SN_INDEX_BUILD ); ?> -->
</body>
</html>
