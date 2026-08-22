<?php
/**
 * Signal & Noise — the human subscribe page at /notes/subscribe/.
 *
 * WHY THIS EXISTS, and why the styled-feed approach it replaces cannot work
 * (v11.9.4): the site names RSS as its only endorsed channel, so clicking that
 * link should not meet a wall. v11.9.2–3 tried to solve it by attaching an XSL
 * stylesheet to the feed. Verified in three browsers, none of which will ever
 * render it:
 *
 *   Chrome  — shows raw XML; <?xml-stylesheet?> is not applied.
 *   Safari  — never renders feeds; opens a "find an RSS reader" dialog.
 *   Firefox — DOWNLOADS the file.
 *
 * Firefox is the tell that this was never only about XSLT: the feed's
 * `application/rss+xml` type is correct and REQUIRED for readers and
 * autodiscovery, and it is exactly what makes a browser treat the response as a
 * download rather than a document. The two requirements are mutually exclusive;
 * no header or stylesheet reconciles them.
 *
 * A page has none of that problem, because it is just a page. Readers are
 * unaffected — they follow <link rel="alternate"> in <head>, never the visible
 * link — so pointing the human-facing "RSS" link here costs subscription
 * nothing.
 *
 * Routed on `template_redirect` by exact path, so it needs NO rewrite rule and
 * NO flush (the theme must never flush — see JF-1 in inc/feed-json.php). Same
 * technique the plugin uses to serve /.well-known/did.json.
 *
 * @package SignalNoise
 * @since 11.9.4
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

const SN_SUBSCRIBE_PATH = '/notes/subscribe/';

/**
 * Is the current request the subscribe page? Path-exact, query string ignored,
 * trailing slash optional.
 *
 * @param string $uri Request URI.
 * @return bool
 */
function sn_subscribe_is_request( $uri ) {
	$path = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );
	return trailingslashit( $path ) === SN_SUBSCRIBE_PATH;
}

/**
 * The feed URL this page teaches. Kept in one place so the page and any future
 * caller cannot drift.
 *
 * @return string
 */
function sn_subscribe_feed_url() {
	return home_url( '/notes/feed/' );
}

/**
 * Is the LIVE request this page? Read in one place so the renderer and the
 * title filter can never disagree about which route they are on.
 *
 * @since 12.2.1
 * @return bool
 */
function sn_subscribe_is_current_request() {
	if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return false;
	}
	return sn_subscribe_is_request( wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- path-compared, never echoed.
}

/**
 * The document title.
 *
 * WHY THIS IS NEEDED (v12.2.1): this route answers 200 from template_redirect,
 * but WP's query never resolved to a post, so wp_get_document_title() fell all
 * the way through to "Page not found" — on a live, indexable page, from
 * v11.9.4 until now. pre_get_document_title short-circuits WP's resolver, the
 * same fix inc/page-notes-template.php and inc/page-index-template.php use for
 * their synthetic routes.
 *
 * Deliberately NOT solved by creating a "Subscribe" Page in wp-admin: this
 * renderer exits on template_redirect, so a real Page at this path would never
 * render, and its title would live in the database — outside the repo, absent
 * from a fresh install, and pinned by nothing.
 *
 * @since 12.2.1
 * @param string $title Incoming title.
 * @return string
 */
function sn_subscribe_document_title( $title ) {
	return sn_subscribe_is_current_request() ? sn_subscribe_title() : $title;
}

/**
 * @since 12.2.1
 * @return string
 */
function sn_subscribe_title() {
	return 'Subscribe — ' . get_bloginfo( 'name' );
}

/**
 * template_redirect: serve the page.
 *
 * @return void
 */
function sn_subscribe_render() {
	if ( ! sn_subscribe_is_current_request() ) {
		return;
	}

	status_header( 200 );
	header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );

	$feed  = sn_subscribe_feed_url();
	$json  = sn_feed_json_pretty_url();
	$posts = get_posts( array(
		'post_type'        => 'post',
		'post_status'      => 'publish',
		'numberposts'      => 8,
		'suppress_filters' => false,
	) );

	// PRE-RENDER both template parts so their block-layout CSS (the
	// .wp-container-core-group-is-layout-… flex rules) is registered with
	// WP_Style_Engine BEFORE wp_head prints the stylesheet. Without this
	// two-pass those rules are queued too late and land nowhere: the header nav
	// packs left instead of right. inc/page-notes-render.php carries the same
	// constraint and the same comment — this route simply never had it.
	//
	// v12.2.2: this replaced the get_header / get_footer pair. There is no header.php
	// in a block theme, so get_header fell through to core's theme-compat
	// fallback (wp-includes/theme-compat/header.php) and rendered a generic
	// site-title-and-tagline header. That was the giant flush-left wordmark and
	// the missing nav from v11.9.4 until now.
	ob_start();
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own header template part); captured to register block-layout styles before wp_head runs.
	echo do_blocks( '<!-- wp:template-part {"slug":"header","area":"header"} /-->' );
	$sn_header_html = ob_get_clean();

	ob_start();
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own footer template part); must not be escaped.
	echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
	$sn_footer_html = ob_get_clean();
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
	// <title> comes from core's _wp_render_title_tag() during wp_head, fed by
	// sn_subscribe_document_title() above. Never echo a manual <title> here —
	// that produced a duplicate on /notes before v9.5.1.
	wp_head();
?>
	<style>
	/* Self-contained: the /notes/ hero styles are inline in page-notes-render.php
	   and do not load on this route. Same tokens, so the two surfaces agree. */
	/* v12.2.1 — THE CONTAINER. .sn-notes-page carries the width, the gutter and
	   the clearance for the fixed .sn-footer, and it is declared in
	   page-notes-render.php's INLINE style, which does not load here. So from
	   v11.9.4 this page rendered flush against the viewport edge with no width
	   and no gutter at all. Declared locally, and tests/feed-subscribe-page.php
	   compares it declaration-for-declaration against the /notes/ rule so the
	   two cannot drift apart again. */
	.sn-notes-page.sn-subscribe {
		padding: clamp(2rem, 5vw, 4.5rem) clamp(1.25rem, 3vw, 3rem) 160px;
		max-width: 1320px;
		margin: 0 auto;
	}
	.sn-subscribe .sn-notes-hero { padding: clamp(2rem,6vw,5rem) 0 0; }
	.sn-subscribe .sn-notes-eyebrow,
	.sn-subscribe .sn-notes-section-label {
		font-family: 'DM Mono','Courier New',monospace; font-size: max(0.7rem,11px);
		letter-spacing: 0.18em; text-transform: uppercase; margin: 0 0 1rem;
		color: var(--wp--preset--color--rust);
	}
	.sn-subscribe .sn-notes-eyebrow { color: var(--wp--preset--color--blood); }
	.sn-subscribe .sn-notes-headline {
		font-family: 'Bebas Neue',Impact,sans-serif; font-weight: 400;
		font-size: clamp(3rem,8vw,7rem); line-height: 0.95; letter-spacing: -0.02em;
		margin: 0 0 1.25rem; color: var(--wp--preset--color--bone);
	}
	.sn-subscribe .sn-notes-dek {
		font-size: clamp(1rem,1.4vw,1.15rem); line-height: 1.55; max-width: 48ch;
		color: var(--wp--preset--color--rust); margin: 0 0 1.5rem;
	}
	.sn-subscribe .sn-notes-rule { border: 0; border-top: 1px solid currentColor; opacity: 0.14; margin: 2.5rem 0; }
	.sn-subscribe-step { margin-bottom: 2.5rem; }
	.sn-subscribe-url { margin: 0 0 1.25rem; }
	.sn-subscribe-url code {
		display: inline-block; font-family: 'DM Mono','Courier New',monospace;
		font-size: clamp(0.95rem,2.2vw,1.35rem); padding: 0.75rem 1rem;
		border: 1px solid var(--wp--preset--color--blood);
		color: var(--wp--preset--color--bone); word-break: break-all;
		user-select: all; background: none;
	}
	.sn-subscribe-help {
		max-width: 58ch; line-height: 1.6; color: var(--wp--preset--color--rust);
		margin: 0 0 0.85rem;
	}
	.sn-subscribe .sn-notes-index-list { list-style: none; margin: 0; padding: 0; }
	.sn-subscribe .sn-notes-row { display: grid; grid-template-columns: 8rem 1fr; gap: 1.5rem;
		padding: 1rem 0; border-bottom: 1px solid rgba(0,0,0,0.08); }
	@media (max-width: 700px) { .sn-subscribe .sn-notes-row { grid-template-columns: 1fr; gap: 0.35rem; } }
	.sn-subscribe .sn-notes-row-date {
		font-family: 'DM Mono','Courier New',monospace; font-size: max(0.7rem,11px);
		letter-spacing: 0.14em; color: var(--wp--preset--color--rust);
	}
	.sn-subscribe .sn-notes-row-title {
		font-family: 'Bebas Neue',Impact,sans-serif; font-weight: 400;
		font-size: clamp(1.3rem,2.6vw,1.8rem); line-height: 1.05; margin: 0;
	}
	.sn-subscribe .sn-notes-row-title a { color: var(--wp--preset--color--bone); text-decoration: none; }
	.sn-subscribe .sn-notes-row-title a:hover,
	.sn-subscribe .sn-notes-row-title a:focus-visible { color: var(--wp--preset--color--blood); }
	</style>
</head>
<body <?php body_class( 'sn-notes-body sn-subscribe-body' ); ?>>
<?php wp_body_open(); ?>
<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sn_header_html is trusted do_blocks() output captured above; must not be re-escaped.
	echo $sn_header_html;
?>
	<main class="sn-notes-page sn-subscribe" id="wp--skip-link--target">
		<header class="sn-notes-hero">
			<p class="sn-notes-eyebrow">Notes &#183; Subscribe</p>
			<div class="sn-notes-hero-title">
				<h1 class="sn-notes-headline">Follow.</h1>
				<p class="sn-notes-dek">No subscription form, no schedule, no algorithm deciding what
					you see. Notes go out over RSS — an open format any reader can follow
					— and as a JSON Feed for readers that prefer it.</p>
			</div>
		</header>

		<hr class="sn-notes-rule" aria-hidden="true">

		<section class="sn-subscribe-step" aria-labelledby="sn-sub-url">
			<p class="sn-notes-section-label" id="sn-sub-url">The address</p>
			<p class="sn-subscribe-url"><code><?php echo esc_html( $feed ); ?></code></p>
			<p class="sn-subscribe-help">Copy that into any feed reader and new notes arrive on
				their own. Nothing is sent to me, and nothing about you is collected — a reader
				fetches the file the same way a browser fetches a page.</p>
			<p class="sn-subscribe-help">Prefer email? <a href="https://blogtrottr.com/" target="_blank" rel="noopener noreferrer" data-sn-subscribe="email">Blogtrottr</a>
				and <a href="https://www.feedrabbit.com/" target="_blank" rel="noopener noreferrer" data-sn-subscribe="email">Feedrabbit</a>
				turn that same address into messages in your inbox.</p>
			<p class="sn-subscribe-help">First time? <a href="<?php echo esc_url( home_url( '/notes/start-here/' ) ); ?>">Start here</a>
				reads the argument in order, or <a href="<?php echo esc_url( home_url( '/notes/' ) ); ?>">browse every note</a>.</p>
		</section>

		<hr class="sn-notes-rule" aria-hidden="true">

		<section class="sn-subscribe-step" aria-labelledby="sn-sub-json">
			<p class="sn-notes-section-label" id="sn-sub-json">The same notes, as JSON Feed</p>
			<p class="sn-subscribe-url"><code><?php echo esc_html( $json ); ?></code></p>
			<p class="sn-subscribe-help">Identical contents in JSON Feed 1.1, for readers that speak it
				— NetNewsWire, Reeder, Feedbin and others. RSS above stays the primary channel;
				take whichever your reader prefers, not both.</p>
		</section>

		<hr class="sn-notes-rule" aria-hidden="true">

		<section class="sn-subscribe-step" aria-labelledby="sn-sub-terms">
			<p class="sn-notes-section-label" id="sn-sub-terms">Passing it on</p>
			<p class="sn-subscribe-help">Subscribing hands you the words, and republishing them
				elsewhere is welcome on the terms set out in the
				<a href="<?php echo esc_url( home_url( '/tdm-policy/' ) ); ?>">reuse and text-and-data-mining policy</a>.
				That page is the canonical statement of them, kept deliberately in one place
				rather than repeated here.</p>
		</section>

		<?php if ( $posts ) : ?>
		<hr class="sn-notes-rule" aria-hidden="true">
		<section class="sn-subscribe-step" aria-labelledby="sn-sub-recent">
			<p class="sn-notes-section-label" id="sn-sub-recent">What you would have received</p>
			<ol class="sn-notes-index-list">
				<?php foreach ( $posts as $p ) : ?>
					<li class="sn-notes-row">
						<div class="sn-notes-row-spec">
							<time class="sn-notes-row-date" datetime="<?php echo esc_attr( get_the_date( 'c', $p ) ); ?>"><?php echo esc_html( get_the_date( 'Y.m.d', $p ) ); ?></time>
						</div>
						<div class="sn-notes-row-content">
							<h2 class="sn-notes-row-title"><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></h2>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
		<?php endif; ?>
	</main>
<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sn_footer_html is trusted do_blocks() output captured above; must not be re-escaped.
	echo $sn_footer_html;
?>
<?php wp_footer(); ?>
</body>
</html>
	<?php
	exit;
}

if ( ! defined( 'SN_SUBSCRIBE_TEST' ) || ! SN_SUBSCRIBE_TEST ) {
	add_action( 'template_redirect', 'sn_subscribe_render' );
	add_filter( 'pre_get_document_title', 'sn_subscribe_document_title', 999 );
}
