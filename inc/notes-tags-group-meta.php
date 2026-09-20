<?php
/**
 * Signal & Noise: the Group field on Posts › Tags.
 *
 * Files a tag under one of /notes/tags/' four headings without a release: the
 * value lives in term meta (`sn_tag_group`, one of sn_notes_tag_group_ids()),
 * the page reads it first (inc/notes-tags-data.php), and the slug lists in
 * the theme stay only as the seed for tags nobody has filed by hand. The
 * field sits beside the description on WordPress's own add and edit forms,
 * saved on the core hooks that fire after core has checked the nonce and the
 * capability. Registered meta, so the REST API can file a tag too.
 *
 * @package Signal_And_Noise
 * @since   13.4.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'sn_tag_group_register_meta' );
add_action( 'post_tag_add_form_fields', 'sn_tag_group_add_field' );
add_action( 'post_tag_edit_form_fields', 'sn_tag_group_edit_field' );
add_action( 'created_post_tag', 'sn_tag_group_save' );
add_action( 'edited_post_tag', 'sn_tag_group_save' );
add_filter( 'manage_edit-post_tag_columns', 'sn_tag_group_column' );
add_filter( 'manage_post_tag_custom_column', 'sn_tag_group_column_value', 10, 3 );

/**
 * Register the meta: a string, single, sanitized to a known id or '', in REST.
 */
function sn_tag_group_register_meta() {
	register_term_meta(
		'post_tag',
		SN_TAG_GROUP_META,
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sn_tag_group_sanitize',
			'auth_callback'     => static function () {
				return current_user_can( 'manage_categories' );
			},
		)
	);
}

/**
 * A known group id, or '' (unfiled). Never anything else.
 *
 * @param mixed $value
 * @return string
 */
function sn_tag_group_sanitize( $value ) {
	$value = is_string( $value ) ? $value : '';
	return in_array( $value, sn_notes_tag_group_ids(), true ) ? $value : '';
}

/**
 * The select: "Not yet filed" plus the four groups, in render order.
 *
 * @param string $selected The effective group id, '' for unfiled.
 * @return string
 */
function sn_tag_group_select( $selected ) {
	$html = '<select name="' . esc_attr( SN_TAG_GROUP_META ) . '" id="sn-tag-group">';
	$html .= '<option value=""' . selected( '', $selected, false ) . '>' . esc_html__( 'Not yet filed', 'signal-and-noise' ) . '</option>';
	foreach ( sn_notes_tag_groups() as $group ) {
		$html .= '<option value="' . esc_attr( $group['id'] ) . '"' . selected( $group['id'], $selected, false ) . '>' . esc_html( html_entity_decode( $group['title'], ENT_QUOTES, 'UTF-8' ) ) . '</option>';
	}
	return $html . '</select>';
}

/** The field on the add form. */
function sn_tag_group_add_field() {
	echo '<div class="form-field"><label for="sn-tag-group">' . esc_html__( 'Group on /notes/tags', 'signal-and-noise' ) . '</label>';
	echo sn_tag_group_select( '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
	echo '<p>' . esc_html__( 'The heading the glossary files this tag under. Unfiled tags show under "Not yet filed".', 'signal-and-noise' ) . '</p></div>';
}

/**
 * The field on the edit form, showing where the tag files today (meta or seed).
 *
 * @param WP_Term $term
 */
function sn_tag_group_edit_field( $term ) {
	echo '<tr class="form-field"><th scope="row"><label for="sn-tag-group">' . esc_html__( 'Group on /notes/tags', 'signal-and-noise' ) . '</label></th><td>';
	echo sn_tag_group_select( sn_notes_tag_group_effective( $term ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
	echo '<p class="description">' . esc_html__( 'The heading the glossary files this tag under. Saving here overrides the theme\'s seed list.', 'signal-and-noise' ) . '</p></td></tr>';
}

/**
 * Save on the core hooks, with the gate the meta declares checked HERE.
 *
 * created_post_tag fires from wp_insert_term() on every path that mints a
 * tag, not only the tag form: a Contributor creating a tag through
 * POST /wp/v2/tags with a form-encoded body, or through tax_input on a post
 * save, runs with their $_POST in scope. So the handler verifies the tag
 * form's own nonce (add-tag on create, update-tag_<id> on edit) and the
 * edit_term capability (manage_categories for post_tag) before writing, and
 * leaves the meta alone on any other path. The REST `meta` field has its own
 * gate (edit_term_meta plus the auth_callback above).
 *
 * @param int $term_id
 */
function sn_tag_group_save( $term_id ) {
	$term_id = (int) $term_id;
	if ( ! isset( $_POST[ SN_TAG_GROUP_META ] ) || ! current_user_can( 'edit_term', $term_id ) ) {
		return;
	}
	$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? ( $_POST['_wpnonce_add-tag'] ?? '' ) ) );
	if ( ! wp_verify_nonce( $nonce, 'update-tag_' . $term_id ) && ! wp_verify_nonce( $nonce, 'add-tag' ) ) {
		return;
	}
	$gid = sn_tag_group_sanitize( sanitize_key( wp_unslash( $_POST[ SN_TAG_GROUP_META ] ) ) );
	if ( '' === $gid ) {
		delete_term_meta( $term_id, SN_TAG_GROUP_META );
		return;
	}
	update_term_meta( $term_id, SN_TAG_GROUP_META, $gid );
}

/**
 * A Group column on the tags list, after Description.
 *
 * @param array $columns
 * @return array
 */
function sn_tag_group_column( $columns ) {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'description' === $key ) {
			$out['sn_tag_group'] = __( 'Group', 'signal-and-noise' );
		}
	}
	if ( ! isset( $out['sn_tag_group'] ) ) {
		$out['sn_tag_group'] = __( 'Group', 'signal-and-noise' );
	}
	return $out;
}

/**
 * The column's value: the group's title, or "Not yet filed".
 *
 * @param string $content
 * @param string $column
 * @param int    $term_id
 * @return string
 */
function sn_tag_group_column_value( $content, $column, $term_id ) {
	if ( 'sn_tag_group' !== $column ) {
		return $content;
	}
	$term = get_term( (int) $term_id, 'post_tag' );
	if ( ! $term || is_wp_error( $term ) ) {
		return $content;
	}
	$gid = sn_notes_tag_group_effective( $term );
	foreach ( sn_notes_tag_groups() as $group ) {
		if ( $group['id'] === $gid ) {
			return esc_html( html_entity_decode( $group['title'], ENT_QUOTES, 'UTF-8' ) );
		}
	}
	return '<em>' . esc_html__( 'Not yet filed', 'signal-and-noise' ) . '</em>';
}
