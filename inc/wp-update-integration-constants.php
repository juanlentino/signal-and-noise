<?php
/**
 * WP update integration: the shared constants.
 *
 * Moved verbatim out of inc/wp-update-integration.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_GH_THEME_OWNER         = 'juanlentino';
const SN_GH_THEME_REPO          = 'signal-and-noise';
const SN_GH_THEME_CACHE_KEY     = 'sn_gh_latest_theme';
const SN_GH_THEME_CACHE_TTL     = HOUR_IN_SECONDS; // v8.5.3: 12h → 1h (mirrors plugin v1.11.1)
const SN_GH_THEME_STYLESHEET    = 'signal-and-noise';
/**
 * The theme's Text Domain, which is NOT the stylesheet slug (`signal-noise` vs
 * `signal-and-noise`). Used to identify our own package on the upload path —
 * see sn_gh_theme_source_is_ours().
 *
 * @since 12.7.0
 */
const SN_GH_THEME_TEXT_DOMAIN   = 'signal-and-noise'; // 13.2.3: the text domain is the slug (Theme Handbook).
const SN_GH_THEME_LAST_SEEN_OPT = 'sn_last_seen_theme_version';
/**
 * Why the last tag fetch failed, in prose, for the Dashboard card. Lives beside
 * the negative cache and shares its lifetime.
 *
 * @since theme v10.43.0
 */
const SN_GH_THEME_ERROR_KEY     = 'sn_gh_latest_theme_error';
/**
 * Failure-cache TTLs, split by whether re-asking could get a different answer.
 * Transient mirrors the companion plugin's SN_GH_FAIL_TTL_TRANSIENT and
 * github-actions-api.php's SNT_GH_RUNS_FAIL_TTL — the value that rode out the
 * 2026-07-16 GitHub incident without ever going dark.
 *
 * @since theme v10.43.0
 */
const SN_GH_THEME_FAIL_TTL_TRANSIENT = 5 * MINUTE_IN_SECONDS;
const SN_GH_THEME_FAIL_TTL_DURABLE   = HOUR_IN_SECONDS;
