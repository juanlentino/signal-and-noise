<?php
/**
 * Dynamic render for signal-noise/note-cite (inc/note-cite.php has the
 * contract; tests/note-cite.php pins it).
 *
 * @package SignalNoise
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// The formats line (inc/note-formats.php) follows the citation, and stands on its own without the plugin.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- both renderers escape every value they print (esc_html / esc_attr / esc_url).
echo sn_php_block_output( sn_note_cite_html() . sn_note_formats_html(), __( 'Cite this note', 'signal-and-noise' ) );
