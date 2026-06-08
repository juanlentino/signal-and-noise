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
		'feed_url'      => home_url( '/feed/json/' ),
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
		'id'             => (string) get_permalink( $post ),
		'url'            => get_permalink( $post ),
		'title'          => get_the_title( $post ),
		'content_html'   => (string) apply_filters( 'the_content', $post->post_content ),
		'date_published' => get_post_time( 'c', true, $post ),
		'date_modified'  => get_post_modified_time( 'c', true, $post ),
	);
	if ( $tags ) { $item['tags'] = $tags; }
	if ( has_excerpt( $post ) ) {
		$ex = get_the_excerpt( $post );
		if ( '' !== $ex ) { $item['summary'] = $ex; }
	}
	return $item;
}

if ( ! defined( 'SN_FEED_JSON_TEST' ) || ! SN_FEED_JSON_TEST ) {
	add_action( 'init', 'sn_feed_json_register' );
	add_filter( 'feed_content_type', 'sn_feed_json_content_type', 10, 2 );
}