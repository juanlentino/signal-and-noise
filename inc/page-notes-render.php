<?php
/**
 * Signal & Noise — /notes full PHP renderer.
 *
 * Included via the `template_include` short-circuit in
 * inc/page-notes-template.php. Renders the entire HTML document for
 * the /notes index page from scratch, bypassing WordPress block-
 * template resolution entirely.
 *
 * Layout direction: "Industrial Catalog" — the page is presented as
 * a directory listing for the brand. Tabular note rows in mono with
 * a date+meta column pulled left like a magazine spec line, a
 * terminal-status RSS footer with a blinking cursor. Stays inside
 * the existing brutalist white/asphalt/blood vocabulary but adds
 * editorial precision. (The numbered pillar-essay rail left this
 * index in v10.47.0: it is now the owner-placeable
 * signal-noise/pillar-essays block, see blocks/pillar-essays/.)
 *
 * Design tokens (from theme.json):
 *   void     #ffffff   page background
 *   asphalt  #f5f5f5   subtle card background
 *   concrete #d9d9d9   hairline borders
 *   rust     #666666   secondary text
 *   bone     #000000   primary text
 *   blood    #e00404   accent (rail, hover, cursor, links)
 *   signal   #ff4c47   secondary accent (hover shift)
 *
 * @package SignalNoise
 * @since 7.0.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// The pure helpers this renderer calls (sn_notes_query_posts,
// sn_notes_search_term, sn_notes_hero_stats, …) live in
// inc/notes-index-helpers.php (v10.49.0 extraction; always loaded via
// functions.php BEFORE the template router pulls this file in), and
// sn_notes_reading_time_for_slug() in inc/notes-reading-time.php
// (v10.42.2, available to REST/MCP). This file is now RENDER PATH ONLY:
// including it emits the full /notes HTML document — the old mid-file
// SN_NOTES_RENDER_TEST early return is retired; fixtures require the
// helpers module directly.

// ── BEGIN PAGE OUTPUT ──────────────────────────────────────────
$query = sn_notes_query_posts();
$sn_term      = sn_notes_search_term();
$sn_searching = ( '' !== $sn_term );
// Hero meta (browse mode only): corpus-level stats via the testable helper —
// found_posts, not the per-page post_count, so "N entries" is the whole corpus
// on every page; "Last updated" shows only when posts[0] is genuinely newest.
$sn_hero_stats = sn_notes_hero_stats( $query );
$entry_count   = $sn_hero_stats['count'];
$latest_date   = $sn_hero_stats['latest_date'];

// Tag-archive context. $sn_filtered (search OR tag) hides the hero corpus
// stats and the Start-here pinned card (both mislabel filtered results).
$sn_tag_id   = sn_notes_current_tag_id();
$sn_tag      = ( $sn_tag_id > 0 );
$sn_tag_name = '';
if ( $sn_tag ) {
	$sn_tag_obj  = get_queried_object();
	$sn_tag_name = ( $sn_tag_obj && isset( $sn_tag_obj->name ) ) ? $sn_tag_obj->name : '';
}
$sn_filtered      = $sn_searching || $sn_tag;
$sn_start_here_id = $sn_filtered ? 0 : sn_notes_start_here_id();

// The stickied note is floated to the TOP of the index (page 1) and is
// excluded from $query (post__not_in) so it never appears twice; page 2+ does
// not repeat it — standard WP sticky semantics, which a secondary WP_Query
// does not provide on its own. Add it back into the displayed corpus count.
$sn_pin_on_page1 = ( $sn_start_here_id > 0 && sn_notes_current_page() <= 1 );
if ( $sn_start_here_id > 0 ) {
	$entry_count += 1;
}

// The router only short-circuits a tag archive for a REAL term, so force
// HTTP 200 here — even a valid-but-empty tag archive should be 200, not the
// 404 WP may have committed before template_redirect (WORDPRESS-REFERENCE #40).
if ( $sn_tag && function_exists( 'status_header' ) ) {
	status_header( 200 );
}

// PRE-RENDER the header and footer template parts so their block-
// layout CSS (e.g. `.wp-container-core-group-is-layout-... { flex-
// wrap, justify-content, … }`) gets registered with WP_Style_Engine
// BEFORE wp_head() runs. Without this two-pass, the layout styles
// for the .sn-header / .sn-footer flex containers are queued AFTER
// wp_head() has already printed its stylesheet — they end up nowhere
// in the document, and the header nav packs left instead of right
// (no space-between), the footer copyright packs left instead of
// right, etc. Output buffer captures the markup; WP's style-engine
// receives the side effects.
//
// (Historical note: through v9.7.0 the header also carried a core/search
// block whose script-module enqueue made this pre-render doubly load-
// bearing. v9.8.0 removed that block — search now lives in the /notes
// archive below, not the header — so the pre-render is now justified by
// the block-layout CSS alone. The pass MUST still run BEFORE wp_head().)
ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() output is trusted rendered block HTML (the theme's own header template part); escaping it would corrupt the markup. Captured here only to register block-layout styles before wp_head().
echo do_blocks( '<!-- wp:template-part {"slug":"header","area":"header"} /-->' );
$sn_header_html = ob_get_clean();

ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() output is trusted rendered block HTML (the theme's own footer template part); must not be escaped.
echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
$sn_footer_html = ob_get_clean();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
// The document <title> is emitted by core's _wp_render_title_tag() during
// wp_head() below — inc/setup.php registers add_theme_support( 'title-tag' )
// (added for the TSF cutover, v8.5.5), and the value comes from the
// pre_get_document_title filter in inc/page-notes-template.php. Before v9.5.1
// this renderer ALSO echoed a manual <title> here (a leftover from before
// title-tag support existed), which produced a DUPLICATE <title> on /notes.
wp_head();
?>
</head>
<body <?php body_class( 'sn-notes-body' ); ?>>
<?php wp_body_open(); ?>

<?php
// Header was pre-rendered above (before wp_head ran) so the block-
// layout styles registered correctly. Now we just echo the captured
// HTML.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sn_header_html is trusted do_blocks() output captured above; must not be re-escaped.
echo $sn_header_html;
?>

<main class="sn-notes-page" id="wp--skip-link--target">

	<header class="sn-notes-hero">
		<?php // v11.4.4 resume-treatment: eyebrow is a kicker spanning the
		      // grid; dek reads under the title; the side column carries the
		      // subscribe line with the corpus meta as its closing stamp. ?>
		<p class="sn-notes-eyebrow"><?php if ( $sn_tag ) : ?>Topic &middot; <?php echo esc_html( $sn_tag_name ); ?><?php else : ?>Index &middot; <?php echo esc_html( wp_date( 'Y' ) ); ?><?php endif; ?></p>
		<div class="sn-notes-hero-title">
			<h1 class="sn-notes-headline">Notes.</h1>
			<?php
			// A tag archive that carries its own description says THAT; every
			// other view keeps the corpus dek. Without this, all 23 tag
			// archives repeat one identical sentence — the thin-content shape
			// the contentless-page rule warns about. A tag with no description
			// written yet falls back rather than inventing one.
			$sn_tag_desc = function_exists( 'sn_notes_tag_description' ) ? sn_notes_tag_description() : '';
			?>
			<?php if ( '' !== $sn_tag_desc ) : ?>
			<p class="sn-notes-dek"><?php echo esc_html( $sn_tag_desc ); ?></p>
			<?php else : ?>
			<p class="sn-notes-dek">Working notes on music, AI, and the infrastructure underneath. Written when there&rsquo;s something worth writing.</p>
			<?php endif; ?>
			<?php
			// Start Here is a PAGE under /notes/, so it is absent from the post
			// query that builds the index below — without this link the corpus
			// has no route to its own front door. Rendered unconditionally
			// (unlike the corpus meta, which is filtered-state suppressed):
			// wayfinding is most useful precisely when a newcomer landed on a
			// tag or search view. Resolver returns 0 if the page is gone, so a
			// missing page removes the link rather than serving a 404.
			$sn_start_here_page = sn_notes_start_here_page_id();
			?>
			<?php // v12.18.0: a wayfinding ROW, because "All tags" used to sit at the
			      // tail of the corpus meta stamp below — inside `if ( ! $sn_filtered )`.
			      // That made the ONLY site-wide route to /notes/tags/ disappear on
			      // every tag archive and search view, so the reader who had just
			      // clicked a tag was the one reader who could not reach the tag index.
			      //
			      // The suppression is right for the figures it was written for:
			      // "59 entries" and "Last updated" describe the CORPUS and would
			      // mislabel a filtered result set. A corpus-wide navigation link
			      // carries no such claim. The link inherited a visibility rule
			      // written for the numbers beside it, purely by sitting in their <p>.
			      //
			      // This is the argument the Start Here link already made two lines
			      // up — rendered unconditionally because wayfinding matters most to
			      // someone who landed mid-corpus — so the two now share a row.
			      // Still deliberately NOT in the top nav: seven entries already, and
			      // a secondary index does not rank beside Home and About. ?>
			<div class="sn-notes-wayfinding">
				<?php if ( $sn_start_here_page ) : ?>
				<p class="sn-notes-start-here">
					<a href="<?php echo esc_url( get_permalink( $sn_start_here_page ) ); ?>">First time? Start here<span class="sn-notes-start-here-arrow" aria-hidden="true">&rarr;</span></a>
				</p>
				<?php endif; ?>
				<?php // Quiet by design: Start Here is a bordered target (v11.9.4) and
				      // a second identical box would rank the glossary as an equal call
				      // to action. Same mono/blood voice, underline instead of a border. ?>
				<p class="sn-notes-all-tags">
					<a href="<?php echo esc_url( home_url( '/notes/tags/' ) ); ?>">All tags</a>
				</p>
			</div>
		</div>
		<div class="sn-notes-hero-side">
			<?php // v12.13.1: the two feed addresses, direct. This linked to
			      // /notes/subscribe/, a 241-word page whose deliverable was one
			      // URL and whose second half re-listed the index below it. The
			      // page is gone; a reader now reaches the feed in one click
			      // instead of two. Email went with it: it was never a link, it
			      // was a paragraph naming two third-party bridges, and naming
			      // vendors here reads wrong beside the rest of the page.
			      //
			      // v12.14.0: the reader names come back. The retired page carried
			      // them ("NetNewsWire, Reeder, Feedbin and others") and I dropped
			      // them with it, generalising an argument that was only ever about
			      // the EMAIL bridges — those are a service you sign into, and
			      // naming them here reads like an endorsement. A feed reader is
			      // different: it is the thing that makes "RSS" mean anything, and
			      // "whatever reader you already use" strands anyone who has none.
			      // Plain text, not links, exactly as the retired page had them:
			      // apps a visitor installs, not destinations to send them to.
			      // "among others" is deliberate — this is a claim about
			      // third-party software that can age, and the hedge is what keeps
			      // it from becoming wrong if one of the three drops JSON Feed.
			      //
			      // Both URLs come from their ACCESSORS, never a literal: the
			      // retired page's own suite pinned exactly that, and this hero
			      // is now the second reader of both facts. Two copies of a feed
			      // path is two things to keep in step. ?>
			<p class="sn-notes-subscribe">
				Every note lands in whatever reader you already use, the day it goes up
				&mdash; <a href="<?php echo esc_url( function_exists( 'sn_subscribe_feed_url' ) ? sn_subscribe_feed_url() : home_url( '/notes/feed/' ) ); ?>">RSS</a> or <a href="<?php echo esc_url( function_exists( 'sn_feed_json_pretty_url' ) ? sn_feed_json_pretty_url() : home_url( '/feed/json/' ) ); ?>">JSON Feed</a>. No reader yet? NetNewsWire, Reeder and Feedbin read both, among others.<span class="sn-notes-cursor" aria-hidden="true"></span>
			</p>
			<?php // The one sentence worth carrying over from that page. On a site
			      // arguing about what gets recorded about people, how the feed
			      // behaves is the point, not a footnote. ?>
			<p class="sn-notes-subscribe-privacy">Nothing is sent to me, and nothing about you is collected &mdash; a reader fetches the file the same way a browser fetches a page.</p>
			<?php if ( ! $sn_filtered ) : ?>
			<?php // Corpus stats: entry count + last-updated, the side column's
			      // closing stamp. Suppressed in search/tag state — there the
			      // figures describe the filtered result set and would mislabel
			      // the corpus (the count lives in the summary line below). ?>
			<p class="sn-notes-meta">
				<span><?php echo esc_html( sprintf( _n( '%d entry', '%d entries', $entry_count, 'signal-noise' ), $entry_count ) ); ?></span>
				<?php if ( $latest_date ) : ?>
					<span class="sn-notes-meta-bullet" aria-hidden="true">&middot;</span>
					<span>Last updated <?php echo esc_html( $latest_date ); ?></span>
				<?php endif; ?>
			</p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( ! $sn_searching ) : ?>
	<hr class="sn-notes-rule" aria-hidden="true">
	<?php endif; ?>

	<section class="sn-notes-index-section" aria-labelledby="sn-index-heading">

		<form class="sn-notes-search" role="search" method="get" action="<?php echo esc_url( home_url( '/notes/' ) ); ?>">
			<label class="screen-reader-text" for="sn-notes-q">Search notes</label>
			<input type="search" id="sn-notes-q" name="s" value="<?php echo esc_attr( $sn_term ); ?>" placeholder="Search notes" autocomplete="off" />
			<button type="submit" aria-label="Search"><span aria-hidden="true">&rarr;</span></button>
		</form>

		<div class="sn-notes-section-wrap">
			<?php if ( $sn_searching ) : ?>
				<p class="sn-notes-section-label" id="sn-index-heading">Notes: Search &middot; &ldquo;<?php echo esc_html( $sn_term ); ?>&rdquo;</p>
				<a class="sn-notes-section-clear" href="<?php echo esc_url( home_url( '/notes/' ) ); ?>">Clear &times;</a>
			<?php elseif ( $sn_tag ) : ?>
				<p class="sn-notes-section-label" id="sn-index-heading">Notes: Tag &middot; &ldquo;<?php echo esc_html( $sn_tag_name ); ?>&rdquo;</p>
				<a class="sn-notes-section-clear" href="<?php echo esc_url( home_url( '/notes/' ) ); ?>">All notes</a>
			<?php else : ?>
				<p class="sn-notes-section-label" id="sn-index-heading">Notes: Index</p>
				<span class="sn-notes-section-count"><?php echo esc_html( sprintf( '%02d', (int) $entry_count ) ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( $sn_searching ) : ?>
			<p class="sn-notes-search-summary"><?php echo esc_html( sprintf( _n( '%d note found', '%d notes found', (int) $query->found_posts, 'signal-noise' ), (int) $query->found_posts ) ); ?></p>
		<?php elseif ( $sn_tag ) : ?>
			<p class="sn-notes-search-summary"><?php echo esc_html( sprintf( _n( '%d note tagged', '%d notes tagged', (int) $query->found_posts, 'signal-noise' ), (int) $query->found_posts ) ); ?></p>
		<?php endif; ?>

		<?php if ( $query->have_posts() || $sn_pin_on_page1 ) : ?>
			<?php
			// v11.10.0: rows and the year spine render from inc/notes-index-row.php.
			// The pinned Start-here row and the chronological rows are the SAME
			// markup through one function — they had already drifted apart once.
			$sn_rows = array();
			if ( $sn_pin_on_page1 ) {
				$sn_pin_post = get_post( $sn_start_here_id );
				if ( $sn_pin_post ) {
					$sn_rows[] = array( 'post' => $sn_pin_post, 'pinned' => true );
				}
			}
			while ( $query->have_posts() ) {
				$query->the_post();
				$sn_rows[] = array( 'post' => get_post(), 'pinned' => false );
			}
			wp_reset_postdata();

			$sn_row_args = array( 'show_type' => (bool) $sn_searching );
			// v12.13.0: $sn_searching, NOT $sn_filtered. The rule below is about
			// RELEVANCE ORDER, and only search has one — a tag archive is
			// ordered by date, exactly like browse, so the calendar IS its
			// structure and the spine belongs on it. Grouping tags with search
			// here was the same conflation the query builder carried (see
			// inc/notes-index-helpers.php): a reason that fits search applied to
			// a set it does not describe.
			//
			// Invisible today by design: sn_notes_year_spine_is_useful() draws
			// nothing while the corpus spans one year, so this changes no pixel
			// until 2027 — and then a tag archive folds prior years the way the
			// index already does, which is what keeps it browsable as it grows.
			// $sn_filtered still governs the hero corpus stats and Start Here,
			// where "search OR tag" remains the right distinction.
			if ( $sn_searching ) {
				// Search stays flat: a result set is ordered by relevance to the
				// query, and folding it by year would impose a spine on a list
				// whose structure is the QUERY, not the calendar.
				sn_notes_render_row_list( wp_list_pluck( $sn_rows, 'post' ), $sn_row_args );
			} else {
				// Browse mode. The pinned row sits ABOVE the spine — it is
				// deliberately out of date order, so filing it under a year would
				// contradict the reason it is pinned.
				$sn_pinned_rows = array();
				$sn_chrono      = array();
				foreach ( $sn_rows as $sn_r ) {
					if ( $sn_r['pinned'] ) {
						$sn_pinned_rows[] = $sn_r['post'];
					} else {
						$sn_chrono[] = $sn_r['post'];
					}
				}
				if ( $sn_pinned_rows ) {
					sn_notes_render_row_list( $sn_pinned_rows, array( 'pinned' => true ) );
				}
				sn_notes_render_year_spine( $sn_chrono, $sn_row_args );
			}
			?>
		<?php elseif ( $sn_searching ) : ?>
			<p class="sn-notes-empty">Nothing matches &ldquo;<?php echo esc_html( $sn_term ); ?>&rdquo;. Notes, essays, and pages all searched.</p>
		<?php elseif ( $sn_tag ) : ?>
			<p class="sn-notes-empty">No notes tagged &ldquo;<?php echo esc_html( $sn_tag_name ); ?>&rdquo;.</p>
		<?php else : ?>
			<p class="sn-notes-empty">No notes published yet. Check back soon.</p>
		<?php endif; ?>

		<?php if ( $query->max_num_pages > 1 ) : ?>
			<nav class="sn-notes-pagination" aria-label="Notes pages">
				<?php
				$sn_notes_links = paginate_links( array(
					'base'      => esc_url_raw( add_query_arg( 'paged', '%#%', sn_notes_pagination_base() ) ),
					'format'    => '',
					'current'   => sn_notes_current_page(),
					'total'     => (int) $query->max_num_pages,
					'add_args'  => sn_notes_pagination_add_args( $sn_term ),
					'type'      => 'array',
					'prev_text' => '&larr;',
					'next_text' => '&rarr;',
					'mid_size'  => 2,
					'end_size'  => 1,
				) );
				if ( is_array( $sn_notes_links ) ) {
					foreach ( $sn_notes_links as $sn_link ) {
						// paginate_links() returns pre-escaped, controlled
						// <a>/<span> markup (WP core helper). Echo as-is;
						// wrapping in esc_html would mangle the anchors.
						echo $sn_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() output is trusted WP-core-generated markup.
					}
				}
				?>
			</nav>
		<?php endif; ?>
	</section>

</main>

<?php
// Footer pre-rendered above. Echo the captured HTML.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sn_footer_html is trusted do_blocks() output captured above; must not be re-escaped.
echo $sn_footer_html;
?>

<?php wp_footer(); ?>
</body>
</html>
