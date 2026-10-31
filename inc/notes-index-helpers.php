<?php
/**
 * Signal & Noise — /notes index PURE helpers.
 *
 * v10.49.0: extracted VERBATIM from inc/page-notes-render.php. That file is
 * a full-page renderer (echoes HTML + runs WP_Query at include time), so for
 * ~10 versions its testable helpers hid behind a documented mid-file
 * SN_NOTES_RENDER_TEST early-return hack whose placement was load-bearing
 * (helpers above the return, render body below — it already forced one
 * emergency extraction, v10.42.2's notes-reading-time). The helpers now live
 * here, always-loadable: functions.php requires this module BEFORE the
 * template router, the renderer just calls them, and the sentinel is retired.
 *
 * Consumed by inc/page-notes-render.php (the template_include short-circuit
 * render path, WORDPRESS-REFERENCE §10.4) and by the standalone fixtures
 * (tests/notes-pagination.php, tests/notes-search.php,
 * tests/notes-topic-reframe.php, tests/notes-index-helpers.php).
 *
 * @package SignalNoise
 * @since 10.49.0 (function bodies from 9.6.0 onward — see each docblock)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The parts, in the original top-to-bottom order.
require_once __DIR__ . '/notes-index-helpers-display.php';
require_once __DIR__ . '/notes-index-helpers-query.php';
require_once __DIR__ . '/notes-index-helpers-paging.php';
require_once __DIR__ . '/notes-index-helpers-hero.php';
require_once __DIR__ . '/notes-index-helpers-search.php';
require_once __DIR__ . '/notes-index-helpers-manifest.php';
require_once __DIR__ . '/notes-index-helpers-catalog.php';
