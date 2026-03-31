<?php
/**
 * Signal & Noise — Theme Functions
 *
 * @package SignalNoise
 * @since 1.0.0
 * @version 3.12.1
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
	$post_types = array( 'wp_template', 'wp_template_part', 'wp_navigation' );
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
 * Admin page: Signal & Noise Theme Options under Appearance menu.
 */
add_action( 'admin_menu', function() {
	add_theme_page(
		'Signal & Noise',
		'Signal & Noise',
		'manage_options',
		'sn-theme-options',
		'sn_theme_options_page'
	);
} );

function sn_theme_options_page() {
	$theme         = wp_get_theme( 'signal-and-noise' );
	$local_version = $theme->get( 'Version' );
	$notices       = array();

	// Handle form actions.
	if ( isset( $_POST['sn_action'] ) && check_admin_referer( 'sn_theme_options_nonce' ) ) {
		$action = sanitize_text_field( $_POST['sn_action'] );

		if ( 'clear_overrides' === $action ) {
			$count = sn_clear_template_overrides();
			$notices[] = array( 'success', $count . ' database override(s) cleared. Site is reading from theme files.' );
		}

		if ( 'purge_caches' === $action ) {
			// WP object cache + transients.
			wp_cache_flush();
			global $wpdb;
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" );
			delete_site_transient( 'update_themes' );
			wp_clean_themes_cache();

			// Breeze file caches.
			$breeze_dirs = array(
				ABSPATH . 'wp-content/cache/breeze-minification/',
				ABSPATH . 'wp-content/cache/breeze/',
			);
			foreach ( $breeze_dirs as $dir ) {
				if ( is_dir( $dir ) ) {
					$it = new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
						RecursiveIteratorIterator::CHILD_FIRST
					);
					foreach ( $it as $file ) {
						$file->isDir() ? @rmdir( $file->getPathname() ) : @unlink( $file->getPathname() );
					}
				}
			}

			// Breeze Varnish purge (if available).
			if ( class_exists( 'Breeze_PurgeVarnish' ) ) {
				Breeze_PurgeVarnish::purge_cache();
			} elseif ( function_exists( 'breeze_varnish_purge_all' ) ) {
				breeze_varnish_purge_all();
			}

			$notices[] = array( 'success', 'All caches purged: WP object cache, transients, Breeze page cache, Breeze minification, Varnish.' );
		}

		if ( 'check_updates' === $action ) {
			delete_transient( 'sn_github_release' );
			delete_site_transient( 'update_themes' );
			wp_clean_themes_cache();
			$notices[] = array( 'info', 'Update cache cleared. Visit <a href="' . admin_url( 'update-core.php' ) . '">Dashboard → Updates</a> to check for new versions.' );
		}

		if ( 'full_reset' === $action ) {
			// Clear overrides.
			$count = sn_clear_template_overrides();

			// Purge caches.
			wp_cache_flush();
			global $wpdb;
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" );
			delete_site_transient( 'update_themes' );
			delete_transient( 'sn_github_release' );
			wp_clean_themes_cache();

			$breeze_dirs = array(
				ABSPATH . 'wp-content/cache/breeze-minification/',
				ABSPATH . 'wp-content/cache/breeze/',
			);
			foreach ( $breeze_dirs as $dir ) {
				if ( is_dir( $dir ) ) {
					$it = new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
						RecursiveIteratorIterator::CHILD_FIRST
					);
					foreach ( $it as $file ) {
						$file->isDir() ? @rmdir( $file->getPathname() ) : @unlink( $file->getPathname() );
					}
				}
			}

			if ( class_exists( 'Breeze_PurgeVarnish' ) ) {
				Breeze_PurgeVarnish::purge_cache();
			} elseif ( function_exists( 'breeze_varnish_purge_all' ) ) {
				breeze_varnish_purge_all();
			}

			$notices[] = array( 'success', 'Full reset complete: ' . $count . ' override(s) cleared + all caches purged.' );
		}
	}

	// Get GitHub latest release.
	$github_version = 'Unknown';
	$github_url     = '#';
	if ( defined( 'SN_GITHUB_TOKEN' ) ) {
		$cached = get_transient( 'sn_github_release' );
		if ( $cached ) {
			$github_version = ltrim( $cached['tag_name'] ?? 'Unknown', 'v' );
			$github_url     = $cached['html_url'] ?? '#';
		} else {
			$response = wp_remote_get(
				'https://api.github.com/repos/' . SN_GITHUB_REPO . '/releases/latest',
				array(
					'headers' => array(
						'Authorization' => 'token ' . SN_GITHUB_TOKEN,
						'Accept'        => 'application/vnd.github.v3+json',
					),
					'timeout' => 10,
				)
			);
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				set_transient( 'sn_github_release', $data, 12 * HOUR_IN_SECONDS );
				$github_version = ltrim( $data['tag_name'] ?? 'Unknown', 'v' );
				$github_url     = $data['html_url'] ?? '#';
			}
		}
	}

	// Count existing overrides.
	$overrides = get_posts( array(
		'post_type'      => array( 'wp_template', 'wp_template_part', 'wp_navigation' ),
		'posts_per_page' => -1,
		'post_status'    => 'any',
	) );

	$is_up_to_date = version_compare( $local_version, $github_version, '>=' );

	// Render page.
	echo '<div class="wrap">';
	echo '<h1 style="font-size:1.6em;margin-bottom:0.3em;">Signal &amp; Noise</h1>';
	echo '<p style="color:#666;margin-top:0;">Theme management and maintenance tools.</p>';

	// Notices.
	foreach ( $notices as $n ) {
		echo '<div class="notice notice-' . $n[0] . ' is-dismissible"><p>' . $n[1] . '</p></div>';
	}

	echo '<hr style="margin:1.5em 0;">';

	// ── STATUS ──
	echo '<h2 style="font-size:1.1em;margin-bottom:0.8em;">Status</h2>';
	echo '<table class="form-table" style="max-width:500px;">';
	echo '<tr><th style="width:180px;padding:8px 10px 8px 0;">Installed version</th><td style="padding:8px 0;"><code>' . esc_html( $local_version ) . '</code></td></tr>';
	echo '<tr><th style="padding:8px 10px 8px 0;">Latest on GitHub</th><td style="padding:8px 0;"><code>' . esc_html( $github_version ) . '</code>';
	if ( $is_up_to_date ) {
		echo ' <span style="color:#00a32a;">&#10003; Up to date</span>';
	} else {
		echo ' <span style="color:#d63638;">&#9650; Update available</span>';
	}
	echo '</td></tr>';
	echo '<tr><th style="padding:8px 10px 8px 0;">DB overrides</th><td style="padding:8px 0;">' . count( $overrides );
	if ( count( $overrides ) > 0 ) {
		echo ' <span style="color:#dba617;">&#9888; Templates are being read from database, not theme files</span>';
	} else {
		echo ' <span style="color:#00a32a;">&#10003; Clean</span>';
	}
	echo '</td></tr>';
	echo '<tr><th style="padding:8px 10px 8px 0;">Self-updater</th><td style="padding:8px 0;">';
	echo defined( 'SN_GITHUB_TOKEN' ) ? '<span style="color:#00a32a;">&#10003; Connected</span>' : '<span style="color:#d63638;">&#10005; SN_GITHUB_TOKEN not set in wp-config.php</span>';
	echo '</td></tr>';
	echo '</table>';

	// Override details.
	if ( $overrides ) {
		echo '<details style="margin-top:0.5em;"><summary style="cursor:pointer;color:#2271b1;font-size:0.85em;">View override details</summary><ul style="margin:0.5em 0 0 1.5em;">';
		foreach ( $overrides as $tpl ) {
			echo '<li><code>' . esc_html( $tpl->post_type ) . '/' . esc_html( $tpl->post_name ) . '</code></li>';
		}
		echo '</ul></details>';
	}

	echo '<hr style="margin:1.5em 0;">';

	// ── ACTIONS ──
	echo '<h2 style="font-size:1.1em;margin-bottom:0.8em;">Actions</h2>';
	echo '<form method="post">';
	wp_nonce_field( 'sn_theme_options_nonce' );

	echo '<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;">';

	// Full Reset button.
	echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:280px;">';
	echo '<strong style="display:block;margin-bottom:4px;">Full Reset</strong>';
	echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">Clears all template overrides and purges every cache. Use after theme uploads.</p>';
	echo '<button type="submit" name="sn_action" value="full_reset" class="button button-primary">Run Full Reset</button>';
	echo '</div>';

	// Clear Overrides.
	echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:280px;">';
	echo '<strong style="display:block;margin-bottom:4px;">Clear Overrides</strong>';
	echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">Removes template, template part, and navigation overrides from the database.</p>';
	echo '<button type="submit" name="sn_action" value="clear_overrides" class="button">Clear Overrides</button>';
	echo '</div>';

	// Purge Caches.
	echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:280px;">';
	echo '<strong style="display:block;margin-bottom:4px;">Purge Caches</strong>';
	echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">Clears WP object cache, transients, Breeze page/minification cache, and Varnish.</p>';
	echo '<button type="submit" name="sn_action" value="purge_caches" class="button">Purge All Caches</button>';
	echo '</div>';

	// Check Updates.
	echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:280px;">';
	echo '<strong style="display:block;margin-bottom:4px;">Check for Updates</strong>';
	echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">Clears the GitHub release cache and checks for new versions immediately.</p>';
	echo '<button type="submit" name="sn_action" value="check_updates" class="button">Check Now</button>';
	echo '</div>';

	echo '</div>'; // flex container
	echo '</form>';

	echo '<hr style="margin:1.5em 0;">';

	// ── LINKS ──
	echo '<h2 style="font-size:1.1em;margin-bottom:0.8em;">Links</h2>';
	echo '<ul style="margin:0;">';
	echo '<li><a href="https://github.com/juanlentino/signal-and-noise" target="_blank">GitHub Repository</a></li>';
	echo '<li><a href="https://github.com/juanlentino/signal-and-noise/releases" target="_blank">Release History</a></li>';
	if ( '#' !== $github_url && ! $is_up_to_date ) {
		echo '<li><a href="' . esc_url( $github_url ) . '" target="_blank">Latest Release Notes</a></li>';
	}
	echo '</ul>';

	echo '</div>'; // wrap
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

		// Clear all template/template-part/navigation overrides.
		sn_clear_template_overrides();

		// Store new version so this only runs once per deploy.
		update_option( 'sn_deployed_version', $current, true );
	}
} );

/**
 * ──────────────────────────────────────────────────
 * GITHUB SELF-UPDATER
 *
 * Checks GitHub releases for new versions and lets WordPress
 * handle updates through the normal Appearance → Themes UI.
 * No manual zip uploads, no deleting, no switching themes.
 * Just click "Update" like any other theme.
 *
 * Requires: SN_GITHUB_TOKEN constant in wp-config.php
 *   define( 'SN_GITHUB_TOKEN', 'github_pat_...' );
 *
 * The token needs only "contents: read" permission on
 * the signal-and-noise repo (fine-grained PAT).
 * ──────────────────────────────────────────────────
 */

define( 'SN_GITHUB_REPO', 'juanlentino/signal-and-noise' );
define( 'SN_THEME_SLUG',  'signal-and-noise' );

/**
 * Check GitHub for a newer release and inject it into WP's update system.
 */
add_filter( 'pre_set_site_transient_update_themes', function( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	if ( ! defined( 'SN_GITHUB_TOKEN' ) || empty( SN_GITHUB_TOKEN ) ) {
		return $transient;
	}

	// Check cache first (12 hour TTL).
	$cached = get_transient( 'sn_github_release' );
	if ( false === $cached ) {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . SN_GITHUB_REPO . '/releases/latest',
			array(
				'headers' => array(
					'Authorization' => 'token ' . SN_GITHUB_TOKEN,
					'Accept'        => 'application/vnd.github.v3+json',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $transient;
		}

		$cached = json_decode( wp_remote_retrieve_body( $response ), true );
		set_transient( 'sn_github_release', $cached, 12 * HOUR_IN_SECONDS );
	}

	$remote_version = ltrim( $cached['tag_name'] ?? '', 'v' );
	$local_version  = wp_get_theme( SN_THEME_SLUG )->get( 'Version' );

	if ( version_compare( $remote_version, $local_version, '>' ) ) {
		$transient->response[ SN_THEME_SLUG ] = array(
			'theme'       => SN_THEME_SLUG,
			'new_version' => $remote_version,
			'url'         => $cached['html_url'] ?? '',
			'package'     => 'https://api.github.com/repos/' . SN_GITHUB_REPO . '/zipball/' . $cached['tag_name'],
		);
	}

	return $transient;
} );

/**
 * Inject GitHub token into the download request so WP can fetch
 * the zipball from a private repo.
 */
add_filter( 'http_request_args', function( $args, $url ) {
	if ( defined( 'SN_GITHUB_TOKEN' ) && strpos( $url, 'api.github.com/repos/' . SN_GITHUB_REPO ) !== false ) {
		$args['headers']['Authorization'] = 'token ' . SN_GITHUB_TOKEN;
		$args['headers']['Accept']        = 'application/vnd.github.v3+json';
	}
	return $args;
}, 10, 2 );

/**
 * Rename the extracted folder to signal-and-noise.
 * Fixes the -1 folder problem for both GitHub zipball downloads
 * (juanlentino-signal-and-noise-HASH/) and manual zip uploads.
 */
add_filter( 'upgrader_source_selection', function( $source, $remote_source, $upgrader ) {
	// Only act on theme installations/updates.
	if ( ! $upgrader instanceof Theme_Upgrader ) {
		return $source;
	}

	// Check if the extracted folder contains our theme.
	$style = trailingslashit( $source ) . 'style.css';
	if ( ! file_exists( $style ) ) {
		return $source;
	}

	$theme_data = get_file_data( $style, array( 'Name' => 'Theme Name' ) );
	if ( empty( $theme_data['Name'] ) || false === strpos( $theme_data['Name'], 'Signal' ) ) {
		return $source;
	}

	$corrected = trailingslashit( $remote_source ) . SN_THEME_SLUG . '/';
	if ( $source === $corrected ) {
		return $source;
	}

	if ( @rename( $source, $corrected ) ) {
		return $corrected;
	}

	return $source;
}, 10, 3 );

/**
 * Clear the GitHub release cache when checking for updates manually.
 */
add_action( 'load-update-core.php', function() {
	delete_transient( 'sn_github_release' );
} );
