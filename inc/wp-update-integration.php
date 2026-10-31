<?php
/**
 * Signal & Noise — WP-native update integration.
 *
 * Hooks into WordPress's standard update system so this theme appears
 * in wp-admin/update-core.php and Appearance → Themes alongside other
 * themes. Polls the GitHub Tags API every 12h (cached in a site
 * transient) to compare local version against the latest tagged release,
 * and offers it through core's Update URI hook (`update_themes_github.com`).
 *
 * Both install paths now coexist (since v8.5.2):
 *   - **Canonical:** `gh workflow run deploy.yml --ref vX.Y.Z --repo juanlentino/signal-and-noise`
 *     → Cloudways /api/v1/git/pull → fast, well-understood, preserves .git
 *   - **WP UI:** wp-admin → Updates → Update Now
 *     → WP downloads GitHub tag ZIP → upgrader_source_selection rename
 *     (below) drops the version suffix → install + .git preserved by
 *     inc/wp-update-git-preservation.php pre/post-install filters
 *
 * The .git preservation work was the missing piece. Without it, clicking
 * "Update Now" destroyed the .git directory (via WP_Upgrader's recursive
 * clear_destination) and broke the next gh workflow_dispatch deploy. The
 * paths shared a destination dir but no coordination protocol. v8.5.0
 * added a WP_Error gate to block WP UI installs entirely; v8.5.1 removed
 * the gate without solving the underlying problem; v8.5.2 added the
 * pre/post-install filter pair that backs up + restores .git atomically.
 *
 * What this file provides:
 *   - Version visibility in wp-admin (badge + "Up to date" / "Update
 *     Available" on update-core.php and Appearance → Themes).
 *   - Health signal — local Version != GitHub latest means no update
 *     has landed yet via either path.
 *   - GitHub tag → install via upgrader_source_selection rename so WP
 *     installs to the correct stylesheet slug.
 *
 * Added in v8.5.0 (2026-05-16). Reworked in v8.5.1 (2026-05-16) to
 * remove the WP_Error gate. v8.5.2 (2026-05-16) extracted .git
 * preservation into inc/wp-update-git-preservation.php — the missing
 * piece that makes the WP UI install path actually safe.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The parts, in the original top-to-bottom order.
require_once __DIR__ . '/wp-update-integration-constants.php';
require_once __DIR__ . '/wp-update-integration-failures.php';
require_once __DIR__ . '/wp-update-integration-failure-report.php';
require_once __DIR__ . '/wp-update-integration-tag-fetch.php';
require_once __DIR__ . '/wp-update-integration-download.php';
require_once __DIR__ . '/wp-update-integration-update-transient.php';
require_once __DIR__ . '/wp-update-integration-source-selection.php';
require_once __DIR__ . '/wp-update-integration-watchdog.php';
