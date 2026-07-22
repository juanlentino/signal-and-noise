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
$sn_wrapper = get_block_wrapper_attributes( array( 'class' => 'sn-notes-pillars-section' ) );
// Per-instance heading id: the block is owner-placeable any number of times,
// and a duplicated id would break aria-labelledby on the second instance.
$sn_heading_id = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'sn-pillars-heading-' ) : 'sn-pillars-heading';
?>
<section <?php echo $sn_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped attribute markup from get_block_wrapper_attributes(). ?> aria-labelledby="<?php echo esc_attr( $sn_heading_id ); ?>">
	<div class="sn-notes-section-wrap">
		<p class="sn-notes-section-label" id="<?php echo esc_attr( $sn_heading_id ); ?>">Pillar Essays</p>
		<span class="sn-notes-section-count"><?php echo esc_html( sprintf( _n( '%d essay', '%d essays', count( $sn_pillars ), 'signal-noise' ), count( $sn_pillars ) ) ); ?></span>
	</div>

	<div class="sn-notes-pillars">
		<?php foreach ( $sn_pillars as $sn_pillar_i => $sn_pillar ) : ?>
		<?php
		$sn_slug        = (string) ( $sn_pillar['slug'] ?? '' );
		$sn_designation = trim( (string) ( $sn_pillar['designation'] ?? '' ) );
		$sn_number      = '' !== $sn_designation ? $sn_designation : sprintf( '%02d', $sn_pillar_i + 1 );
		?>
		<article class="sn-notes-pillar">
			<span class="sn-notes-pillar-number" aria-hidden="true">&#8470; <?php echo esc_html( $sn_number ); ?></span>
			<div class="sn-notes-pillar-body">
				<p class="sn-notes-pillar-eyebrow">Pillar Essay<?php if ( function_exists( 'sn_notes_reading_time_for_slug' ) ) : ?> &middot; <?php echo esc_html( sn_notes_reading_time_for_slug( $sn_slug ) ); ?><?php endif; ?></p>
				<h2 class="sn-notes-pillar-title"><?php echo esc_html( (string) ( $sn_pillar['title'] ?? '' ) ); ?></h2>
				<?php if ( '' !== (string) ( $sn_pillar['dek'] ?? '' ) ) : ?>
				<p class="sn-notes-pillar-dek"><?php echo esc_html( (string) $sn_pillar['dek'] ); ?></p>
				<?php endif; ?>
				<a class="sn-notes-pillar-cta" href="<?php echo esc_url( '/' . $sn_slug . '/' ); ?>">Read essay</a>
			</div>
		</article>
		<?php endforeach; ?>
	</div>
</section>
