<?php
/**
 * Signal & Noise — JSON Feed 1.1 for the Notes corpus.
 *
 * Registered via add_feed('json',…). ?feed=json resolves immediately (no flush —
 * 'feed' is a core public query var). The pretty /feed/json/ path needs a rewrite
 * rule that only materializes on the PLUGIN's next flush; the theme must NOT flush.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * The registered feed slug. ONE source: both URL forms below and the
 * registration itself read it, so renaming the feed moves all three together
 * instead of leaving one pointing at a feed that no longer exists.
 *
 * @since 12.2.0
 * @return string
 */
function sn_feed_json_slug() {
	return 'json';
}

/**
 * The MACHINE URL — <head> autodiscovery and the feed's own feed_url.
 *
 * Deliberately the query form: 'feed' is a core public query var, so this
 * resolves on every install immediately. The pretty path below only exists
 * after the PLUGIN's next rewrite flush (the theme must never flush), so a
 * cold deploy would 404 on it (JF-1). A reader that autodiscovers must never
 * meet that window.
 *
 * @since 12.2.0
 * @return string
 */
function sn_feed_json_url() {
	return home_url( '/?feed=' . sn_feed_json_slug() );
}

/**
 * The HUMAN URL — what /notes/subscribe/ shows someone to copy into a reader.
 *
 * Pretty, because a person reads and retypes it. Safe here and nowhere else:
 * the subscribe page is a human destination, not an autodiscovery target, and
 * the rewrite rule is long since flushed on the live site.
 *
 * @since 12.2.0
 * @return string
 */
function sn_feed_json_pretty_url() {
	return home_url( '/feed/' . sn_feed_json_slug() . '/' );
}

function sn_feed_json_register() {
	add_feed( sn_feed_json_slug(), 'sn_feed_json_render' );
}

function sn_feed_json_content_type( $type, $feed ) {
	return ( sn_feed_json_slug() === $feed ) ? 'application/feed+json' : $type;
}

/**
 * Pure, testable WP_Query args for the JSON feed. posts_per_page is filterable
 * via sn_json_feed_items (default 20); the companion plugin supplies the
 * configured value.
 *
 * @return array
 */
function sn_feed_json_query_args() {
	return array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		// The raw the_content render below bypasses post_password_required();
		// protected posts must never reach the public feed (content-json.php
		// gates the same way; the OG-card leak was this exact trap class).
		'has_password'        => false,
		'posts_per_page'      => (int) apply_filters( 'sn_json_feed_items', 20 ),
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	);
}

/**
 * Top-level JSON Feed 1.1 `authors` block. Pure + testable. Site identity +
 * the /about page as the author URL, plus the site icon as an avatar when set.
 * Applies to every item by default (JSON Feed inherits feed-level authors), so
 * per-item authors are intentionally omitted.
 *
 * @since 10.13.0
 * @return array<int,array<string,string>>
 */
function sn_feed_json_authors() {
	$author = array(
		'name' => get_bloginfo( 'name' ),
		'url'  => home_url( '/about/' ),
	);
	$icon = function_exists( 'get_site_icon_url' ) ? (string) get_site_icon_url( 512 ) : '';
	if ( '' !== $icon ) {
		$author['avatar'] = $icon;
	}
	return array( $author );
}

/**
 * do_feed_json callback. Core invokes it as ($is_comment_feed, $feed_name).
 */
function sn_feed_json_render( $is_comment_feed = false, $feed = 'json' ) {
	$q = new WP_Query( sn_feed_json_query_args() );
	$items = array();
	while ( $q->have_posts() ) {
		$q->the_post();
		$built = sn_feed_json_build_item( get_post() );
		if ( null !== $built ) {
			$items[] = $built;
		}
	}
	wp_reset_postdata();

	$doc = array(
		'version'       => 'https://jsonfeed.org/version/1.1',
		'title'         => get_bloginfo( 'name' ),
		'home_page_url' => home_url( '/notes/' ),
		// Advertise the query-arg path: it resolves on every install. The pretty
		// /feed/json/ only works after the PLUGIN's next rewrite flush (the theme
		// must not flush), so a self-referential /feed/json/ would 404 on a cold
		// deploy (JF-1). ?feed=json is always live.
		'feed_url'      => sn_feed_json_url(),
		'description'   => get_bloginfo( 'description' ),
		'language'      => get_bloginfo( 'language' ),
		// v10.13.0: feed-level authors (applies to all items per JSON Feed 1.1).
		'authors'       => sn_feed_json_authors(),
		'items'         => $items,
	);
	header( 'Content-Type: application/feed+json; charset=' . get_option( 'blog_charset' ) );
	echo wp_json_encode( $doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}

/**
 * Build one JSON Feed item. Pure + testable. Raw values — wp_json_encode escapes.
 */
function sn_feed_json_build_item( $post ) {
	// Defense in depth behind the has_password query exclusion: a filtered
	// query args array must still never leak a protected post's content.
	if ( '' !== (string) ( $post->post_password ?? '' ) ) {
		return null;
	}
	$tags = array();
	foreach ( (array) get_the_category( $post->ID ) as $cat ) {
		$tags[] = $cat->name;
	}
	$item = array(
		'id'           => (string) get_permalink( $post ),
		'url'          => get_permalink( $post ),
		'title'        => get_the_title( $post ),
		'content_html' => (string) apply_filters( 'the_content', $post->post_content ),
	);
	// get_post_time('c',…) returns FALSE on a zeroed/invalid post date; a bare
	// false would serialize to "date_published":false, which is not a valid JSON
	// Feed date-time. Only emit when it's a real RFC-3339 string (JF-2).
	$pub = get_post_time( 'c', true, $post );
	if ( is_string( $pub ) && '' !== $pub ) {
		$item['date_published'] = $pub;
	}
	$mod = get_post_modified_time( 'c', true, $post );
	if ( is_string( $mod ) && '' !== $mod ) {
		$item['date_modified'] = $mod;
	}
	if ( $tags ) { $item['tags'] = $tags; }
	if ( has_excerpt( $post ) ) {
		$ex = get_the_excerpt( $post );
		if ( '' !== $ex ) { $item['summary'] = $ex; }
	}
	// v10.13.0: the item's main image (featured thumbnail) so readers like
	// NetNewsWire / Reeder / Feedbin render a thumbnail. Omitted when absent.
	$thumb = get_the_post_thumbnail_url( $post, 'large' );
	if ( is_string( $thumb ) && '' !== $thumb ) {
		$item['image'] = $thumb;
	}
	// v10.48.0: feed-level provenance. When the Note carries the plugin-owned
	// _sn_prov_uid meta (read via the canonical normalized helper,
	// inc/note-uid.php since v10.49.0), an underscore-prefixed JSON Feed 1.1
	// custom extension republishes it so feed subscribers can verify WITHOUT
	// a second fetch: verify_url is this Note's own /verify docket, json_url
	// the .json content twin (same derivation as inc/content-json.php's head
	// link), and reading time rides the plugin-owned sn_get_reading_time() —
	// omitted when the plugin is absent or reports none (mirrors
	// inc/feed-enrichment.php's >= 1 gate). An item without a uid gets NO
	// extension key at all.
	$uid = function_exists( 'sn_theme_note_uid' ) ? sn_theme_note_uid( $post->ID ) : '';
	if ( '' !== $uid ) {
		$ext = array(
			'note_uid'   => $uid,
			'verify_url' => home_url( '/verify?note=' . rawurlencode( $uid ) ),
			'json_url'   => rtrim( (string) get_permalink( $post ), '/' ) . '.json',
		);
		if ( function_exists( 'sn_get_reading_time' ) ) {
			$mins = (int) sn_get_reading_time( $post->ID );
			if ( $mins >= 1 ) {
				$ext['reading_time_minutes'] = $mins;
			}
		}
		$item['_signal_noise'] = $ext;
	}
	return $item;
}

/**
 * Autodiscovery: advertise the JSON Feed in <head> so readers/validators can
 * find it (mirrors WP's RSS rel=alternate). Uses the always-live ?feed=json URL.
 */
function sn_feed_json_head_link() {
	printf(
		'<link rel="alternate" type="application/feed+json" title="%s" href="%s">' . "\n",
		esc_attr( get_bloginfo( 'name' ) . ' — JSON Feed' ),
		esc_url( sn_feed_json_url() )
	);
}

if ( ! defined( 'SN_FEED_JSON_TEST' ) || ! SN_FEED_JSON_TEST ) {
	add_action( 'init', 'sn_feed_json_register' );
	add_filter( 'feed_content_type', 'sn_feed_json_content_type', 10, 2 );
	add_action( 'wp_head', 'sn_feed_json_head_link' );
}