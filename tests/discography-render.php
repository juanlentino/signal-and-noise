<?php
/**
 * Standalone fixture tests for the [sn_discography] cover-grid gallery
 * (theme v9.14.0 — /music redesign).
 *
 * The shortcode reads normalized discography entries off the cross-package
 * filter `sn_discography_entries` and renders a brutalist cover-grid: a sticky
 * controls rail (live count + role-filter chips), year-grouped sections of
 * album-cover cards (each carrying data-roles for client-side filtering +
 * data-spotify/data-type for lazy click-to-play), and a hidden empty state.
 *
 * Standalone-safe: plugin absent → filter yields array() → shortcode → ''.
 * Every external-data field is escaped; the server emits ZERO eager iframes.
 *
 * @since theme v9.14.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ── WP function stubs ─────────────────────────────────────────────────
$GLOBALS['__test_filters'] = array();
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $p = 10, $a = 1 ) { $GLOBALS['__test_filters'][ $hook ][] = $cb; return true; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		$args = func_get_args();
		foreach ( $GLOBALS['__test_filters'][ $hook ] ?? array() as $cb ) {
			$args[1] = call_user_func_array( $cb, array_slice( $args, 1 ) );
		}
		return $args[1];
	}
}
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $cb ) { $GLOBALS['shortcode_tags'][ $tag ] = $cb; return true; }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $u ) { $u = (string) $u; $u = str_replace( array( '"', "'", '<', '>', ' ' ), '', $u ); return str_replace( '&', '&amp;', $u ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $s, $d = 'default' ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = 'default' ) { return (string) $s; }
}
if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $n, $d = 'default' ) { return 1 === (int) $n ? $single : $plural; }
}

require __DIR__ . '/../inc/discography-render.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; } else { $fail++; echo "FAIL: $label\n"; }
}

echo "Discography cover-grid suite — theme v9.14.0\n\n";

// ── REGISTRATION ──────────────────────────────────────────────────────
ok( isset( $GLOBALS['shortcode_tags']['sn_discography'] ) && 'sn_discography_shortcode' === $GLOBALS['shortcode_tags']['sn_discography'], 'registers [sn_discography] → sn_discography_shortcode' );

// ── STANDALONE-SAFE EMPTY ─────────────────────────────────────────────
$GLOBALS['__test_filters']['sn_discography_entries'] = array( function () { return array(); } );
ok( sn_discography_shortcode() === '', 'empty store → "" (plugin absent / not synced)' );
$GLOBALS['__test_filters']['sn_discography_entries'] = array();
ok( sn_discography_shortcode() === '', 'no filter listener → "" (truly standalone)' );

// ── POPULATED ─────────────────────────────────────────────────────────
$entries = array(
	array( 'id' => 'a1', 'title' => 'New & Loud', 'artist' => 'Artist B', 'roles' => array( 'Producer', 'Mixing' ), 'year' => 2024, 'date' => '2024-05-01', 'type' => 'album', 'image' => 'https://i.scdn.co/image/new.jpg', 'spotify_id' => 'newAlbumId', 'spotify_url' => 'https://open.spotify.com/album/newAlbumId', 'muso_url' => 'https://credits.muso.ai/album/new' ),
	array( 'id' => 'a2', 'title' => 'Old Record', 'artist' => 'Artist A', 'roles' => array( 'Mastering' ), 'year' => 2005, 'date' => '2005-09-10', 'type' => 'single', 'image' => 'https://i.scdn.co/image/old.jpg', 'spotify_id' => 'oldAlbumId', 'spotify_url' => 'https://open.spotify.com/album/oldAlbumId', 'muso_url' => 'https://credits.muso.ai/album/old' ),
	// Unmatched (no Spotify) — kept, Muso-only, no play affordance.
	array( 'id' => 'a3', 'title' => 'Unlinked', 'artist' => 'Artist C', 'roles' => array( 'Engineer' ), 'year' => 2005, 'date' => '2005-01-01', 'type' => 'album', 'image' => 'https://i.scdn.co/image/x.jpg', 'spotify_id' => '', 'spotify_url' => '', 'muso_url' => 'https://credits.muso.ai/album/x' ),
);
$GLOBALS['__test_filters']['sn_discography_entries'] = array( function () use ( $entries ) { return array( $entries[1], $entries[2], $entries[0] ); } );
$html = sn_discography_shortcode();

ok( $html !== '', 'populated store → emits markup' );

// Controls rail: count + role chips.
ok( strpos( $html, 'sn-disco-controls' ) !== false, 'controls rail present' );
ok( strpos( $html, 'data-disco-count' ) !== false, 'count carries data-disco-count (JS updates it on filter)' );
ok( preg_match( '/data-disco-count[^>]*>\s*3\s*</', $html ), 'count shows total release count (3)' );
ok( strpos( $html, '2005' ) !== false && strpos( $html, '2024' ) !== false, 'count/year span shows the catalog range' );

// Filter chips: All + one per PRESENT role; none for absent roles.
ok( strpos( $html, 'data-role="*"' ) !== false, 'All chip present (data-role="*")' );
ok( strpos( $html, 'is-active' ) !== false, 'All chip is active by default' );
ok( preg_match( '/sn-disco-chip is-active"[^>]*aria-pressed="true"/', $html ) === 1, 'a11y: All chip exposes aria-pressed="true"' );
ok( preg_match( '/data-role="Producer"[^>]*aria-pressed="false"/', $html ) === 1, 'a11y: inactive role chips expose aria-pressed="false"' );
ok( strpos( $html, 'data-role="Producer"' ) !== false, 'chip for present role Producer' );
ok( strpos( $html, 'data-role="Mixing"' ) !== false, 'chip for present role Mixing' );
ok( strpos( $html, 'data-role="Engineer"' ) !== false, 'chip for present role Engineer' );
ok( strpos( $html, 'data-role="Mastering"' ) !== false, 'chip for present role Mastering' );
ok( strpos( $html, 'data-role="Composer"' ) === false, 'NO chip for an absent role (Composer)' );

// Preferred chip order: Producer before Mixing before Mastering before Engineer.
ok( strpos( $html, 'data-role="Producer"' ) < strpos( $html, 'data-role="Mixing"' ), 'chip order: Producer before Mixing (preferred order)' );
ok( strpos( $html, 'data-role="Mastering"' ) < strpos( $html, 'data-role="Engineer"' ), 'chip order: Mastering before Engineer' );

// Year grouping desc.
ok( strpos( $html, 'sn-disco-year' ) !== false, 'year sections present' );
ok( strpos( $html, 'New &amp; Loud' ) < strpos( $html, 'Old Record' ), 'entries ordered by year desc (2024 group before 2005)' );

// Cards + data attrs.
ok( strpos( $html, 'sn-disco-card' ) !== false, 'cover cards present' );
ok( strpos( $html, 'data-roles="Producer|Mixing"' ) !== false, 'card carries pipe-joined data-roles (filter source)' );
ok( strpos( $html, 'data-spotify="newAlbumId"' ) !== false, 'playable card carries data-spotify (album id)' );
ok( strpos( $html, 'data-type="album"' ) !== false, 'card carries data-type' );
ok( strpos( $html, 'sn-disco-cover-wrap' ) !== false, 'cover-wrap present' );
ok( strpos( $html, 'sn-disco-play-badge' ) !== false, 'play badge present on a playable card' );
ok( strpos( $html, 'role="button"' ) !== false, 'playable cover-wrap is a keyboard button (role=button)' );

// Meta.
ok( strpos( $html, 'sn-disco-title' ) !== false && strpos( $html, 'New &amp; Loud' ) !== false, 'title rendered (escaped)' );
ok( strpos( $html, 'Artist B' ) !== false, 'artist rendered' );
ok( strpos( $html, 'Producer · Mixing' ) !== false, 'roles rendered as middot-joined string' );
ok( strpos( $html, 'https://credits.muso.ai/album/new' ) !== false, 'per-card Muso credits link' );

// Cover img lazy; NO eager iframe.
ok( strpos( $html, 'loading="lazy"' ) !== false, 'cover img is lazy' );
ok( stripos( $html, '<iframe' ) === false, 'NO eager iframe in server markup' );

// Unmatched (no spotify_id): rendered, but NOT a play button.
ok( strpos( $html, 'Unlinked' ) !== false, 'unmatched album still rendered' );
ok( substr_count( $html, 'role="button"' ) === 2, 'only the 2 playable cards are buttons (unmatched is not)' );

// Empty state element present (hidden).
ok( strpos( $html, 'sn-disco-empty' ) !== false, 'hidden empty-state element present (for the no-match filter case)' );

// ── ESCAPING ──────────────────────────────────────────────────────────
$GLOBALS['__test_filters']['sn_discography_entries'] = array( function () {
	return array( array(
		'id' => 'x', 'title' => 'Bad <b>title</b>', 'artist' => 'A"onmouseover="x', 'roles' => array( '<script>r</script>' ),
		'year' => 2020, 'type' => 'album', 'image' => 'https://x/"onerror="alert(1)', 'spotify_id' => 'id"><img src=x>',
		'spotify_url' => 'https://open.spotify.com/album/x', 'muso_url' => 'https://credits.muso.ai/x',
	) );
} );
$evil = sn_discography_shortcode();
ok( strpos( $evil, '<b>title</b>' ) === false, 'hostile title escaped' );
ok( strpos( $evil, '<script>r</script>' ) === false, 'hostile role escaped' );
ok( strpos( $evil, '"onerror="alert(1)' ) === false, 'hostile image URL esc_url\'d' );
ok( strpos( $evil, 'id"><img src=x>' ) === false, 'hostile spotify_id esc_attr\'d' );

// ── B2: PER-ROLE COUNTS (v10.7.0) ─────────────────────────────────────
// Roles deliberately repeat so counts vary (a count that is always 1 is a
// weak assertion). Mixing×3, Producer×2, total 4.
$counted = array(
	array( 'id' => 'm1', 'title' => 'Mix One',   'roles' => array( 'Mixing' ),               'year' => 2024, 'type' => 'album', 'spotify_id' => '' ),
	array( 'id' => 'm2', 'title' => 'Mix Two',   'roles' => array( 'Mixing', 'Producer' ),   'year' => 2023, 'type' => 'album', 'spotify_id' => '' ),
	array( 'id' => 'm3', 'title' => 'Mix Three', 'roles' => array( 'Mixing' ),               'year' => 2022, 'type' => 'album', 'spotify_id' => '' ),
	array( 'id' => 'p1', 'title' => 'Prod One',  'roles' => array( 'Producer' ),             'year' => 2021, 'type' => 'album', 'spotify_id' => '' ),
);
ok( function_exists( 'sn_discography_count_for_role' ), 'sn_discography_count_for_role() is defined' );
ok( sn_discography_count_for_role( $counted, 'Mixing' ) === 3, 'count helper: Mixing credited on 3 releases' );
ok( sn_discography_count_for_role( $counted, 'Producer' ) === 2, 'count helper: Producer credited on 2 releases' );
ok( sn_discography_count_for_role( $counted, 'Mastering' ) === 0, 'count helper: absent role counts 0' );

$GLOBALS['__test_filters']['sn_discography_entries'] = array( function () use ( $counted ) { return $counted; } );
$ch = sn_discography_shortcode();
ok( preg_match( '/data-role="\*"[^>]*data-count="4"/', $ch ) === 1, 'All chip carries the total count (data-count="4")' );
ok( preg_match( '/data-role="Mixing"[^>]*data-count="3"/', $ch ) === 1, 'Mixing chip carries its per-role count (data-count="3")' );
ok( preg_match( '/data-role="Producer"[^>]*data-count="2"/', $ch ) === 1, 'Producer chip carries its per-role count (data-count="2")' );
ok( strpos( $ch, 'sn-disco-chip__count' ) !== false, 'chips render a visible count badge (.sn-disco-chip__count)' );
ok( preg_match( '/sn-disco-chip__count">3</', $ch ) === 1, 'Mixing badge shows the visible figure (3)' );
// data-count is still between data-role and aria-pressed → existing a11y assertion holds.
ok( preg_match( '/data-role="Producer"[^>]*aria-pressed="false"/', $ch ) === 1, 'a11y: per-role chips keep aria-pressed="false" with the count present' );

// ── B2: URL-ADDRESSABLE FILTER (discography.js static contract) ───────
$djs = file_get_contents( __DIR__ . '/../assets/js/discography.js' );
ok( strpos( $djs, 'URLSearchParams' ) !== false, 'discography.js reads the query string via URLSearchParams' );
ok( preg_match( "/get\(\s*'role'\s*\)/", $djs ) === 1, "discography.js reads the ?role= param" );
ok( strpos( $djs, 'pushState' ) !== false, 'discography.js writes the role to history (pushState)' );
ok( strpos( $djs, 'popstate' ) !== false, 'discography.js restores the filter on back/forward (popstate)' );
ok( preg_match( "/getAttribute\(\s*'data-role'\s*\)/", $djs ) === 1, 'role is matched against chip data-role values (no selector built from the raw param)' );

// ── B1: liner-notes panel (sn_discography_render_liner) ──────────────
ok( function_exists( 'sn_discography_render_liner' ), 'sn_discography_render_liner() is defined' );
ok( sn_discography_render_liner( array( 'title' => 'X', 'tracks' => array() ) ) === '', 'no tracks → no liner panel' );
ok( sn_discography_render_liner( array( 'title' => 'X' ) ) === '', 'missing tracks key → no liner panel (back-compat)' );

$liner = sn_discography_render_liner( array( 'title' => 'Rec', 'tracks' => array(
	array( 'title' => 'Opener', 'roles' => array( 'Producer', 'Mixing' ), 'preview_url' => 'https://p.scdn.co/mp3-preview/o', 'spotify_id' => 's1' ),
	array( 'title' => 'No Preview Track', 'roles' => array( 'Engineer' ), 'preview_url' => '', 'spotify_id' => '' ),
) ) );
ok( strpos( $liner, '<details class="sn-disco-liner"' ) !== false, 'renders a native <details> disclosure (works JS-off)' );
ok( strpos( $liner, '<summary' ) !== false && strpos( $liner, '2 tracks' ) !== false, 'summary shows the track count' );
ok( strpos( $liner, 'sn-disco-tracklist' ) !== false, 'renders the tracklist <ol>' );
ok( strpos( $liner, 'Opener' ) !== false && strpos( $liner, 'Producer · Mixing' ) !== false, 'per-track title + middot-joined credits' );
ok( strpos( $liner, 'data-preview="https://p.scdn.co/mp3-preview/o"' ) !== false, 'a previewable track carries a play button with data-preview' );
ok( substr_count( $liner, 'sn-disco-track__play"' ) === 1, 'only the track WITH a preview gets a play button' );
ok( strpos( $liner, 'sn-disco-track__noplay' ) !== false, 'a track without a preview gets a no-play spacer (titles stay aligned)' );
ok( strpos( $liner, 'aria-pressed="false"' ) !== false, 'play button is a toggle (aria-pressed)' );

// Escaping: every track field is escaped at the sink.
$evil_liner = sn_discography_render_liner( array( 'title' => 'X', 'tracks' => array(
	array( 'title' => 'Bad <b>x</b>', 'roles' => array( '<script>r</script>' ), 'preview_url' => 'https://x/"onerror="alert(1)', 'spotify_id' => 'z' ),
) ) );
ok( strpos( $evil_liner, '<b>x</b>' ) === false, 'hostile track title escaped' );
ok( strpos( $evil_liner, '<script>r</script>' ) === false, 'hostile track role escaped' );
ok( strpos( $evil_liner, '"onerror="alert(1)' ) === false, 'hostile preview_url esc_url\'d' );

// End-to-end: the shortcode renders the panel for an entry carrying tracks.
$GLOBALS['__test_filters']['sn_discography_entries'] = array( function () {
	return array( array( 'id' => 'e1', 'title' => 'Album', 'year' => 2024, 'type' => 'album', 'spotify_id' => '', 'tracks' => array(
		array( 'title' => 'Track A', 'roles' => array( 'Producer' ), 'preview_url' => 'https://p.scdn.co/mp3-preview/a', 'spotify_id' => 'sa' ),
	) ) );
} );
ok( strpos( sn_discography_shortcode(), 'sn-disco-liner' ) !== false, 'shortcode: an entry with tracks renders the liner panel' );

// JS contract: the player is wired (cookieless native Audio, one-at-a-time).
$djs2 = file_get_contents( __DIR__ . '/../assets/js/discography.js' );
ok( strpos( $djs2, 'sn-disco-track__play' ) !== false && strpos( $djs2, 'new Audio' ) !== false, 'discography.js wires a native Audio() for previews (no embed/cookie)' );
ok( strpos( $djs2, "addEventListener( 'error'" ) !== false, 'discography.js retires a dead preview on the audio error event' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
