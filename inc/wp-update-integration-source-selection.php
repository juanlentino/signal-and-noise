<?php
/**
 * WP update integration: the unpacked-source rename.
 *
 * Moved verbatim out of inc/wp-update-integration.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rename the unpacked source directory so WP installs to the correct
 * stylesheet slug.
 *
 * GitHub's auto-generated tag archive (`/archive/refs/tags/v8.5.1.zip`)
 * unpacks to `signal-and-noise-8.5.1/` — with the version suffix but
 * without the leading 'v'. WP's installer uses the dir name to decide
 * where to install, which would end up as
 * `wp-content/themes/signal-and-noise-8.5.1/` (wrong slug, the theme
 * would deactivate on update because the active stylesheet
 * `signal-and-noise` would no longer resolve).
 *
 * The filter receives `$source` (path to the unpacked dir) and renames
 * it to drop the version suffix. Standard pattern for GitHub-hosted
 * themes that ship via auto-generated tag archives.
 *
 * Note: `$hook_extra['theme']` is the slug for theme installs (mirrors
 * the plugin-side filter's `$hook_extra['plugin']` basename). Guarding
 * on this prevents us from renaming other themes that pass through the
 * same filter during a multi-update batch.
 */
/**
 * Does this unpacked package directory contain OUR theme?
 *
 * Used only on the UPLOAD path, where WP gives us no `$hook_extra['theme']` to
 * gate on and the directory name is whatever GitHub's archive produced. Keys on
 * the exact Text Domain, which no other theme can legitimately carry.
 *
 * Deliberately stricter than the retired mu-plugin this replaces, which matched
 * `strpos( $data['Name'], 'Signal' )` and would therefore have claimed — and
 * renamed, and thereby broken — any theme called "Signal Boost" or "Signals".
 *
 * @since 12.7.0
 * @param string $source Absolute path to the unpacked package directory.
 * @return bool
 */
function sn_gh_theme_source_is_ours( $source ) {
	if ( ! function_exists( 'get_file_data' ) ) {
		return false;
	}

	$style = trailingslashit( (string) $source ) . 'style.css';
	if ( ! is_readable( $style ) ) {
		return false;
	}

	$data = get_file_data( $style, array( 'TextDomain' => 'Text Domain' ) );

	return SN_GH_THEME_TEXT_DOMAIN === trim( (string) ( $data['TextDomain'] ?? '' ) );
}

add_filter( 'upgrader_source_selection', function( $source, $remote_source, $upgrader, $hook_extra ) {
	$theme = isset( $hook_extra['theme'] ) ? (string) $hook_extra['theme'] : '';
	$ours  = ( SN_GH_THEME_STYLESHEET === $theme );

	// v12.7.0 — also claim a manual UPLOAD.
	//
	// `$hook_extra['theme']` is set for an update and unset for an Upload
	// Theme, so the gate above can never match an upload: GitHub's archive
	// unpacks to `signal-and-noise-<version>/` and that version-suffixed
	// directory used to land in wp-content/themes/ under the wrong slug,
	// deactivating the theme. This was covered until now by an untracked
	// must-use plugin on the server (`mu-plugins/sn-theme-updater.php`),
	// retired in this release; only the safe half of it moved here.
	//
	// Scoped by the package's own Text Domain rather than by anything the
	// caller supplies, so a foreign theme passes through untouched.
	if ( ! $ours && '' === $theme && sn_gh_theme_source_is_ours( $source ) ) {
		$ours = true;
	}

	if ( ! $ours ) {
		return $source;
	}

	$source         = trailingslashit( $source );
	$desired_source = trailingslashit( dirname( $source ) ) . SN_GH_THEME_STYLESHEET . '/';

	if ( $source === $desired_source ) {
		return $source;
	}

	global $wp_filesystem;
	if ( ! $wp_filesystem || ! $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $desired_source ) ) ) {
		return new WP_Error(
			'sn_rename_source_failed',
			'Could not rename the unpacked theme directory from "' . esc_html( basename( $source ) ) . '" to "' . SN_GH_THEME_STYLESHEET . '". Manual install via SFTP may be required.'
		);
	}

	return $desired_source;
}, 10, 4 );
