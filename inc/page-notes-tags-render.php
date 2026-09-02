<?php
/**
 * Signal & Noise — /notes/tags/ full PHP renderer.
 *
 * A GLOSSARY, not a tag cloud. Every tag carries an owner-written description,
 * so each row can say what the tag is; a cloud would spend that space encoding
 * frequency instead, which is the one property a reader cannot act on. Uniform
 * type size on every row is the whole point — varying it by count is the cloud.
 *
 * Composition is the site's split-hero at row scale: the term left in the
 * uppercase label register, its prose right. Same reason the hero works.
 *
 * Scaffold NOTE: the header/footer template parts are rendered into buffers
 * BEFORE wp_head(), exactly as inc/page-notes-render.php does. That ordering is
 * load-bearing, not stylistic — do_blocks() is what registers the block-layout
 * styles, and wp_head() is where they are printed. Reversing them drops the
 * header's layout CSS with no error.
 *
 * @package Signal_And_Noise
 * @since   12.15.0
 */

defined( 'ABSPATH' ) || exit;

$sn_groups = function_exists( 'sn_notes_tag_groups_resolved' ) ? sn_notes_tag_groups_resolved() : array();

ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own header part); escaping would corrupt the markup.
echo do_blocks( '<!-- wp:template-part {"slug":"header","area":"header"} /-->' );
$sn_header_html = ob_get_clean();

ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted do_blocks() output (the theme's own footer part).
echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
$sn_footer_html = ob_get_clean();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'sn-notes-body' ); ?>>
<?php wp_body_open(); ?>
<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- captured do_blocks() output.
echo $sn_header_html;
?>

<main class="sn-notes-page" id="wp--skip-link--target">

	<header class="sn-notes-hero">
		<p class="sn-notes-eyebrow">Index &middot; Tags</p>
		<div class="sn-notes-hero-title">
			<h1 class="sn-notes-headline">Tags.</h1>
			<p class="sn-notes-dek">Every tag on the notes, grouped by the question it belongs to. Each one says what it covers, so you can tell before you click.</p>
			<p class="sn-notes-start-here">
				<a href="<?php echo esc_url( home_url( '/notes/' ) ); ?>">All notes<span class="sn-notes-start-here-arrow" aria-hidden="true">&rarr;</span></a>
			</p>
		</div>
		<div class="sn-notes-hero-side">
			<?php
			// Mirrors the index hero's closing stamp, in the same register. Not
			// decoration: .sn-notes-hero is a two-column grid above 900px, so a
			// hero with one child leaves the right half empty and squeezes the
			// dek into 1.15fr of the width. Counts are stated ABOUT the
			// vocabulary here, which is a different claim from putting a count
			// on each row — that one would rank the tags by size, which is the
			// tag-cloud failure this page exists to avoid.
			$sn_tag_total   = 0;
			$sn_group_total = count( $sn_groups );
			foreach ( $sn_groups as $sn_g ) {
				$sn_tag_total += count( $sn_g['terms'] );
			}
			?>
			<p class="sn-notes-meta">
				<span><?php echo esc_html( sprintf( _n( '%d tag', '%d tags', $sn_tag_total, 'signal-noise' ), $sn_tag_total ) ); ?></span>
				<span class="sn-notes-meta-bullet" aria-hidden="true">&middot;</span>
				<span><?php echo esc_html( sprintf( _n( '%d group', '%d groups', $sn_group_total, 'signal-noise' ), $sn_group_total ) ); ?></span>
			</p>
		</div>
	</header>

	<?php foreach ( $sn_groups as $sn_group ) : ?>
	<section class="sn-tags-group">
		<div class="sn-tags-group-head">
			<h2 class="sn-tags-group-title"><?php echo wp_kses( $sn_group['title'], array() ); ?></h2>
			<p class="sn-tags-group-dek"><?php echo esc_html( $sn_group['dek'] ); ?></p>
		</div>
		<ul class="sn-tags-list">
			<?php foreach ( $sn_group['terms'] as $sn_term ) : ?>
			<?php $sn_link = get_term_link( $sn_term ); ?>
			<?php if ( ! is_wp_error( $sn_link ) ) : ?>
			<li class="sn-tags-row">
				<a class="sn-tags-link" href="<?php echo esc_url( $sn_link ); ?>">
					<span class="sn-tags-term"><?php echo esc_html( $sn_term->name ); ?></span>
					<span class="sn-tags-desc"><?php echo esc_html( $sn_term->description ); ?></span>
				</a>
			</li>
			<?php endif; ?>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php endforeach; ?>

</main>

<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- captured do_blocks() output.
echo $sn_footer_html;
?>
<?php wp_footer(); ?>
</body>
</html>
