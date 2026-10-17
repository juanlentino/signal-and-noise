<?php
/**
 * Signal & Noise: the print colophon.
 *
 * A printed note carries its own proof. On screen the provenance panel links
 * to the ledger and to /verify, and shows the first 12 characters of each
 * version's hash; on paper a link is only its text, and 12 characters prove
 * nothing. The colophon writes out what a reader holding the page needs: the
 * full SHA-256 of the signed text, the version and its status, and the verify,
 * ledger and note addresses as text they can type.
 *
 * Hidden on screen, shown in print (blocks/print-colophon/style.css). The data
 * is the companion plugin's public view-model, read the way the chip and the
 * panel read it (inc/provenance-surface.php): every helper is
 * function_exists()-guarded, and without the plugin, or for a note with no
 * chain, nothing renders.
 *
 * Placed in parts/post-closing.html, inside the closing <footer>: outside the
 * verifier's cut of the page (normalize/extract-content.mjs stops at the
 * first footer), so nothing here can reach the signed prose.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The colophon markup for the current note, or '' when there is no record.
 *
 * One line, no newlines: sn_php_block_output() runs wpautop over it.
 *
 * @return string
 */
function sn_print_colophon_html() {
	if ( ! function_exists( 'sn_prov_view_data' ) ) {
		return '';
	}
	$vm = sn_prov_view_data( get_the_ID() );
	if ( ! is_array( $vm ) || '' === (string) ( $vm['note_uid'] ?? '' ) ) {
		return '';
	}

	$uid     = (string) $vm['note_uid'];
	$genesis = ! empty( $vm['is_genesis_only'] );
	$version = (int) ( $vm['version'] ?? 0 );

	// Status exactly as the panel presents it: a founding-snapshot note reads
	// Verified once the snapshot itself is anchored, never "Recording".
	$root  = function_exists( 'sn_prov_genesis_root_state' ) ? sn_prov_genesis_root_state() : array( 'status' => '', 'bitcoin_block' => 0 );
	$pres  = function_exists( 'sn_prov_present_status' )
		? sn_prov_present_status( (string) ( $vm['status'] ?? '' ), (string) ( $root['status'] ?? '' ) )
		: array( 'label' => ucfirst( (string) ( $vm['status'] ?? '' ) ) );
	$block = (int) ( $vm['bitcoin_block'] ?? 0 );
	if ( 0 === $block && 'genesis' === ( $vm['status'] ?? '' ) ) {
		$block = (int) ( $root['bitcoin_block'] ?? 0 );
	}
	$status = 'Status: ' . (string) ( $pres['label'] ?? '' ) . ( $block > 0 ? ', anchored in Bitcoin block ' . number_format( $block ) : '' ) . '.';

	// The date the shown version was recorded.
	$versions = (array) ( $vm['versions'] ?? array() );
	$last     = $versions ? (array) end( $versions ) : array();
	$ts       = '' !== (string) ( $last['committed_at'] ?? '' ) ? strtotime( (string) $last['committed_at'] ) : false;
	$when     = $ts ? ', recorded ' . wp_date( 'F j, Y', $ts ) : '';

	// The verify address the chip links, written out: note= always, v= unless
	// the note has only its founding snapshot. A signed page's base already
	// carries ?kind=, so the join character follows the base.
	$verify = (string) ( $vm['verify_url'] ?? '' );
	$verify = $verify . ( false === strpos( $verify, '?' ) ? '?' : '&' ) . 'note=' . rawurlencode( $uid ) . ( $genesis ? '' : '&v=' . $version );

	$link = static function ( $url ) {
		$href = esc_url( (string) $url );
		return '' === $href ? '' : '<a href="' . $href . '">' . esc_html( (string) $url ) . '</a>';
	};

	$copy   = $genesis ? 'This is a printed copy of this note as attested in the founding snapshot' : 'This is a printed copy of version ' . $version . ' of this note';
	$source = $link( get_permalink( get_the_ID() ) );
	$ledger = $link( (string) ( $vm['ledger_url'] ?? '' ) );

	return '<aside class="sn-print-colophon" aria-label="' . esc_html( __( 'Printed record', 'signal-and-noise' ) ) . '">'
		. '<p class="sn-print-colophon__label">' . esc_html( __( 'Printed record', 'signal-and-noise' ) ) . '</p>'
		. '<p>' . esc_html( $copy . $when ) . ( '' === $source ? '.' : ', from ' . $source . '.' ) . '</p>'
		. '<p>' . esc_html( $status ) . '</p>'
		. '<p>SHA-256 of the signed text: <code>' . esc_html( (string) ( $vm['current_hash'] ?? '' ) ) . '</code></p>'
		. '<p>Check it against the public record at ' . $link( $verify ) . '.' . ( '' === $ledger ? '' : ' The ledger is at ' . $ledger . '.' ) . '</p>'
		. '</aside>';
}
