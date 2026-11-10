<?php
/**
 * The machine files, registered with the rewrite API (2026-11-10).
 *
 * /opensearch.xml, /humans.txt, /.well-known/security.txt, /llms.txt and
 * /llms-full.txt have no backing post. Each handler matches REQUEST_URI at
 * template_redirect priority 0, by which time WordPress has resolved the
 * request as a 404 and sent nocache headers (cache-headers-hooks.php undoes
 * them). Here each path also gets a rewrite rule and the sn_machine query
 * var, the way core registers robots.txt, so WordPress resolves the request
 * as a known route, not a 404. The same template_redirect handlers then
 * answer it, after send_headers and wp have run (the plugin's security
 * headers and trackers hook those), so nothing they add is skipped.
 *
 * The rules take effect at the plugin's next rewrite flush; the theme never
 * flushes. Until that flush is confirmed live, the handlers' REQUEST_URI
 * match, their status_header( 200 ) and the 404 undo stay.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_MACHINE_ROUTES = array(
	'opensearch' => '^opensearch\\.xml$',
	'humans'     => '^humans\\.txt$',
	'security'   => '^\\.well-known/security\\.txt$',
	'llms'       => '^llms\\.txt$',
	'llms-full'  => '^llms-full\\.txt$',
);

add_action(
	'init',
	static function () {
		foreach ( SN_MACHINE_ROUTES as $key => $regex ) {
			add_rewrite_rule( $regex, 'index.php?sn_machine=' . $key, 'top' );
		}
	}
);

add_filter(
	'query_vars',
	static function ( $vars ) {
		$vars[] = 'sn_machine';
		return $vars;
	}
);
