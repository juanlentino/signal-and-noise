<?php
/**
 * Signal & Noise — /.well-known/agents.json (machine-surfaces discovery manifest).
 *
 * Sub-project A of the machine-readability program (roadmap:
 * docs/superpowers/specs/2026-07-11-machine-readability-program-roadmap.md).
 *
 * A single machine-readable "front door": one JSON index that enumerates every
 * machine surface the site already exposes (llms.txt, feeds, WebSub, OpenSearch,
 * sitemap, the Abilities/REST base, provenance-verify), so an agent or crawler
 * discovers all of them from one entry point instead of knowing each convention
 * independently. The surface list is PURE + FILTERABLE (`sn_agents_surfaces`) so
 * later phases (the MCP endpoint = sub-project B) append their entry with one
 * filter callback and no edit here.
 *
 * Same flush-free virtual-route mechanism as /.well-known/gpc.json + security.txt
 * (template_redirect priority 0). `updated` is a FIXED date (not request-time) so
 * the resource is byte-stable and edge-cacheable. Advertised from HTML via a
 * <head> <link> (sn_agents_head_link) and from /llms.txt via a "Machine surfaces"
 * section (see inc/llms-txt.php — wired at release, see the A handoff).
 *
 * @package SignalNoise
 * @since 10.37.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SN_AGENTS_MANIFEST_PATH' ) ) {
	// The well-known location. Bespoke name (no dominant "agent discovery"
	// standard exists; llms.txt covers the LLM-markdown angle — this is the
	// programmatic index). If this ever changes, update the matcher + head link.
	define( 'SN_AGENTS_MANIFEST_PATH', '/.well-known/agents.json' );
}

if ( ! defined( 'SN_AGENTS_UPDATED' ) ) {
	// Date the surface set was last materially affirmed. Bump when a surface is
	// added/removed — keeps the JSON cache-stable between real changes.
	define( 'SN_AGENTS_UPDATED', '2026-07-11' );
}

/**
 * Is this request for the manifest? Pure helper (takes the path) so it is
 * testable without $_SERVER. Matches the exact well-known path, with or without a
 * query string / trailing slash; rejects near-misses.
 *
 * @param string $uri Request URI (may carry a query string).
 * @return bool
 */
function sn_agents_is_request( $uri ) {
	$path = strtok( (string) $uri, '?' );
	$path = '/' . trim( (string) $path, '/' );
	return ( SN_AGENTS_MANIFEST_PATH === $path );
}

/**
 * The machine surfaces the site exposes. Pure + FILTERABLE via `sn_agents_surfaces`
 * so later phases append entries (e.g. sub-project B's MCP endpoint) without
 * editing this file. Each entry: { type, url (absolute), title, description,
 * format }. URLs are absolute (home_url) so an off-site agent follows them directly.
 *
 * @return array<int,array<string,string>>
 */
function sn_agents_surfaces() {
	$home = function_exists( 'home_url' ) ? home_url() : '';
	$surfaces = array(
		array( 'type' => 'llms-txt',  'url' => $home . '/llms.txt',      'format' => 'text/markdown',     'title' => 'llms.txt',      'description' => 'Curated key pages + feeds for LLM answer engines.' ),
		array( 'type' => 'llms-full', 'url' => $home . '/llms-full.txt', 'format' => 'text/markdown',     'title' => 'llms-full.txt', 'description' => 'llms.txt plus a recent-Notes corpus section.' ),
		array( 'type' => 'feed-rss',  'url' => $home . '/feed/',         'format' => 'application/rss+xml', 'title' => 'RSS feed',     'description' => 'Subscribe to new Notes.' ),
		array( 'type' => 'feed-json', 'url' => $home . '/feed/json/',    'format' => 'application/feed+json', 'title' => 'JSON Feed',  'description' => 'Machine-readable Notes feed (JSON Feed 1.1).' ),
		array( 'type' => 'opensearch', 'url' => $home . '/opensearch.xml', 'format' => 'application/opensearchdescription+xml', 'title' => 'OpenSearch', 'description' => 'Search-provider description over /notes/?s=.' ),
		array( 'type' => 'sitemap',   'url' => $home . '/wp-sitemap.xml', 'format' => 'application/xml',  'title' => 'Sitemap',      'description' => 'Core WordPress sitemap index.' ),
		array( 'type' => 'abilities', 'url' => $home . '/wp-json/wp-abilities/v1/abilities', 'format' => 'application/json', 'title' => 'Abilities API', 'description' => 'The agent/automation surface: discover + run site abilities.' ),
		array( 'type' => 'provenance-verify', 'url' => $home . '/provenance/verify/', 'format' => 'text/html', 'title' => 'Provenance verify', 'description' => "Verify a Note's cryptographic (Bitcoin-anchored) authorship proof." ),
	);

	/**
	 * Filter the machine-surfaces list. Later phases append entries (e.g. the MCP
	 * endpoint) here. Callbacks MUST return entries with at least `type` + `url`.
	 *
	 * @param array<int,array<string,string>> $surfaces
	 */
	$surfaces = (array) apply_filters( 'sn_agents_surfaces', $surfaces );

	// Defensive: drop malformed entries (a bad filter callback can't corrupt the doc).
	return array_values(
		array_filter(
			$surfaces,
			static function ( $s ) {
				return is_array( $s ) && ! empty( $s['type'] ) && ! empty( $s['url'] );
			}
		)
	);
}

/**
 * The full manifest array (site block + updated + surfaces + structured-data note).
 *
 * @return array<string,mixed>
 */
function sn_agents_manifest() {
	$home = function_exists( 'home_url' ) ? home_url() : '';
	return array(
		'site'            => array(
			'name'        => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '',
			'url'         => $home,
			'description' => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'description' ) : '',
		),
		'updated'         => SN_AGENTS_UPDATED,
		'surfaces'        => sn_agents_surfaces(),
		'structured_data' => array(
			'type'        => 'JSON-LD',
			'embedded_in' => 'the <head> of every page (schema.org @graph)',
		),
	);
}

/**
 * Encode the manifest. Pretty + unescaped slashes so URLs are human-legible.
 *
 * @return string
 */
function sn_agents_json_body() {
	return (string) wp_json_encode( sn_agents_manifest(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
}

/**
 * Emit the 200 status + application/json header + body. status_header( 200 ) is
 * REQUIRED (postless virtual path → 404 by template_redirect; WP-REFERENCE #40).
 */
function sn_agents_send() {
	if ( function_exists( 'status_header' ) ) {
		status_header( 200 );
	}
	header( 'Content-Type: application/json; charset=utf-8' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- application/json document from wp_json_encode; HTML escaping would corrupt the JSON.
	echo sn_agents_json_body();
}

/**
 * template_redirect handler: serve the manifest, then exit.
 */
function sn_agents_maybe_serve() {
	$req = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( ! sn_agents_is_request( $req ) ) {
		return;
	}
	sn_agents_send();
	exit;
}

/**
 * Advertise the manifest from HTML: a <link> in <head> so an agent starting from
 * any page finds the machine index. rel="alternate" + the JSON type is the safe,
 * valid signal (see the A handoff for the bespoke-rel alternative).
 */
function sn_agents_head_link() {
	$href = ( function_exists( 'home_url' ) ? home_url( SN_AGENTS_MANIFEST_PATH ) : SN_AGENTS_MANIFEST_PATH );
	printf(
		'<link rel="alternate" type="application/json" href="%s" title="Machine surfaces manifest">' . "\n",
		esc_url( $href )
	);
}

if ( ! defined( 'SN_AGENTS_TEST' ) || ! SN_AGENTS_TEST ) {
	add_action( 'template_redirect', 'sn_agents_maybe_serve', 0 );
	add_action( 'wp_head', 'sn_agents_head_link' );
}
