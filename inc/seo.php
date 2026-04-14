<?php
/**
 * Signal & Noise — SEO + analytics delivery.
 *
 * - Meta description on front page and singular posts
 * - Google Tag (gtag.js) delayed until first user interaction
 * - Plausible CE tracking script on the self-hosted Railway instance
 * - Breeze excludes so our perf-critical bundles aren't re-ordered by
 *   the Breeze cache plugin
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO: Output meta description tag.
 */
add_action( 'wp_head', function() {
	$description = '';

	if ( is_front_page() ) {
		$description = 'Music producer, mix engineer, and creative strategist based in Buenos Aires. Founder of Panacea recording studio.';
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		if ( ! empty( $post->post_excerpt ) ) {
			$description = wp_strip_all_tags( $post->post_excerpt );
		}
	}

	if ( $description ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
}, 2 );

/**
 * Analytics: Delay Google Tag (gtag.js) until first user interaction.
 * Eliminates 147 KiB from initial page load. Analytics still fires for
 * any user who scrolls, clicks, or touches — only bots and instant
 * bounces are missed, which aren't useful data anyway.
 */
add_action( 'wp_head', function() {
	?>
	<script>
	(function(){var d=!1;function g(){if(!d){d=!0;var s=document.createElement('script');s.src='https://www.googletagmanager.com/gtag/js?id=GT-NMC3GVL';s.async=!0;document.head.appendChild(s);s.onload=function(){window.dataLayer=window.dataLayer||[];function t(){dataLayer.push(arguments)}t('js',new Date());t('config','GT-NMC3GVL')}}}['scroll','click','touchstart','keydown'].forEach(function(e){document.addEventListener(e,g,{once:!0,passive:!0})});setTimeout(g,5000)})();
	</script>
	<?php
}, 10 );

/**
 * Analytics: Plausible CE tracking script.
 * Self-hosted instance on Railway. Lightweight (~1 KiB), no cookies, GDPR-compliant.
 */
add_action( 'wp_head', function() {
	if ( is_admin() || is_preview() ) return;
	?>
	<script defer data-domain="juanlentino.com" src="https://plausible-analytics-ce-production-fcb9.up.railway.app/js/script.js"></script>
	<?php
}, 11 );

/**
 * Prevent Breeze from deferring the block navigation script.
 */
add_filter( 'breeze_exclude_js', function( $excluded ) {
	$excluded[] = 'wp-block-navigation-view';
	$excluded[] = 'wp-block-navigation';
	$excluded[] = 'signal-noise-sticky-header';
	return $excluded;
} );

/**
 * Prevent Breeze from minifying critical inline CSS.
 */
add_filter( 'breeze_exclude_css', function( $excluded ) {
	$excluded[] = 'critical.css';
	return $excluded;
} );
