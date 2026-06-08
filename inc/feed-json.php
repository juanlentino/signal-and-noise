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

function sn_feed_json_register() {
	add_feed( 'json', 'sn_feed_json_render' );
}

function sn_feed_json_content_type( $type, $feed ) {
	return ( 'json' === $feed ) ? 'application/feed+json' : $type;
}

/**
 * do_feed_json callback. Core invokes it as ($is_comment_feed, $feed_name).
 */
function sn_feed_json_render( $is_comment_feed = false, $feed = 'json' ) {
	$q = new WP_Query( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 20,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	) );
	$items = array();
	while ( $q->have_posts() ) {
		$q->the_post();
		$items[] = sn_feed_json_build_item( get_post() );
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
		'feed_url'      => home_url( '/?feed=json' ),
		'description'   => get_bloginfo( 'description' ),
		'language'      => get_bloginfo( 'language' ),
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
		esc_url( home_url( '/?feed=json' ) )
	);
}

if ( ! defined( 'SN_FEED_JSON_TEST' ) || ! SN_FEED_JSON_TEST ) {
	add_action( 'init', 'sn_feed_json_register' );
	add_filter( 'feed_content_type', 'sn_feed_json_content_type', 10, 2 );
	add_action( 'wp_head', 'sn_feed_json_head_link' );
}