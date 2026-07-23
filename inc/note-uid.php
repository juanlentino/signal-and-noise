<?php
/**
 * Signal & Noise — canonical Note-uid read.
 *
 * The companion plugin owns the `_sn_prov_uid` post meta (the key a Note's
 * Bitcoin-anchored authorship credential is filed under; literal key here —
 * the constant lives plugin-side, the precedent every reader already cites).
 * Before v10.49.0 the lowercase+trim normalization was INLINED in three
 * modules (inc/content-json-document.php, inc/feed-json.php,
 * inc/feed-enrichment.php) and had already drifted: the .json twin lacked
 * the trim. Since the /verify docket resolves a pasted Note URL by matching
 * this exact value, normalization drift between the surfaces is a
 * verification bug, not a cosmetic one — so the read lives here, once.
 *
 * @package SignalNoise
 * @since 10.49.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Note's provenance uid, normalized: lowercased + trimmed. '' when the
 * meta is absent, whitespace-only, or the meta seam is unavailable (the
 * standalone fixtures only stub what they exercise).
 *
 * @param int $post_id Post id.
 * @return string
 */
function sn_theme_note_uid( $post_id ) {
	if ( ! function_exists( 'get_post_meta' ) ) {
		return '';
	}
	return strtolower( trim( (string) get_post_meta( (int) $post_id, '_sn_prov_uid', true ) ) );
}
