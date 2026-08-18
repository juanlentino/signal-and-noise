<?php
/**
 * Signal & Noise — the provenance badge joins the BROW on signed Pages.
 *
 * WHERE IT WAS. The plugin appends the chip through a `the_content` filter
 * (sn_prov_append_page_panel, priority 20), so on /about it landed as the last
 * child of .entry-content — measured 2026-08-18 at y=3212 of a 3382px document,
 * with the <h1> at y=250. A content filter can only append; the position was
 * never a styling choice, it was a consequence of the hook.
 *
 * WHY THAT IS WRONG, beyond taste. The badge is a claim ABOUT the document, not
 * a part of it. Sitting inside .entry-content after the final section it reads
 * as a closing sentence — and a reader learns the page is signed only after
 * reading everything, which inverts the argument the site exists to make:
 * authorship is established at creation, not asserted afterwards.
 *
 * WHERE IT GOES. The page already has the right slot. /about opens with
 * `<p class="sn-catalog-eyebrow">Dossier · Who I Am</p>` directly above its
 * title — the metadata line. The badge joins it as a second segment, in the
 * same DM Mono uppercase tracking every other meta line on the site uses, with
 * no pill chrome (the brow does not draw boxes).
 *
 * THE HOOK, and the trap. The obvious precedent is inc/pillar-title-eyebrow.php,
 * which filters `render_block_core/post-title`. It would silently never fire
 * here: /about's title is an authored `core/heading` inside the content, not a
 * post-title block, and its eyebrow is an authored `core/paragraph`. So this
 * filters `render_block_core/paragraph` and joins the FIRST eyebrow paragraph.
 *
 * NEVER SILENTLY LOSES THE BADGE. The plugin's foot append is suppressed only
 * when this placement actually happened (sn_prov_brow_placed()). A signed Page
 * with no eyebrow keeps the plugin's original behaviour rather than showing no
 * proof at all — a guard that makes the badge invisible is worse than the badge
 * being in the wrong place.
 *
 * @package SignalNoise
 * @since 11.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Did this request already place the brow badge? Also the signal the plugin's
 * append filter reads, so the two can never both render.
 *
 * @param bool|null $set Internal: mark as placed.
 * @return bool
 */
function sn_prov_brow_placed( $set = null ) {
	static $placed = false;
	if ( true === $set ) {
		$placed = true;
	}
	return $placed;
}

/** Reset between queries so a second loop cannot inherit the first's flag. */
function sn_prov_brow_reset() {
	// Cheap and explicit: the static above is per-request, and the only way to
	// clear it is a fresh request. Kept as a named seam for the tests.
	return sn_prov_brow_placed();
}

/**
 * The reader-facing, main-query, singular signed Page this render may decorate.
 *
 * Mirrors sn_pillar_eyebrow_page_id()'s gating exactly — everywhere else (other
 * blocks, non-main queries, feeds, REST/editor ServerSideRender, wp-admin)
 * degrades to 0 and the filter returns its input unchanged.
 *
 * @return int Page ID, or 0.
 */
function sn_prov_brow_page_id() {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return 0;
	}
	if ( function_exists( 'is_feed' ) && is_feed() ) {
		return 0;
	}
	if ( ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
		return 0;
	}
	$id = get_the_ID();
	return $id ? (int) $id : 0;
}

/**
 * The brow segment for a signed Page, or '' when the Page carries no chain.
 *
 * Reads the plugin's own view model rather than re-deriving status or version —
 * two renderers disagreeing about an anchor is exactly the class of bug the
 * provenance surface must not have. Guarded, so the theme still renders with
 * the plugin absent.
 *
 * @param int  $page_id
 * @param bool $with_separator Prefix the middot that joins an existing brow.
 *                             False when the segment IS the brow.
 * @return string HTML, already escaped.
 */
function sn_prov_brow_segment( $page_id, $with_separator = true ) {
	if ( ! function_exists( 'sn_prov_view_data' ) || ! function_exists( 'sn_prov_present_status' ) ) {
		return '';
	}
	$vm = sn_prov_view_data( $page_id );
	if ( null === $vm ) {
		return '';
	}
	$root  = function_exists( 'sn_prov_genesis_root_state' ) ? sn_prov_genesis_root_state() : array( 'status' => '' );
	$pres  = sn_prov_present_status( $vm['status'], $root['status'] ?? '' );
	$label = (string) ( $pres['label'] ?? '' );
	if ( '' === $label ) {
		return '';
	}
	// Version only when there is a real one: a genesis-only Page has no v-number
	// to show, and inventing "v1" would claim an edit history it does not have.
	$version = empty( $vm['is_genesis_only'] ) ? ' v' . (int) $vm['version'] : '';
	$href    = '';
	if ( function_exists( 'sn_prov_primary_explorer' ) ) {
		$explorer = sn_prov_primary_explorer( $vm, $root );
		$href     = (string) ( $explorer['href'] ?? '' );
	}
	$text = esc_html( $label . $version );
	$inner = '' === $href
		? '<span class="sn-prov-brow-label">' . $text . '</span>'
		: '<a class="sn-prov-brow-link" href="' . esc_url( $href ) . '" rel="nofollow noopener" target="_blank">'
			. $text . '<span class="sn-prov-brow-ext" aria-hidden="true">&#8599;</span></a>';
	$sep = $with_separator ? '<span class="sn-prov-brow-sep" aria-hidden="true">&middot;</span>' : '';
	return '<span class="sn-prov-brow" data-state="' . esc_attr( (string) ( $pres['state'] ?? '' ) ) . '">'
		. $sep . $inner . '</span>';
}

/**
 * render_block_core/paragraph: join the first `.sn-catalog-eyebrow` brow.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block.
 * @return string
 */
function sn_prov_brow_filter( $block_content, $block = array() ) {
	if ( sn_prov_brow_placed() ) {
		return $block_content; // one brow per page, always the first
	}
	if ( false === strpos( (string) $block_content, 'sn-catalog-eyebrow' ) ) {
		return $block_content;
	}
	$page_id = sn_prov_brow_page_id();
	if ( ! $page_id ) {
		return $block_content;
	}
	$segment = sn_prov_brow_segment( $page_id );
	if ( '' === $segment ) {
		return $block_content;
	}
	// Insert INSIDE the paragraph, before its closing tag, so the badge shares
	// the brow's line and baseline rather than starting a second one.
	$pos = strrpos( $block_content, '</p>' );
	if ( false === $pos ) {
		return $block_content;
	}
	sn_prov_brow_placed( true );
	return substr( $block_content, 0, $pos ) . $segment . substr( $block_content, $pos );
}

/**
 * render_block_core/post-title: CREATE the brow when the page has none.
 *
 * The two signed Pages have opposite shapes, and one filter cannot serve both:
 *
 *   /about              authored core/heading + an authored .sn-catalog-eyebrow
 *                       → the paragraph filter joins the existing brow
 *   /notes/start-here/  a core/post-title block and NO eyebrow at all
 *                       → nothing to join, so the brow is created here
 *
 * Without this the badge on a post-title page falls back to the plugin's foot
 * append — the position this whole change exists to leave. Verified against the
 * live markup before signing rather than after: start-here renders
 * `<h1 class="wp-block-post-title">` and carries no eyebrow paragraph.
 *
 * The emitted line reuses .sn-catalog-eyebrow so it inherits the site's brow
 * treatment exactly, and the segment drops its leading separator because here
 * it IS the brow rather than a second segment of one.
 *
 * @param string $block_content
 * @param array  $block
 * @return string
 */
function sn_prov_brow_title_filter( $block_content, $block = array() ) {
	if ( sn_prov_brow_placed() ) {
		return $block_content;
	}
	$page_id = sn_prov_brow_page_id();
	if ( ! $page_id ) {
		return $block_content;
	}
	// A post-title block renders BEFORE the content paragraphs, so registration
	// order cannot decide which filter wins — block order does. On a page that
	// has an authored brow, firing here would stack a second one above the
	// title. Ask the content directly instead of racing the render.
	$post = get_post( $page_id );
	if ( $post && false !== strpos( (string) $post->post_content, 'sn-catalog-eyebrow' ) ) {
		return $block_content; // the paragraph filter owns this page
	}
	$segment = sn_prov_brow_segment( $page_id, false );
	if ( '' === $segment ) {
		return $block_content;
	}
	sn_prov_brow_placed( true );
	return '<p class="sn-catalog-eyebrow sn-prov-brow-solo">' . $segment . '</p>' . $block_content;
}

if ( function_exists( 'add_filter' ) ) {
	// Exactly one of these ever fires: they share the placed-flag, and the
	// title filter additionally checks the page content for an authored brow
	// (it renders first, so it cannot rely on the flag alone).
	add_filter( 'render_block_core/paragraph', 'sn_prov_brow_filter', 10, 2 );
	add_filter( 'render_block_core/post-title', 'sn_prov_brow_title_filter', 10, 2 );

	// Suppress the plugin's foot append ONLY when the brow actually took it.
	// Blocks render inside the_content at priority 9; the plugin appends at 20,
	// so by the time this answers, the flag is truthful for this request.
	add_filter(
		'sn_prov_auto_append_page_panel',
		static function ( $enabled ) {
			return sn_prov_brow_placed() ? false : $enabled;
		},
		10,
		1
	);
}
