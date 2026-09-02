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

<main class="sn-notes-main sn-tags-main">

	<header class="sn-notes-hero">
		<div class="sn-notes-hero-main">
			<p class="sn-notes-eyebrow">Index &middot; Tags</p>
			<h1 class="sn-notes-title">Tags.</h1>
			<p class="sn-notes-dek">Every tag on the notes, grouped by the question it belongs to. Each one says what it covers, so you can tell before you click.</p>
			<p class="sn-notes-start-here">
				<a href="<?php echo esc_url( home_url( '/notes/' ) ); ?>">All notes<span class="sn-notes-start-here-arrow" aria-hidden="true">&rarr;</span></a>
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
