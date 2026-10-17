<?php
/**
 * The pillar essays' runout (2026.10): the essay ends like a record's dead wax,
 * on one etched line with the signed record's own fields: its id, version, the
 * date of the latest cut, and the anchoring block once there is one, linked to
 * /verify for that record. Read from the plugin's sn_prov_view_data (never
 * sn_prov_note_uid, which mints an id), and appended after the post-content
 * element, so nothing inside the signed region changes. No record, no line.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The runout line for a signed pillar page, or ''.
 *
 * @param int $page_id The pillar page being viewed.
 * @return string
 */
function sn_essay_runout_html( $page_id ) {
	if ( ! function_exists( 'sn_prov_view_data' ) ) {
		return '';
	}
	$vm  = sn_prov_view_data( (int) $page_id );
	$uid = is_array( $vm ) ? (string) ( $vm['note_uid'] ?? '' ) : '';
	if ( '' === $uid || (int) ( $vm['version'] ?? 0 ) < 1 ) {
		return '';
	}
	$versions = (array) ( $vm['versions'] ?? array() );
	$latest   = (array) end( $versions );
	$cut      = substr( (string) ( $latest['committed_at'] ?? '' ), 0, 10 );
	$block    = (int) ( $vm['bitcoin_block'] ?? 0 );
	$fields   = array( 'JL', substr( $uid, 0, 8 ), 'v' . (int) $vm['version'] );
	if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $cut ) ) {
		$fields[] = 'cut ' . $cut;
	}
	// The plugin's own reading of the status, the one the page's chip shows:
	// 'confirmed' is anchored, and a genesis commit counts once its root is.
	$root  = function_exists( 'sn_prov_genesis_root_state' ) ? (array) sn_prov_genesis_root_state() : array();
	$state = function_exists( 'sn_prov_present_status' )
		? (string) ( sn_prov_present_status( (string) ( $vm['status'] ?? '' ), (string) ( $root['status'] ?? '' ) )['state'] ?? '' )
		: (string) ( $vm['status'] ?? '' );
	if ( ! empty( $vm['is_genesis_only'] ) ) {
		$block = (int) ( $root['bitcoin_block'] ?? 0 );
	}
	if ( 'confirmed' === $state ) {
		$fields[] = $block > 0 ? 'block ' . $block : 'verified';
	} else {
		$fields[] = 'anchoring';
	}
	$verify   = add_query_arg( 'note', $uid, (string) ( $vm['verify_url'] ?? '' ) );
	return '<p class="sn-essay-runout">' . esc_html( implode( ' · ', $fields ) ) . ' · <a href="' . esc_url( $verify ) . '">verify</a></p>';
}

/**
 * render_block_core/post-content filter: append the runout after the element.
 *
 * @param string        $block_content Rendered post content.
 * @param array         $block         Parsed block.
 * @param WP_Block|null $instance      Block instance.
 * @return string
 */
function sn_essay_runout_filter( $block_content, $block, $instance = null ) {
	$page_id = sn_pillar_eyebrow_page_id( $block, $instance );
	if ( ! $page_id || '' === sn_pillar_eyebrow_designation( $page_id ) ) {
		return $block_content;
	}
	return (string) $block_content . sn_essay_runout_html( $page_id );
}

if ( ! defined( 'SN_ESSAY_RUNOUT_TEST' ) || ! SN_ESSAY_RUNOUT_TEST ) {
	add_filter( 'render_block_core/post-content', 'sn_essay_runout_filter', 10, 3 );
}
