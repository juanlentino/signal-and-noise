<?php
/**
 * Signal & Noise — /notes page, PHP-authoritative full render.
 *
 * Why this exists: across THREE incidents (2026-04, 2026-05, 2026-05)
 * the /notes page rendered stale content despite the canonical
 * version being correct in `main`. Each incident had a different
 * proximate cause (deploy silently skipping the file, broken self-
 * heal corrupting it, stale `wp_template` DB row surviving the one-
 * shot migration), but the common surface was WordPress's block-
 * template resolution chain — file ↔ DB ↔ object cache ↔ registry —
 * which has too many layers that can drift independently and too
 * many ways to silently render stale content.
 *
 * This module pulls /notes off that chain entirely. We hook
 * `template_include` and short-circuit to a custom render file in
 * `inc/page-notes-render.php`. WP's normal block-template resolution
 * never runs for this page when our hook fires. Front-end rendering
 * is driven from PHP.
 *
 * Defense layers:
 *   1. PHP renderer in inc/page-notes-render.php (PRIMARY) — the
 *      canonical source of truth; what users actually see.
 *   2. templates/page-notes.html (FALLBACK) — kept on disk with the
 *      correct two-card content. Used by WP normally if our
 *      template_include hook fails to resolve (e.g., the render
 *      file is missing post-deploy). Better to render from a stale-
 *      but-correct file than to 404.
 *   3. admin_init wp_template DB sweep — clears any stale Site
 *      Editor save that would otherwise win in get_block_templates()
 *      results.
 *
 * Trade-off: /notes is no longer practically editable via Site
 * Editor — the canonical layout lives in inc/page-notes-render.php.
 * Given the incident history, this is the right call: the page
 * hasn't been customized via Site Editor in practice, and removing
 * the surface eliminates the failure mode entirely.
 *
 * @package SignalNoise
 * @since 7.0.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Short-circuit template resolution for the /notes page. Priority
 * 999 so we run after any other template_include filter — last
 * writer wins, and we want to win.
 *
 * Detection: WP sets `is_page('notes')` true when the request resolves
 * to the static Page with slug `notes`. The page exists since the
 * theme's seed migration creates it on activation (see
 * `sn_ensure_notes_page()` in inc/notes-and-provenance.php).
 *
 * Fallback: if the render file is missing for any reason, fall
 * through to whatever WP would have done. Don't break the page just
 * because our override is missing.
 */
add_filter( 'template_include', function( $template ) {
	if ( ! is_page( 'notes' ) ) {
		return $template;
	}
	$render = get_theme_file_path( 'inc/page-notes-render.php' );
	if ( ! file_exists( $render ) ) {
		return $template;
	}
	return $render;
}, 999 );

/**
 * Auto-purge wp_template DB rows for `page-notes` on every admin
 * pageview. The template file has been removed and the renderer
 * is now PHP-authoritative, but a row in the DB could still exist
 * from a Site Editor save in the past. Clearing it keeps
 * `get_block_templates()` results clean for any other code path
 * that queries them, and prevents the row from re-appearing in the
 * Site Editor UI. Cheap query — keyed lookup on post_type +
 * post_name. No-op when no row matches.
 */
add_action( 'admin_init', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! post_type_exists( 'wp_template' ) ) {
		return;
	}
	$ids = get_posts( array(
		'post_type'      => 'wp_template',
		'post_status'    => 'any',
		'name'           => 'page-notes',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'wp_theme',
				'field'    => 'name',
				'terms'    => 'signal-and-noise',
			),
		),
	) );
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
} );
