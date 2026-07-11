<?php
/**
 * Signal & Noise — content-as-data: the JSON document builder. Assembles a
 * per-URL JSON representation of a Note or Page (machine-readability sub-project
 * C). Testable via stubbed WP functions (mirrors inc/feed-json.php's builder).
 *
 * @package SignalNoise
 * @since 10.38.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Breadcrumb trail as an array of { name, url }. Notes: Home → Notes → self.
 * Pages: Home → (ancestors, root-first) → self.
 *
 * @param WP_Post|object $post
 * @return array<int,array{name:string,url:string}>
 */
function sn_content_json_breadcrumb( $post ) {
	$crumbs = array(
		array( 'name' => 'Home', 'url' => home_url( '/' ) ),
	);
	if ( 'post' === get_post_type( $post ) ) {
		$crumbs[] = array( 'name' => 'Notes', 'url' => home_url( '/notes/' ) );
	} else {
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
			$crumbs[] = array(
				'name' => wp_strip_all_tags( get_the_title( $ancestor_id ) ),
				'url'  => get_permalink( $ancestor_id ),
			);
		}
	}
	$crumbs[] = array(
		'name' => wp_strip_all_tags( get_the_title( $post ) ),
		'url'  => get_permalink( $post ),
	);
	return $crumbs;
}

/**
 * Build the full content-as-data document for a singular post/page. The caller
 * sets up the post (setup_postdata) so the_content renders blocks/shortcodes.
 *
 * @param WP_Post|object $post
 * @return array<string,mixed>
 */
function sn_content_json_document( $post ) {
	$is_post   = ( 'post' === get_post_type( $post ) );
	$permalink = get_permalink( $post );
	$html      = (string) apply_filters( 'the_content', $post->post_content );
	$text      = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) );

	$doc = array(
		'url'            => $permalink,
		'type'           => $is_post ? 'note' : 'page',
		'title'          => wp_strip_all_tags( get_the_title( $post ) ),
		'date_published' => get_the_date( 'c', $post ),
		'date_modified'  => get_the_modified_date( 'c', $post ),
		'author'         => array(
			'name' => 'Juan Lentino',
			'url'  => home_url( '/about/' ),
		),
		'breadcrumb'     => sn_content_json_breadcrumb( $post ),
		'content_html'   => $html,
		'content_text'   => $text,
		'schema'         => array(
			'type'        => $is_post ? 'Article' : 'WebPage',
			'embedded_in' => 'the page <head> as a schema.org @graph (JSON-LD)',
			'page_url'    => $permalink,
		),
	);

	// Provenance applies to Notes only (Pages carry no authorship proof).
	if ( $is_post ) {
		$doc['provenance'] = array(
			'verify_url' => home_url( '/provenance/verify/' ),
			'note'       => 'This Note carries a Bitcoin-anchored authorship proof.',
		);
	}

	return $doc;
}
