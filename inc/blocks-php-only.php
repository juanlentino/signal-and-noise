<?php
/**
 * Signal & Noise — the template furniture as PHP-only blocks (WordPress 7.0).
 *
 * Nine renderers that were [shortcodes] inside block templates become real
 * blocks: visible by name in the site editor, movable, styleable — with NO
 * JavaScript build. WordPress 7.0's `supports.autoRegister` exposes a
 * server-rendered block to the editor from its PHP registration alone.
 *
 * THE CONTRACT IS PARITY. Every render_callback returns the SAME string the
 * shortcode returns for the same post — tests/blocks-php-only.php pins it
 * per block. The shortcodes stay registered through 13.1.x for anything
 * typed into post content; 13.2.0 retires them.
 *
 * Context: the renderers read the queried post. On `single` that is the
 * block's `postId` context, so parity is exact. A differing context id (a
 * future Query Loop placement) is honoured by bracketing the render in
 * setup_postdata()/wp_reset_postdata() — only when it differs, so single
 * pays nothing.
 *
 * @package SignalNoise
 * @since 13.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Run a renderer for the block's context post: set the global post to the
 * context id only when it differs from the current one, restore after.
 *
 * @param object   $block    WP_Block (or a stand-in exposing ->context).
 * @param callable $renderer The shortcode renderer.
 * @return string
 */
function sn_php_block_render_for_context( $block, callable $renderer ) {
	$ctx_id = ( is_object( $block ) && ! empty( $block->context['postId'] ) ) ? (int) $block->context['postId'] : 0;
	if ( $ctx_id > 0 && $ctx_id !== (int) get_the_ID() ) {
		$post = get_post( $ctx_id );
		if ( $post ) {
			setup_postdata( $post );
			$out = (string) $renderer();
			wp_reset_postdata();
			return $out;
		}
	}
	return (string) $renderer();
}

function sn_block_render_prov_chip( $attributes, $content, $block ) {
	return sn_php_block_render_for_context( $block, 'sn_prov_chip_shortcode' );
}

function sn_block_render_prov_panel( $attributes, $content, $block ) {
	return sn_php_block_render_for_context( $block, 'sn_prov_panel_shortcode' );
}

function sn_block_render_related_notes( $attributes, $content, $block ) {
	return sn_php_block_render_for_context( $block, 'sn_related_notes_shortcode' );
}

function sn_block_render_cited_by( $attributes, $content, $block ) {
	return sn_php_block_render_for_context( $block, 'sn_cited_by_shortcode' );
}

function sn_block_render_note_share( $attributes, $content, $block ) {
	return sn_php_block_render_for_context( $block, 'sn_note_share_shortcode' );
}

function sn_block_render_note_reply( $attributes, $content, $block ) {
	return sn_php_block_render_for_context( $block, 'sn_note_reply_shortcode' );
}

function sn_block_render_updated_date( $attributes, $content, $block ) {
	return sn_php_block_render_for_context( $block, 'sn_updated_date_shortcode' );
}

function sn_block_render_post_pillar( $attributes, $content, $block ) {
	return sn_php_block_render_for_context( $block, 'sn_post_pillar_shortcode' );
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
		'api_version'  => 3,
		'category'     => 'signal-noise',
		'uses_context' => array( 'postId' ),
		'supports'     => array(
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
