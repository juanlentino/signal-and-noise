<?php
/**
 * Dynamic render for signal-noise/note-cite (inc/note-cite.php has the
 * contract; tests/note-cite.php pins it).
 *
 * @package SignalNoise
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sn_note_cite_html() escapes every value it prints (esc_html / esc_attr / esc_url).
echo sn_php_block_output( sn_note_cite_html(), __( 'Cite this note', 'signal-and-noise' ) );
