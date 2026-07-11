<?php
/**
 * Signal & Noise — content-as-data: the .json virtual route (machine-readability
 * sub-project C). Appending .json to any Note or Page URL returns a JSON
 * representation of its content. Flush-free (template_redirect pri 0, no
 * add_rewrite_rule), same mechanism as inc/agents-manifest.php.
 *
 * @package SignalNoise
 * @since 10.38.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure matcher: given a request URI, return the base path if it is a ".json"
 * content twin (e.g. "/notes/foo.json" → "/notes/foo"), else "". Rejects
 * non-.json paths, near-miss suffixes, and an empty base ("/.json").
 *
 * @param string $uri
 * @return string
 */
function sn_content_json_base_path( $uri ) {
	$path = strtok( (string) $uri, '?' );
	$path = '/' . ltrim( (string) $path, '/' );
	if ( '.json' !== substr( $path, -5 ) ) {
		return '';
	}
	$base = '/' . trim( substr( $path, 0, -5 ), '/' );
	return ( '/' === $base ) ? '' : $base;
}

/**
 * Resolve a request URI to a published singular post/page ID served as JSON, or
 * 0 if it is not a content twin. This resolution IS the scope gate — collections
 * and drafts return 0 and fall through to their natural 404.
 *
 * @param string $uri
 * @return int
 */
function sn_content_json_resolve( $uri ) {
	$base = sn_content_json_base_path( $uri );
	if ( '' === $base ) {
		return 0;
	}
	// Exclude collection/listing paths: the JSON Feed already serves the Notes
	// collection, and the /notes index renders its listing from a template (not
	// post_content), so its twin would be misleading. Filterable for future ones.
	$excluded = (array) apply_filters( 'sn_content_json_excluded_paths', array( '/notes' ) );
	if ( in_array( $base, $excluded, true ) ) {
		return 0;
	}
	$post_id = url_to_postid( home_url( $base . '/' ) );
	if ( ! $post_id ) {
		$post_id = url_to_postid( home_url( $base ) );
	}
	if ( ! $post_id ) {
		return 0;
	}
	$post = get_post( $post_id );
	// Serve only published, non-password-protected singular posts/pages. A
	// password-protected post is still status=publish (protection lives in
	// post_password_required(), which the raw the_content render bypasses), so
	// the password gate MUST be here or gated content leaks.
	if ( ! $post
		|| 'publish' !== $post->post_status
		|| '' !== (string) $post->post_password
		|| ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return 0;
	}
	// Exclude the static front page: its twin derives from the site root (a
	// malformed "host.json"), and the head-link + purge siblings already skip it.
	// Same predicate as sn_content_json_purge_url(), so advertise/purge/serve agree.
	$permalink = get_permalink( $post_id );
	if ( $permalink && rtrim( $permalink, '/' ) === rtrim( home_url( '/' ), '/' ) ) {
		return 0;
	}
	return (int) $post_id;
}

/**
 * Emit the 200 + application/json + the JSON document. status_header(200) is
 * REQUIRED (a postless .json path is a 404 by template_redirect; WP-REFERENCE #40).
 *
 * @param WP_Post|object $post
 */
function sn_content_json_send( $post ) {
	if ( function_exists( 'status_header' ) ) {
		status_header( 200 );
	}
	header( 'Content-Type: application/json; charset=utf-8' );
	$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- virtual route sets loop context for the_content.
	if ( function_exists( 'setup_postdata' ) ) {
		setup_postdata( $post );
	}
	$doc = sn_content_json_document( $post );
	if ( function_exists( 'wp_reset_postdata' ) ) {
		wp_reset_postdata();
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- application/json from wp_json_encode; HTML escaping would corrupt the JSON.
	echo wp_json_encode( $doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

/**
 * template_redirect handler: serve the JSON twin, else return (404 stands).
 */
function sn_content_json_maybe_serve() {
	$uri     = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$post_id = sn_content_json_resolve( $uri );
	if ( ! $post_id ) {
		return;
	}
	sn_content_json_send( get_post( $post_id ) );
	exit;
}

/**
 * Advertise the .json twin from a singular page's <head>.
 */
function sn_content_json_head_link() {
	// The front page has no meaningful .json twin (its URL is the site root, so
	// the derived twin would be a malformed "host.json").
	if ( function_exists( 'is_front_page' ) && is_front_page() ) {
		return;
	}
	if ( function_exists( 'is_singular' ) && ! is_singular( array( 'post', 'page' ) ) ) {
		return;
	}
	$permalink = get_permalink();
	if ( ! $permalink ) {
		return;
	}
	printf(
		'<link rel="alternate" type="application/json" href="%s" title="This page as JSON">' . "\n",
		esc_url( rtrim( $permalink, '/' ) . '.json' )
	);
}

/**
 * Advertise the content-as-data convention in sub-project A's discovery manifest.
 *
 * @param array<int,array<string,string>> $surfaces
 * @return array<int,array<string,string>>
 */
function sn_content_json_advertise_surface( $surfaces ) {
	$surfaces[] = array(
		'type'        => 'content-json',
		'url'         => function_exists( 'home_url' ) ? home_url( '/' ) : '',
		'format'      => 'application/json',
		'title'       => 'Content as JSON',
		'description' => 'Append .json to any Note or Page URL for a JSON representation of its content (title, canonical, breadcrumb, body, schema + provenance references).',
	);
	return $surfaces;
}

/**
 * Purge the .json twin alongside the HTML page when a post is published/updated
 * (rides the plugin's per-URL Cloudflare purge — inc/cloudflare-purge.php).
 *
 * @param array  $urls
 * @param int    $post_id
 * @param object $post
 * @return array
 */
function sn_content_json_purge_url( $urls, $post_id, $post ) {
	if ( $post && in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		$permalink = get_permalink( $post_id );
		// Skip the site root (a static front page): its twin would be malformed.
		if ( $permalink && rtrim( $permalink, '/' ) !== rtrim( home_url( '/' ), '/' ) ) {
			$urls[] = rtrim( $permalink, '/' ) . '.json';
		}
	}
	return $urls;
}

if ( ! defined( 'SN_CONTENT_JSON_TEST' ) || ! SN_CONTENT_JSON_TEST ) {
	add_action( 'template_redirect', 'sn_content_json_maybe_serve', 0 );
	add_action( 'wp_head', 'sn_content_json_head_link' );
	add_filter( 'sn_agents_surfaces', 'sn_content_json_advertise_surface' );
	add_filter( 'sn_cf_purge_urls_for_post', 'sn_content_json_purge_url', 10, 3 );
}
