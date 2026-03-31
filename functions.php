<?php
/**
 * Signal & Noise — Theme Functions
 *
 * @package SignalNoise
 * @since 1.0.0
 * @version 3.9.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue custom front-end assets.
 *
 * custom.css is inlined (below) to eliminate render-blocking external CSS.
 * Only the JS file is enqueued externally (loaded in footer with defer).
 */
function signal_noise_enqueue_styles() {
	wp_enqueue_script(
		'signal-noise-sticky-header',
		get_theme_file_uri( 'assets/js/sticky-header.js' ),
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'signal_noise_enqueue_styles' );

/**
 * Performance: Inline only critical above-the-fold CSS.
 * The full custom.css is loaded deferred below.
 */
add_action( 'wp_head', function() {
	$css_file = get_theme_file_path( 'assets/css/critical.css' );
	if ( file_exists( $css_file ) ) {
		echo '<style id="sn-critical-inline">' . "\n";
		echo file_get_contents( $css_file );  // phpcs:ignore WordPress.WP.AlternativeFunctions
		echo '</style>' . "\n";
	}
}, 50 );

/**
 * Performance: Load full custom.css deferred (non-render-blocking).
 * Critical CSS above covers the first paint; this fills in the rest.
 */
add_action( 'wp_head', function() {
	$css_uri = get_theme_file_uri( 'assets/css/custom.css' ) . '?v=' . wp_get_theme()->get( 'Version' );
	echo '<link rel="stylesheet" href="' . esc_url( $css_uri ) . '" media="print" onload="this.media=\'all\'">' . "\n";
	echo '<noscript><link rel="stylesheet" href="' . esc_url( $css_uri ) . '"></noscript>' . "\n";
}, 51 );

/**
 * Performance: Preload critical font files.
 * Also output favicon link tags as theme-level fallback.
 */
add_action( 'wp_head', function() {
	echo '<link rel="icon" type="image/png" sizes="32x32" href="' . get_theme_file_uri( 'assets/images/favicon-32.png' ) . '">' . "\n";
	echo '<link rel="apple-touch-icon" sizes="180x180" href="' . get_theme_file_uri( 'assets/images/favicon-180.png' ) . '">' . "\n";
	echo '<link rel="preload" href="' . get_theme_file_uri( 'assets/fonts/bebas-neue-latin.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	echo '<link rel="preload" href="' . get_theme_file_uri( 'assets/fonts/dm-mono-300-latin.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
}, 1 );

/**
 * Performance: Inline critical @font-face so the browser can use the preloaded
 * heading font immediately, without waiting for the external stylesheet.
 */
add_action( 'wp_head', function() {
	?>
	<style id="sn-critical-fonts">
	@font-face{font-family:'Bebas Neue';font-style:normal;font-weight:400;font-display:swap;src:url('<?php echo get_theme_file_uri( 'assets/fonts/bebas-neue-latin.woff2' ); ?>') format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
	</style>
	<?php
}, 2 );

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
 * Enqueue editor styles so the Site Editor matches the front end.
 */
function signal_noise_editor_styles() {
	add_editor_style( 'assets/css/custom.css' );
}
add_action( 'after_setup_theme', 'signal_noise_editor_styles' );

/**
 * Performance: Make Contact Form 7 CSS non-render-blocking.
 * Dequeue on non-contact pages; defer on the contact page.
 */
add_action( 'wp_enqueue_scripts', function() {
	if ( ! is_page( 'contact' ) ) {
		wp_dequeue_style( 'contact-form-7' );
		wp_dequeue_script( 'contact-form-7' );
		wp_dequeue_script( 'wpcf7-recaptcha' );
	}
}, 20 );

/**
 * Performance: Remove WP Statistics frontend styles and tracker script.
 */
add_action( 'wp_enqueue_scripts', function() {
	wp_dequeue_style( 'wp-statistics-tracker' );
	wp_dequeue_style( 'wp_statistics_widget_css' );
}, 20 );

add_action( 'wp_enqueue_scripts', function() {
	wp_dequeue_script( 'wp-statistics-tracker' );
}, 99 );

/**
 * Performance: Defer render-blocking WordPress core CSS.
 * Converts wp-block-library from render-blocking to non-blocking using
 * the media='print' onload pattern. Saves ~300ms on mobile.
 */
add_filter( 'style_loader_tag', function( $html, $handle ) {
	$defer_handles = array( 'wp-block-library', 'contact-form-7', 'trp-language-switcher' );
	if ( in_array( $handle, $defer_handles, true ) ) {
		$html = str_replace(
			" media='all'",
			" media='print' onload=\"this.media='all'\"",
			$html
		);
	}
	return $html;
}, 10, 2 );

/**
 * Shortcode: [current_year]
 */
function signal_noise_current_year() {
	return date( 'Y' );
}
add_shortcode( 'current_year', 'signal_noise_current_year' );

/**
 * Process shortcodes inside block template parts.
 */
add_filter( 'render_block', function( $block_content, $block ) {
	if ( strpos( $block_content, '[current_year]' ) !== false ) {
		$block_content = do_shortcode( $block_content );
	}
	return $block_content;
}, 10, 2 );

/**
 * Force Spotify embeds to use dark theme and remove border-radius.
 */
add_filter( 'embed_oembed_html', function( $html, $url ) {
	if ( strpos( $url, 'spotify.com' ) !== false ) {
		// Add theme=0 (dark) to iframe src
		$html = preg_replace(
			'/src="([^"]*spotify[^"]*)"/',
			'src="$1&theme=0"',
			$html
		);
		// Strip inline border-radius
		$html = str_replace( 'border-radius: 12px', 'border-radius: 0', $html );
	}
	return $html;
}, 10, 2 );

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
 * Prevent Breeze from minifying theme CSS.
 *
 * Breeze CSS minification strips the onload handler from the deferred
 * custom.css link tag (media=print → media=all on load), which breaks
 * the non-render-blocking pattern. The theme handles its own CSS
 * optimization (critical inline + deferred full), so Breeze should
 * leave it alone.
 */
add_filter( 'breeze_exclude_css', function( $excluded ) {
	$excluded[] = 'custom.css';
	$excluded[] = 'critical.css';
	return $excluded;
} );

/**
 * Performance: Add fetchpriority=low to Interactivity API script modules.
 * Reduces network contention with LCP resources on mobile.
 */
add_filter( 'script_module_loader_tag', function( $tag, $id ) {
	$low_priority = array(
		'@wordpress/interactivity',
		'@wordpress/interactivity-router',
		'@wordpress/block-library/navigation',
	);
	foreach ( $low_priority as $module_id ) {
		if ( str_contains( $id, $module_id ) ) {
			$tag = str_replace( '<script ', '<script fetchpriority="low" ', $tag );
			break;
		}
	}
	return $tag;
}, 10, 2 );

/**
 * Security: Strip WordPress and plugin generator meta tags.
 */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// Output buffer: strip remaining generator meta tags from plugins.
add_action( 'template_redirect', function() {
	ob_start( function( $html ) {
		// Strip generator meta tags.
		$html = preg_replace( '/<meta name="generator"[^>]*>\n?/i', '', $html );

		// Strip WP Statistics frontend CSS (survives wp_dequeue when Breeze bundles it).
		$html = preg_replace( '/<link[^>]*wp-statistics[^>]*>\n?/i', '', $html );

		// Strip Cloudflare Turnstile on non-contact pages (17 KiB render-blocking).
		if ( ! is_page( 'contact' ) ) {
			$html = preg_replace( '/<script[^>]*challenges\.cloudflare\.com[^>]*><\/script>\n?/i', '', $html );
			$html = preg_replace( '/<script[^>]*turnstile[^>]*><\/script>\n?/i', '', $html );
		}

		return $html;
	});
} );

/**
 * Accessibility: Add skip-to-content link as first element in body.
 */
add_action( 'wp_body_open', function() {
	echo '<a class="sn-skip-link" href="#wp--skip-link--target">Skip to content</a>';
} );

/**
 * ──────────────────────────────────────────────────
 * TEMPLATE OVERRIDE PROTECTION
 * 
 * WordPress block themes store Site Editor customizations as
 * wp_template / wp_template_part custom post types in the database.
 * These override the actual theme files, which means uploading an
 * updated theme ZIP won't change the site until the DB records are
 * deleted. This section handles that automatically.
 * ──────────────────────────────────────────────────
 */

/**
 * Delete all database-stored template overrides.
 * Called on theme activation and via admin button.
 */
function sn_clear_template_overrides() {
	$post_types = array( 'wp_template', 'wp_template_part' );
	$count      = 0;

	foreach ( $post_types as $post_type ) {
		$posts = get_posts( array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
		) );
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
			$count++;
		}
	}

	return $count;
}

/**
 * Auto-clear on theme activation (covers fresh installs + re-activations).
 */
add_action( 'after_switch_theme', function() {
	sn_clear_template_overrides();
} );

/**
 * Auto-clear when this theme is updated via the WP updater.
 */
add_action( 'upgrader_process_complete', function( $upgrader, $options ) {
	if ( 'theme' === ( $options['type'] ?? '' ) ) {
		$theme_slug = get_option( 'stylesheet' );
		$updated    = $options['themes'] ?? ( isset( $options['theme'] ) ? array( $options['theme'] ) : array() );
		if ( in_array( $theme_slug, $updated, true ) ) {
			sn_clear_template_overrides();
		}
	}
}, 10, 2 );

/**
 * Admin page: manual reset button under Appearance menu.
 */
add_action( 'admin_menu', function() {
	add_theme_page(
		'Reset Templates',
		'Reset Templates',
		'manage_options',
		'sn-reset-templates',
		'sn_reset_templates_page'
	);
} );

function sn_reset_templates_page() {
	if ( isset( $_POST['sn_reset_templates'] ) && check_admin_referer( 'sn_reset_templates_nonce' ) ) {
		$count = sn_clear_template_overrides();
		echo '<div class="notice notice-success"><p><strong>' . esc_html( $count ) . ' database template override(s) deleted.</strong> The site is now using theme files directly.</p></div>';
	}

	// Check for existing overrides.
	$existing = get_posts( array(
		'post_type'      => array( 'wp_template', 'wp_template_part' ),
		'posts_per_page' => -1,
		'post_status'    => 'any',
	) );

	echo '<div class="wrap">';
	echo '<h1>Signal &amp; Noise — Template Reset</h1>';
	echo '<p>WordPress stores Site Editor customizations in the database, which override theme files.<br>';
	echo 'Use this to force the site to read templates from the theme directly.</p>';
	echo '<p><strong>Database template overrides found:</strong> ' . count( $existing ) . '</p>';

	if ( $existing ) {
		echo '<ul style="margin-left:2em;">';
		foreach ( $existing as $tpl ) {
			echo '<li><code>' . esc_html( $tpl->post_type ) . '/' . esc_html( $tpl->post_name ) . '</code></li>';
		}
		echo '</ul>';
	}

	echo '<form method="post" style="margin-top:1.5em;">';
	wp_nonce_field( 'sn_reset_templates_nonce' );
	echo '<input type="submit" name="sn_reset_templates" class="button button-primary" value="Clear All Template Overrides" />';
	echo '</form>';
	echo '<p class="description" style="margin-top:1em;">This also runs automatically when you activate or update the theme.</p>';
	echo '</div>';
}

/**
 * Performance: Auto-flush theme cache when deployed version changes.
 *
 * CI/CD deploys bypass WordPress's upgrader hooks, so the cached theme
 * header (version, description, etc.) goes stale. This detects the
 * mismatch on the first admin page load after deploy and flushes it.
 * Zero cost on subsequent loads — only fires when the version changes.
 */
add_action( 'admin_init', function() {
	$theme          = wp_get_theme();
	$current        = $theme->get( 'Version' );
	$cached_version = get_option( 'sn_deployed_version' );

	if ( $cached_version !== $current ) {
		// Clear theme-related caches.
		delete_site_transient( 'update_themes' );
		wp_clean_themes_cache();
		wp_cache_flush();

		// Store new version so this only runs once per deploy.
		update_option( 'sn_deployed_version', $current, true );
	}
} );
