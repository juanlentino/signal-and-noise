<?php
/**
 * Signal & Noise — declares the "article" template part area.
 *
 * `parts/post-frontmatter.html` and `parts/post-closing.html` frame a note's
 * body — front matter before it, the closing furniture after — but
 * theme.json never declared an area for them, so the site editor filed both
 * under "General" (WP_TEMPLATE_PART_AREA_UNCATEGORIZED). theme.json now
 * assigns them `"area": "article"`; this module teaches core that the area
 * itself exists via the `default_wp_template_part_areas` filter, mirroring
 * the exact shape core's own areas use (area/label/description/icon/
 * area_tag — see get_allowed_block_template_part_areas() in
 * wp-includes/block-template-utils.php).
 *
 * `area_tag` is the wrapper tag used when a part in this area carries no
 * `tagName` attribute. post-frontmatter has none, so 'div' is correct;
 * post-closing opts into `tagName: footer` explicitly in templates/single.html
 * and is unaffected by this default.
 *
 * @package SignalNoise
 * @since 13.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * default_wp_template_part_areas — add the "article" area.
 *
 * @param array[] $areas Existing allowed template part area definitions.
 * @return array[]
 */
function sn_template_part_areas( $areas ) {
	$areas[] = array(
		'area'        => 'article',
		'label'       => __( 'Article', 'signal-noise' ),
		'description' => __( "Before and after a note's body: the front matter and the closing.", 'signal-noise' ),
		'icon'        => 'layout',
		'area_tag'    => 'div',
	);

	return $areas;
}
add_filter( 'default_wp_template_part_areas', 'sn_template_part_areas' );
