<?php
/**
 * Signal & Noise — the template furniture as PHP-only blocks (WordPress 7.0).
 *
 * Nine renderers that were [shortcodes] inside block templates become real
 * blocks: listed by name in the site editor's List View and movable — with
 * NO JavaScript build (the canvas shows nothing for a post-dependent block
 * outside a post context). WordPress 7.0's `supports.autoRegister` exposes a
 * server-rendered block to the editor from its PHP registration alone.
 *
 * THE CONTRACT IS PARITY WITH `wpautop( shortcode )`, not the bare shortcode
 * string. Each `[sn_x]` used to sit in a `core/shortcode` block, whose own
 * renderer runs `wpautop()` on the expanded output — so the live HTML was
 * always `wpautop( shortcode_output )`: a `<p>` wrapper around inline markup
 * (the chip's two `<a>`s), `<br />` for embedded newlines (the panel), a
 * trailing `\n` after block-level tags. `.sn-post-frontmatter`'s flex row is
 * styled against that `<p>` wrapper, so the eight ex-`wp:shortcode` blocks
 * below each return `wpautop( (string) sn_x_shortcode() )` — every
 * render_callback returns the SAME string the `core/shortcode` block used to
 * produce for the same post, and tests/blocks-php-only.php pins it per
 * block. The toggle is the one exception: it was placed in `wp:html`, never
 * autop'd, so its callback stays a bare string. The shortcodes stay
 * registered through 13.1.x for anything typed into post content; 13.2.0
 * retires them.
 *
 * Context: every renderer reads the queried/global post, exactly as its
 * shortcode did, so parity is by construction. None of these blocks is
 * placed inside a Query Loop; if one ever is, the renderer — not a wrapper —
 * has to learn to take a post id.
 *
 * @package SignalNoise
 * @since 13.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function sn_block_render_prov_chip( $attributes, $content, $block ) {
	return wpautop( (string) sn_prov_chip_shortcode() );
}

function sn_block_render_prov_panel( $attributes, $content, $block ) {
	return wpautop( (string) sn_prov_panel_shortcode() );
}

function sn_block_render_related_notes( $attributes, $content, $block ) {
	return wpautop( (string) sn_related_notes_shortcode() );
}

function sn_block_render_cited_by( $attributes, $content, $block ) {
	return wpautop( (string) sn_cited_by_shortcode() );
}

function sn_block_render_note_share( $attributes, $content, $block ) {
	return wpautop( (string) sn_note_share_shortcode() );
}

function sn_block_render_note_reply( $attributes, $content, $block ) {
	return wpautop( (string) sn_note_reply_shortcode() );
}

function sn_block_render_updated_date( $attributes, $content, $block ) {
	return wpautop( (string) sn_updated_date_shortcode() );
}

function sn_block_render_post_pillar( $attributes, $content, $block ) {
	return wpautop( (string) sn_post_pillar_shortcode() );
}

function sn_block_render_theme_toggle( $attributes, $content, $block ) {
	$placement = ( isset( $attributes['placement'] ) && 'header' === $attributes['placement'] ) ? 'header' : 'footer';
	return (string) sn_dark_mode_toggle_markup( array( 'placement' => $placement ) );
}

/**
 * Register the nine blocks. api_version 3, category signal-noise (the
 * custom blocks' category, inc/blocks-register.php), autoRegister on, html
 * off. multiple => false where a second instance is meaningless; the toggle
 * is placed twice (header + footer).
 */
function sn_register_php_only_blocks() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	$common = array(
		'api_version' => 3,
		'category'    => 'signal-noise',
		'supports'    => array(
			'autoRegister' => true,
			'html'         => false,
			'multiple'     => false,
		),
	);

	$blocks = array(
		'prov-chip'     => array(
			'title'           => __( 'Provenance chip', 'signal-noise' ),
			'icon'            => 'shield',
			'description'     => __( 'The byline verification chip of a signed note.', 'signal-noise' ),
			'render_callback' => 'sn_block_render_prov_chip',
		),
		'prov-panel'    => array(
			'title'           => __( 'Provenance record', 'signal-noise' ),
			'icon'            => 'shield-alt',
			'description'     => __( 'The expandable provenance record of a signed note.', 'signal-noise' ),
			'render_callback' => 'sn_block_render_prov_panel',
		),
		'related-notes' => array(
			'title'           => __( 'Related notes', 'signal-noise' ),
			'icon'            => 'networking',
			'description'     => __( 'Notes the kernel ranks closest to this one.', 'signal-noise' ),
			'render_callback' => 'sn_block_render_related_notes',
		),
		'cited-by'      => array(
			'title'           => __( 'Cited by', 'signal-noise' ),
			'icon'            => 'admin-links',
			'description'     => __( 'Verified webmentions that cite this note.', 'signal-noise' ),
			'render_callback' => 'sn_block_render_cited_by',
		),
		'note-share'    => array(
			'title'           => __( 'Share this note', 'signal-noise' ),
			'icon'            => 'share',
			'description'     => __( 'The share row for a note.', 'signal-noise' ),
			'render_callback' => 'sn_block_render_note_share',
		),
		'note-reply'    => array(
			'title'           => __( 'Reply by email', 'signal-noise' ),
			'icon'            => 'email',
			'description'     => __( 'The reply-by-email line for a note.', 'signal-noise' ),
			'render_callback' => 'sn_block_render_note_reply',
		),
		'updated-date'  => array(
			'title'           => __( 'Updated date', 'signal-noise' ),
			'icon'            => 'clock',
			'description'     => __( 'The last-updated line, shown only when a note was amended.', 'signal-noise' ),
			'render_callback' => 'sn_block_render_updated_date',
		),
		'post-pillar'   => array(
			'title'           => __( 'Pillar link', 'signal-noise' ),
			'icon'            => 'category',
			'description'     => __( 'The link to the pillar essay a note belongs to, when it does.', 'signal-noise' ),
			'render_callback' => 'sn_block_render_post_pillar',
		),
	);

	foreach ( $blocks as $slug => $args ) {
		register_block_type( 'signal-noise/' . $slug, array_merge( $common, $args ) );
	}

	// The toggle: placed in both header and footer, so `multiple` stays open,
	// and its one attribute gets an auto-generated inspector control (7.0).
	register_block_type(
		'signal-noise/theme-toggle',
		array(
			'api_version'     => 3,
			'category'        => 'signal-noise',
			'title'           => __( 'High-contrast toggle', 'signal-noise' ),
			'icon'            => 'visibility',
			'description'     => __( 'The high-contrast palette switch.', 'signal-noise' ),
			'attributes'      => array(
				'placement' => array(
					'type'    => 'string',
					'enum'    => array( 'header', 'footer' ),
					'default' => 'footer',
				),
			),
			'supports'        => array(
				'autoRegister' => true,
				'html'         => false,
			),
			'render_callback' => 'sn_block_render_theme_toggle',
		)
	);
}

if ( ! defined( 'SN_BLOCKS_TEST' ) || ! SN_BLOCKS_TEST ) {
	add_action( 'init', 'sn_register_php_only_blocks', 11 ); // after inc/blocks-register.php's init (priority 10) so the category exists.
}
