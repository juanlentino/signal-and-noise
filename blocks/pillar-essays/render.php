<?php
/**
 * Dynamic render for signal-noise/pillar-essays.
 *
 * The pillar rail left the /notes index in v10.47.0 and became this
 * owner-placeable block (dropped into the /provenance/ hub Page, a DB CMS
 * page). Descriptors come from sn_theme_pillar_descriptors(): Pages the
 * plugin flags with '_sn_pillar' meta, hub-children fallback until then.
 *
 * The card number renders the owner's editorial designation when set
 * ("No. 1.01" via the &#8470; sign), positional %02d only as fallback. The
 * header count is a plain "N essays": the old "03 / 03" positional counter
 * retired because designations make it a false positional claim. Honest
 * empty: no descriptors, no output. Every sink escaped.
 *
 * TWO DENSITIES (v13.x). Default is the full card — dek, "Read essay" CTA,
 * bordered box — measured at 912px tall on /provenance at 1440x900, where
 * the essays ARE the page and that weight is correct. `compact` renders the
 * same three descriptors as single rows (designation, title, reading time,
 * whole row clickable), for surfaces where the rail is supporting cast and a
 * full screen of cards would bury what the reader came for. Same data, same
 * source, same escaping; only dek and CTA are dropped.
 *
 * The compact title is a <span>, not the <h2> the full card uses. Three h2s
 * for three sidebar links on a page that already has its own heading
 * outline is heading noise, and the section keeps its accessible name from
 * aria-labelledby on the label above either way.
 *
 * @package SignalNoise
 * @since 10.47.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'sn_theme_pillar_descriptors' ) ) {
	return;
}
$sn_pillars = sn_theme_pillar_descriptors();
if ( ! is_array( $sn_pillars ) || array() === $sn_pillars ) {
	return;
}
// Attribute, not a filter: the density is an editorial property of THIS
// placement. The same block is on /provenance at full weight in the same
// request-shape, so a global switch would be wrong by construction.
$sn_compact = ! empty( $attributes['compact'] );

// ONE classifier for both the header count and the per-row class. Two
// independent readings of "is this a sub-pillar?" is how a header ends up
// disagreeing with the rows underneath it.
$sn_is_sub_fn = static function ( $designation ) {
	if ( ! function_exists( 'sn_theme_pillar_designation_parts' ) ) {
		return false;
	}
	$parts = sn_theme_pillar_designation_parts( $designation );
	return is_array( $parts ) && $parts[1] > 0;
};

// v12.20.1: count the two kinds separately. "3 essays" was true but it undid
// the distinction the rows had just drawn — a reader who can see that 1.01
// sits under 1.00 is told the rail holds three peers. An undesignated essay
// counts as a pillar, matching how it renders.
$sn_n_pillars = 0;
$sn_n_subs    = 0;
foreach ( $sn_pillars as $sn_p ) {
	if ( $sn_is_sub_fn( trim( (string) ( $sn_p['designation'] ?? '' ) ) ) ) {
		++$sn_n_subs;
	} else {
		++$sn_n_pillars;
	}
}
$sn_count_text = sprintf( _n( '%d pillar', '%d pillars', $sn_n_pillars, 'signal-noise' ), $sn_n_pillars );
if ( $sn_n_subs > 0 ) {
	// Only when they exist. A trailing "0 sub-pillars" would advertise an
	// absence, and the owner's framing is that there may never be another.
	$sn_count_text .= ' · ' . sprintf( _n( '%d sub-pillar', '%d sub-pillars', $sn_n_subs, 'signal-noise' ), $sn_n_subs );
}
$sn_wrapper = get_block_wrapper_attributes( array(
	'class' => 'sn-notes-pillars-section' . ( $sn_compact ? ' is-compact' : '' ),
) );
// Per-instance heading id: the block is owner-placeable any number of times,
// and a duplicated id would break aria-labelledby on the second instance.
$sn_heading_id = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'sn-pillars-heading-' ) : 'sn-pillars-heading';
?>
<section <?php echo $sn_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped attribute markup from get_block_wrapper_attributes(). ?> aria-labelledby="<?php echo esc_attr( $sn_heading_id ); ?>">
	<div class="sn-notes-section-wrap">
		<p class="sn-notes-section-label" id="<?php echo esc_attr( $sn_heading_id ); ?>">Pillar Essays</p>
		<span class="sn-notes-section-count"><?php echo esc_html( $sn_count_text ); ?></span>
	</div>

	<div class="sn-notes-pillars">
		<?php foreach ( $sn_pillars as $sn_pillar_i => $sn_pillar ) : ?>
		<?php
		$sn_slug        = (string) ( $sn_pillar['slug'] ?? '' );
		$sn_designation = trim( (string) ( $sn_pillar['designation'] ?? '' ) );
		$sn_number      = '' !== $sn_designation ? $sn_designation : sprintf( '%02d', $sn_pillar_i + 1 );
		// SUBORDINATION (v12.20.0). The designation already encodes the shape of
		// the programme: major = the pillar, minor = an essay underneath it.
		// 1.00 is pillar one itself; 1.01 sits under it. Until now the rail
		// rendered all of them as peers, which is the one thing the numbering
		// says they are not.
		//
		// Derived, never a count or a position: a rail holding only 1.00 and
		// 2.01 must still subordinate the second, and an essay with no
		// designation at all is top-level rather than a straggler. That is what
		// "ready for anything" has to mean here — the treatment follows from the
		// number the owner typed, so zero sub-pillars, one, or nine all render
		// correctly without anyone revisiting this file.
		$sn_is_sub      = $sn_is_sub_fn( $sn_designation );
		$sn_row_class   = 'sn-notes-pillar' . ( $sn_is_sub ? ' sn-notes-pillar--sub' : '' );
		// v11.4.6: home_url(), not a bare '/<slug>/'. Matches what the command
		// palette already emits for the same pillar, and a root-relative href
		// resolves outside the install on a subdirectory setup. CMA audit
		// 2026-08-05 INFO-2.
		$sn_href        = home_url( '/' . $sn_slug . '/' );
		$sn_time        = function_exists( 'sn_notes_reading_time_for_slug' ) ? sn_notes_reading_time_for_slug( $sn_slug ) : '';
		?>

		<?php if ( $sn_compact ) : ?>
		<a class="<?php echo esc_attr( $sn_row_class ); ?>" href="<?php echo esc_url( $sn_href ); ?>">
			<span class="sn-notes-pillar-number" aria-hidden="true">&#8470; <?php echo esc_html( $sn_number ); ?></span>
			<span class="sn-notes-pillar-body">
				<span class="sn-notes-pillar-title"><?php echo esc_html( (string) ( $sn_pillar['title'] ?? '' ) ); ?></span>
				<?php if ( '' !== $sn_time ) : ?>
				<span class="sn-notes-pillar-eyebrow"><?php echo esc_html( $sn_time ); ?></span>
				<?php endif; ?>
			</span>
		</a>
		<?php else : ?>
		<article class="<?php echo esc_attr( $sn_row_class ); ?>">
			<span class="sn-notes-pillar-number" aria-hidden="true">&#8470; <?php echo esc_html( $sn_number ); ?></span>
			<div class="sn-notes-pillar-body">
				<p class="sn-notes-pillar-eyebrow">Pillar Essay<?php if ( '' !== $sn_time ) : ?> &middot; <?php echo esc_html( $sn_time ); ?><?php endif; ?></p>
				<h2 class="sn-notes-pillar-title"><?php echo esc_html( (string) ( $sn_pillar['title'] ?? '' ) ); ?></h2>
				<?php if ( '' !== (string) ( $sn_pillar['dek'] ?? '' ) ) : ?>
				<p class="sn-notes-pillar-dek"><?php echo esc_html( (string) $sn_pillar['dek'] ); ?></p>
				<?php endif; ?>
				<a class="sn-notes-pillar-cta" href="<?php echo esc_url( $sn_href ); ?>">Read essay</a>
			</div>
		</article>
		<?php endif; ?>

		<?php endforeach; ?>
	</div>
</section>
