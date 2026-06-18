<?php
/**
 * Signal & Noise — live colophon build meta (C2).
 *
 * Surfaces the real build provenance on the /colophon page: theme version,
 * companion-plugin version, git short SHA, and deploy time — exposed through a
 * [sn_build] shortcode resolved in the colophon pattern via the render_block
 * bridge in inc/setup.php.
 *
 * NO SHELL-OUT. Process-spawn functions are absent/disabled on Cloudways, so
 * the git short SHA is read straight off the filesystem: .git/HEAD plus the
 * referenced loose ref / packed-refs / detached SHA. inc/wp-update-git-
 * preservation.php proves .git survives WP-UI installs and sits as a real
 * directory at the theme root on-server, so these reads are reliable in
 * production.
 *
 * DEGRADES, NEVER FATALS. Every segment is independently optional: no .git →
 * drop the SHA + deploy time; plugin inactive (SNT_VERSION undefined) → drop
 * the plugin segment. The theme version (always available via wp_get_theme())
 * is the irreducible minimum, so the line is never empty in practice.
 *
 * NOT CACHED. The build line renders on the /colophon page only — a rarely-hit
 * surface — and the reads are a handful of small file_get_contents(), cheaper
 * than a transient round-trip. Skipping the cache also keeps the SHA + deploy
 * time always fresh (no post-deploy staleness window).
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve a full ref ("refs/heads/<branch>") → short SHA + ref-file mtime, from
 * a directory holding loose refs + packed-refs. Pure; no shell-out.
 *
 * @param string $refs_dir Directory holding refs/ and/or packed-refs.
 * @param string $ref      Full ref path, e.g. "refs/heads/main".
 * @return array{sha:string,mtime:int}|array{} Empty if unresolved.
 */
function sn_colophon_resolve_ref( $refs_dir, $ref ) {
	// Harden against a tampered .git/HEAD: $ref is used as a path segment below
	// ("$refs_dir/$ref"), so reject anything that isn't a plain refs/* path with
	// no ".." traversal. A real ref is "refs/heads/main", "refs/tags/v1.0", etc.
	// Fail closed (no SHA) on anything else — the char-class allows "." for
	// dotted branch/tag names but the strpos check still bars traversal.
	if ( ! preg_match( '#^refs/[A-Za-z0-9._/-]+$#', (string) $ref ) || false !== strpos( (string) $ref, '..' ) ) {
		return array();
	}

	// 1) Loose ref file.
	$loose = $refs_dir . '/' . $ref;
	if ( is_file( $loose ) ) {
		$sha = trim( (string) file_get_contents( $loose ) );
		if ( preg_match( '/^[0-9a-f]{40}/i', $sha ) ) {
			return array( 'sha' => substr( $sha, 0, 7 ), 'mtime' => (int) @filemtime( $loose ) );
		}
	}

	// 2) packed-refs fallback (the loose file may be absent after gc).
	$packed = $refs_dir . '/packed-refs';
	if ( is_file( $packed ) ) {
		$lines = preg_split( '/\R/', (string) file_get_contents( $packed ) );
		foreach ( (array) $lines as $line ) {
			if ( '' === $line || '#' === $line[0] || '^' === $line[0] ) {
				continue;
			}
			$parts = explode( ' ', $line, 2 );
			if ( count( $parts ) === 2
				&& trim( $parts[1] ) === $ref
				&& preg_match( '/^[0-9a-f]{40}/i', $parts[0] )
			) {
				return array( 'sha' => substr( $parts[0], 0, 7 ), 'mtime' => (int) @filemtime( $packed ) );
			}
		}
	}

	return array();
}

/**
 * Parse a HEAD file → either a detached-HEAD result or the symbolic ref it names.
 *
 * @param string $head_file Path to a HEAD file.
 * @return array {sha,branch,mtime} (detached) | {ref,branch} (symbolic) | array().
 */
function sn_colophon_parse_head( $head_file ) {
	if ( ! is_file( $head_file ) ) {
		return array();
	}
	$head = trim( (string) file_get_contents( $head_file ) );
	if ( '' === $head ) {
		return array();
	}

	// Detached HEAD: a bare 40-hex SHA (e.g. a `--ref vX.Y.Z` tag-checkout deploy).
	if ( preg_match( '/^[0-9a-f]{40}$/i', $head ) ) {
		return array( 'sha' => substr( $head, 0, 7 ), 'branch' => '', 'mtime' => (int) @filemtime( $head_file ) );
	}

	// Symbolic HEAD: "ref: refs/heads/<branch>".
	if ( strpos( $head, 'ref: ' ) !== 0 ) {
		return array();
	}
	$ref = trim( substr( $head, 5 ) );
	return array( 'ref' => $ref, 'branch' => (string) preg_replace( '#^refs/heads/#', '', $ref ) );
}

/**
 * Read short SHA + branch + ref-file mtime from a real .git DIRECTORY (the
 * production case). Pure; no shell-out.
 *
 * @param string $git_dir Absolute path to a .git directory.
 * @return array{sha:string,branch:string,mtime:int}|array{} Empty on failure.
 */
function sn_colophon_read_git_dir( $git_dir ) {
	$head = sn_colophon_parse_head( $git_dir . '/HEAD' );
	if ( empty( $head ) ) {
		return array();
	}
	if ( isset( $head['sha'] ) ) {
		return $head; // detached
	}
	$resolved = sn_colophon_resolve_ref( $git_dir, $head['ref'] );
	if ( ! empty( $resolved['sha'] ) ) {
		return array( 'sha' => $resolved['sha'], 'branch' => $head['branch'], 'mtime' => $resolved['mtime'] );
	}
	return array();
}

/**
 * Resolve a linked worktree's shared common dir (where refs/heads/* live).
 *
 * @param string $worktree_gitdir The per-worktree gitdir.
 * @return string Absolute common-dir path, or '' if not resolvable.
 */
function sn_colophon_worktree_commondir( $worktree_gitdir ) {
	$commondir_file = $worktree_gitdir . '/commondir';
	if ( ! is_file( $commondir_file ) ) {
		return '';
	}
	$common = trim( (string) file_get_contents( $commondir_file ) );
	if ( '' === $common ) {
		return '';
	}
	$path = ( isset( $common[0] ) && '/' === $common[0] ) ? $common : $worktree_gitdir . '/' . $common;
	$real = realpath( $path );
	return $real ? $real : '';
}

/**
 * Locate the theme's .git and read its build meta. Handles a real .git dir
 * (production — see inc/wp-update-git-preservation.php) and a worktree .git
 * FILE ("gitdir: …", local dev). In a linked worktree HEAD names the
 * checked-out branch but refs/heads/* live in the shared common dir, so the
 * worktree's OWN ref is resolved there — never the common dir's HEAD. Never
 * fatals.
 *
 * @return array{sha:string,branch:string,mtime:int}|array{}
 */
function sn_colophon_git_meta() {
	$git = get_template_directory() . '/.git';

	if ( is_dir( $git ) ) {
		return sn_colophon_read_git_dir( $git );
	}

	if ( ! is_file( $git ) ) {
		return array();
	}

	$contents = trim( (string) file_get_contents( $git ) );
	if ( strpos( $contents, 'gitdir: ' ) !== 0 ) {
		return array();
	}
	$worktree_gitdir = trim( substr( $contents, 8 ) );

	$head = sn_colophon_parse_head( $worktree_gitdir . '/HEAD' );
	if ( empty( $head ) ) {
		return array();
	}
	if ( isset( $head['sha'] ) ) {
		return $head; // detached worktree HEAD
	}

	// Resolve the worktree's own ref — first in the per-worktree gitdir, then in
	// the shared common dir where refs/heads/* actually live for linked worktrees.
	$resolved = sn_colophon_resolve_ref( $worktree_gitdir, $head['ref'] );
	if ( empty( $resolved['sha'] ) ) {
		$common = sn_colophon_worktree_commondir( $worktree_gitdir );
		if ( '' !== $common ) {
			$resolved = sn_colophon_resolve_ref( $common, $head['ref'] );
		}
	}
	if ( ! empty( $resolved['sha'] ) ) {
		return array( 'sha' => $resolved['sha'], 'branch' => $head['branch'], 'mtime' => $resolved['mtime'] );
	}
	return array();
}

/**
 * Assemble the dry, factual build line, e.g.
 * "Theme v10.5.0 · plugin v6.12.0 · a1b2c3d · 2026-06-14".
 *
 * @return string Possibly empty (only if the theme version is somehow blank).
 */
function sn_colophon_build_line() {
	$segments = array();

	// Theme version — single source of truth is style.css (never a constant).
	$theme_version = (string) wp_get_theme()->get( 'Version' );
	if ( '' !== $theme_version ) {
		$segments[] = 'Theme v' . $theme_version;
	}

	// Companion-plugin version via its own constant — absent when inactive.
	if ( defined( 'SNT_VERSION' ) && SNT_VERSION ) {
		$segments[] = 'plugin v' . SNT_VERSION;
	}

	// Git short SHA + deploy time (ref-file mtime), no shell-out.
	$git = sn_colophon_git_meta();
	if ( ! empty( $git['sha'] ) ) {
		$segments[] = $git['sha'];
	}
	if ( ! empty( $git['mtime'] ) ) {
		$segments[] = wp_date( 'Y-m-d', $git['mtime'] );
	}

	return implode( ' · ', $segments );
}

/**
 * [sn_build] shortcode. Escaped at the output sink — do_shortcode() output is
 * not auto-escaped.
 *
 * @return string
 */
function sn_colophon_build_shortcode() {
	$line = sn_colophon_build_line();
	return '' === $line ? '' : esc_html( $line );
}

if ( ! defined( 'SN_COLOPHON_META_TEST' ) || ! SN_COLOPHON_META_TEST ) {
	add_shortcode( 'sn_build', 'sn_colophon_build_shortcode' );
}
