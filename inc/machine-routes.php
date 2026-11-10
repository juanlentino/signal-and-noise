<?php
/**
 * The machine files, registered with the rewrite API (2026-11-10).
 *
 * /opensearch.xml, /humans.txt, /.well-known/security.txt, /llms.txt and
 * /llms-full.txt have no backing post. Each path gets a rewrite rule and the
 * sn_machine query var, the way core registers robots.txt, so WordPress
 * resolves the request as a known route (a 200), not a 404. Each file's
 * template_redirect handler (priority 0) answers on the query var alone,
 * after send_headers and wp have run (the plugin's security headers and
 * trackers hook those), so nothing they add is skipped.
 *
 * Flush safety: a rule only works once the rewrite rules are flushed. The
 * theme stores a signature of SN_MACHINE_ROUTES and, on wp_loaded (after
 * every init rule is registered), soft-flushes once when the stored one
 * differs: a fresh install, or a release that changes this list. Every other
 * request is one autoloaded option read.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_MACHINE_ROUTES_OPTION = 'sn_machine_routes_signature';

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

add_action(
	'wp_loaded',
	static function () {
		$signature = md5( serialize( SN_MACHINE_ROUTES ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- a fingerprint of a constant array, never unserialized.
		if ( get_option( SN_MACHINE_ROUTES_OPTION ) === $signature ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( SN_MACHINE_ROUTES_OPTION, $signature, true );
	}
);
