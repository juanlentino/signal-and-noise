<?php
/**
 * Signal & Noise: nothing foreign inside the signed content.
 *
 * A note's post-content element is what the public ledger's checker reads and
 * compares with the signed record. On 2026-10-02 MailPoet's slide-in form was
 * switched on for posts: MailPoet appends its forms to `the_content`, so the
 * form's markup landed inside that element and all 50 notes failed the public
 * served-page check for two days, with every signature and hash intact.
 *
 * MailPoet has its own second path: when its `the_content` callback did not
 * run for a singular page but is still registered at `wp_footer`, it prints
 * its overlay forms (slide-in, pop-up, fixed bar) in the footer. Those are
 * position: fixed, so where they sit in the document does not change what a
 * reader sees. So on a signed singular (every note; a page only when it
 * carries a provenance UID) the callback is taken off before the
 * page renders and put back just before MailPoet's footer hook asks for it.
 *
 * A "below the post" form is not an overlay and is not printed by that footer
 * path, so it does not show on a signed page at all. That is the rule, not an
 * accident: nothing is appended to signed content.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class and method of each known appender. Filter: `sn_signed_content_appenders`. */
const SN_SIGNED_CONTENT_APPENDERS = array(
	array( 'MailPoet\\Form\\DisplayFormInWPContent', 'contentDisplay' ),
);

/**
 * Find the known appenders among a hook's callbacks. PURE.
 *
 * @param array<int,array<string,array>> $callbacks WP_Hook::$callbacks: priority => id => { function, accepted_args }.
 * @param array<int,array{0:string,1:string}> $known Class and method pairs.
 * @return array<int,array{priority:int,function:callable,accepted_args:int}>
 */
function sn_signed_content_find_appenders( array $callbacks, array $known ) {
	$found = array();
	foreach ( $callbacks as $priority => $entries ) {
		foreach ( (array) $entries as $entry ) {
			$fn = $entry['function'] ?? null;
			if ( ! is_array( $fn ) || ! is_object( $fn[0] ?? null ) || ! is_string( $fn[1] ?? null ) ) {
				continue;
			}
			foreach ( $known as $pair ) {
				if ( is_a( $fn[0], (string) $pair[0] ) && $fn[1] === (string) $pair[1] ) {
					$found[] = array( 'priority' => (int) $priority, 'function' => $fn, 'accepted_args' => (int) ( $entry['accepted_args'] ?? 1 ) );
				}
			}
		}
	}
	return $found;
}

/**
 * Is this request a signed singular? Every note is. A page is only when it
 * opted into signing and carries a provenance UID (inc/content-json-document.php);
 * an ordinary page keeps whatever a plugin appends to it.
 *
 * @return bool
 */
function sn_signed_content_is_signed() {
	if ( is_singular( 'post' ) ) {
		return true;
	}
	return is_singular( 'page' ) && '' !== (string) get_post_meta( (int) get_queried_object_id(), '_sn_prov_uid', true );
}

/**
 * On a signed singular (a note, or a page that is signed), take the appenders off `the_content`.
 *
 * @return void
 */
function sn_signed_content_detach() {
	global $wp_filter;
	if ( ! sn_signed_content_is_signed() || empty( $wp_filter['the_content']->callbacks ) ) {
		return;
	}
	$known = (array) apply_filters( 'sn_signed_content_appenders', SN_SIGNED_CONTENT_APPENDERS );
	$found = sn_signed_content_find_appenders( (array) $wp_filter['the_content']->callbacks, $known );
	foreach ( $found as $a ) {
		remove_filter( 'the_content', $a['function'], $a['priority'] );
	}
	$GLOBALS['sn_signed_content_detached'] = $found;
}
add_action( 'template_redirect', 'sn_signed_content_detach', 1 );

/**
 * Put them back before the footer hooks run, so MailPoet's own footer path
 * finds its callback registered and prints the overlay forms there.
 *
 * @return void
 */
function sn_signed_content_reattach() {
	foreach ( (array) ( $GLOBALS['sn_signed_content_detached'] ?? array() ) as $a ) {
		add_filter( 'the_content', $a['function'], $a['priority'], $a['accepted_args'] );
	}
	$GLOBALS['sn_signed_content_detached'] = array();
}
add_action( 'wp_footer', 'sn_signed_content_reattach', 1 );
