<?php
/**
 * Signal & Noise — Theme Functions
 *
 * @package SignalNoise
 * @since 1.0.0
 * @version 4.2.3
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
 * Performance: Load full custom.css.
 * Critical CSS above covers first paint; this fills in the rest.
 * Loaded normally (not deferred) because Breeze minification strips
 * the onload handler from deferred stylesheets.
 */
add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style(
		'sn-custom',
		get_theme_file_uri( 'assets/css/custom.css' ),
		array(),
		wp_get_theme()->get( 'Version' )
	);
}, 10 );

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
 * ──────────────────────────────────────────────────
 * PLAUSIBLE CE DASHBOARD WIDGETS
 *
 * Native WordPress dashboard widgets pulling from the Plausible
 * Stats API. Cached with transients. No iframe, no plugin.
 *
 * Requires: SN_PLAUSIBLE_KEY constant in wp-config.php
 * ──────────────────────────────────────────────────
 */

define( 'SN_PLAUSIBLE_URL', 'https://plausible-analytics-ce-production-fcb9.up.railway.app' );
define( 'SN_PLAUSIBLE_SITE', 'juanlentino.com' );

function sn_plausible_api( $endpoint, $params = array(), $cache_minutes = 15 ) {
	if ( ! defined( 'SN_PLAUSIBLE_KEY' ) ) return null;
	$cache_key = 'sn_pa_' . md5( $endpoint . serialize( $params ) );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) return $cached;
	$url = SN_PLAUSIBLE_URL . '/api/v1/stats/' . $endpoint . '?site_id=' . SN_PLAUSIBLE_SITE;
	foreach ( $params as $k => $v ) $url .= '&' . urlencode( $k ) . '=' . urlencode( $v );
	$response = wp_remote_get( $url, array(
		'headers' => array( 'Authorization' => 'Bearer ' . SN_PLAUSIBLE_KEY ),
		'timeout' => 10,
	) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return null;
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	set_transient( $cache_key, $data, $cache_minutes * MINUTE_IN_SECONDS );
	return $data;
}

function sn_fmt( $n ) {
	if ( $n >= 1000000 ) return round( $n / 1000000, 1 ) . 'M';
	if ( $n >= 1000 ) return round( $n / 1000, 1 ) . 'K';
	return number_format( $n );
}

function sn_duration( $s ) {
	if ( $s < 60 ) return $s . 's';
	return floor( $s / 60 ) . 'm ' . ( $s % 60 ) . 's';
}

function sn_metric_card( $label, $value, $change = null ) {
	echo '<div style="flex:1;min-width:90px;text-align:center;padding:10px 6px;">';
	echo '<div style="font-size:1.5em;font-weight:600;color:#1d2327;">' . esc_html( $value ) . '</div>';
	echo '<div style="font-size:0.75em;color:#787c82;margin-top:2px;">' . esc_html( $label ) . '</div>';
	if ( null !== $change ) {
		$color = $change > 0 ? '#00a32a' : ( $change < 0 ? '#d63638' : '#787c82' );
		$arrow = $change > 0 ? '&#9650;' : ( $change < 0 ? '&#9660;' : '&#8212;' );
		echo '<div style="font-size:0.7em;color:' . $color . ';margin-top:2px;">' . $arrow . ' ' . abs( $change ) . '%</div>';
	}
	echo '</div>';
}

function sn_ranked_list( $items, $key, $metric = 'visitors' ) {
	if ( empty( $items ) ) { echo '<p style="color:#787c82;font-size:0.85em;">No data yet.</p>'; return; }
	$max = max( array_column( $items, $metric ) );
	echo '<table style="width:100%;border-collapse:collapse;">';
	foreach ( array_slice( $items, 0, 10 ) as $item ) {
		$label = $item[ $key ] ?: '(direct / none)';
		$count = $item[ $metric ];
		$pct   = $max > 0 ? round( ( $count / $max ) * 100 ) : 0;
		echo '<tr><td style="padding:4px 8px 4px 0;font-size:0.85em;position:relative;">';
		echo '<div style="position:absolute;top:0;left:0;bottom:0;width:' . $pct . '%;background:rgba(224,4,4,0.07);border-radius:2px;"></div>';
		echo '<span style="position:relative;">' . esc_html( $label ) . '</span></td>';
		echo '<td style="padding:4px 0;font-size:0.85em;text-align:right;white-space:nowrap;font-weight:500;">' . sn_fmt( $count ) . '</td></tr>';
	}
	echo '</table>';
}

add_action( 'wp_dashboard_setup', function() {
	if ( ! defined( 'SN_PLAUSIBLE_KEY' ) ) return;

	// ── VISITORS TODAY ──
	wp_add_dashboard_widget( 'sn_pa_overview', 'Visitors Today', function() {
		$realtime  = sn_plausible_api( 'realtime/visitors', array(), 1 );
		$today     = sn_plausible_api( 'aggregate', array( 'period' => 'day', 'metrics' => 'visitors,pageviews,bounce_rate,visit_duration' ), 5 );
		$yesterday = sn_plausible_api( 'aggregate', array( 'period' => 'custom', 'date' => date('Y-m-d', strtotime('-1 day')) . ',' . date('Y-m-d', strtotime('-1 day')), 'metrics' => 'visitors,pageviews' ), 60 );

		$rv = is_numeric( $realtime ) ? $realtime : ( $realtime['results'] ?? 0 );
		echo '<div style="text-align:center;padding:8px 0 12px;border-bottom:1px solid #f0f0f0;margin-bottom:10px;">';
		echo '<div style="font-size:2em;font-weight:700;color:#1d2327;">' . intval( $rv ) . '</div>';
		echo '<div style="font-size:0.75em;color:#e00404;font-weight:500;">ONLINE NOW</div></div>';

		if ( $today && isset( $today['results'] ) ) {
			$r = $today['results'];
			$yv = $yesterday['results']['visitors']['value'] ?? 0;
			$yp = $yesterday['results']['pageviews']['value'] ?? 0;
			$tv = $r['visitors']['value'] ?? 0;
			$tp = $r['pageviews']['value'] ?? 0;
			$cv = $yv > 0 ? round((($tv-$yv)/$yv)*100) : null;
			$cp = $yp > 0 ? round((($tp-$yp)/$yp)*100) : null;
			echo '<div style="display:flex;flex-wrap:wrap;">';
			sn_metric_card( 'Visitors', sn_fmt($tv), $cv );
			sn_metric_card( 'Pageviews', sn_fmt($tp), $cp );
			sn_metric_card( 'Bounce', ($r['bounce_rate']['value'] ?? 0).'%' );
			sn_metric_card( 'Duration', sn_duration($r['visit_duration']['value'] ?? 0) );
			echo '</div>';
		}
		echo '<p style="margin:10px 0 0;font-size:0.8em;text-align:right;"><a href="' . SN_PLAUSIBLE_URL . '/' . SN_PLAUSIBLE_SITE . '" target="_blank">Full dashboard &rarr;</a></p>';
	} );

	// ── 30-DAY TREND ──
	wp_add_dashboard_widget( 'sn_pa_trend', 'Visitor Trend (30 days)', function() {
		$data = sn_plausible_api( 'timeseries', array( 'period' => '30d', 'metrics' => 'visitors' ), 30 );
		$results = $data['results'] ?? array();
		if ( empty( $results ) ) { echo '<p style="color:#787c82;font-size:0.85em;">No data yet.</p>'; return; }

		$max = max( array_column( $results, 'visitors' ) );
		$max = $max > 0 ? $max : 1;
		$total = array_sum( array_column( $results, 'visitors' ) );
		$bar_count = count( $results );

		echo '<div style="margin-bottom:8px;font-size:0.85em;color:#1d2327;"><strong>' . sn_fmt( $total ) . '</strong> <span style="color:#787c82;">visitors in the last 30 days</span></div>';
		echo '<div style="display:flex;align-items:flex-end;height:80px;gap:2px;">';
		foreach ( $results as $day ) {
			$v = $day['visitors'];
			$h = max( round( ( $v / $max ) * 100 ), 2 );
			$date = date( 'M j', strtotime( $day['date'] ) );
			echo '<div title="' . esc_attr( $date . ': ' . $v . ' visitors' ) . '" style="flex:1;height:' . $h . '%;background:#e00404;border-radius:1px;min-width:4px;opacity:' . ( $v > 0 ? '1' : '0.2' ) . ';"></div>';
		}
		echo '</div>';
		echo '<div style="display:flex;justify-content:space-between;font-size:0.7em;color:#787c82;margin-top:4px;">';
		echo '<span>' . date( 'M j', strtotime( $results[0]['date'] ) ) . '</span>';
		echo '<span>' . date( 'M j', strtotime( end( $results )['date'] ) ) . '</span>';
		echo '</div>';
	} );

	// ── TOP STATS (TABBED) ──
	wp_add_dashboard_widget( 'sn_pa_topstats', 'Top Stats (30 days)', function() {
		$pages     = sn_plausible_api( 'breakdown', array( 'period' => '30d', 'property' => 'event:page', 'metrics' => 'visitors', 'limit' => '10' ), 15 );
		$sources   = sn_plausible_api( 'breakdown', array( 'period' => '30d', 'property' => 'visit:source', 'metrics' => 'visitors', 'limit' => '10' ), 15 );
		$countries = sn_plausible_api( 'breakdown', array( 'period' => '30d', 'property' => 'visit:country', 'metrics' => 'visitors', 'limit' => '10' ), 15 );
		$devices   = sn_plausible_api( 'breakdown', array( 'period' => '30d', 'property' => 'visit:device', 'metrics' => 'visitors', 'limit' => '5' ), 15 );
		$browsers  = sn_plausible_api( 'breakdown', array( 'period' => '30d', 'property' => 'visit:browser', 'metrics' => 'visitors', 'limit' => '5' ), 15 );

		$tabs = array(
			'pages'     => array( 'label' => 'Pages',     'data' => $pages['results'] ?? array(),     'key' => 'page' ),
			'sources'   => array( 'label' => 'Sources',   'data' => $sources['results'] ?? array(),   'key' => 'source' ),
			'countries' => array( 'label' => 'Countries', 'data' => $countries['results'] ?? array(), 'key' => 'country' ),
			'devices'   => array( 'label' => 'Devices',   'data' => $devices['results'] ?? array(),   'key' => 'device' ),
			'browsers'  => array( 'label' => 'Browsers',  'data' => $browsers['results'] ?? array(),  'key' => 'browser' ),
		);

		$uid = 'sn_ts_' . wp_rand();
		echo '<div style="display:flex;border-bottom:1px solid #c3c4c7;margin-bottom:12px;">';
		$first = true;
		foreach ( $tabs as $id => $tab ) {
			$active = $first ? 'border-bottom:2px solid #e00404;color:#1d2327;' : 'border-bottom:2px solid transparent;color:#787c82;';
			echo '<div onclick="snTopTab(\'' . $uid . '\',\'' . $id . '\')" style="flex:1;text-align:center;padding:8px 4px;cursor:pointer;font-size:0.8em;font-weight:500;' . $active . '" id="' . $uid . '_tab_' . $id . '">' . $tab['label'] . '</div>';
			$first = false;
		}
		echo '</div>';

		$first = true;
		foreach ( $tabs as $id => $tab ) {
			echo '<div id="' . $uid . '_panel_' . $id . '" style="' . ( $first ? '' : 'display:none;' ) . '">';
			sn_ranked_list( $tab['data'], $tab['key'] );
			echo '</div>';
			$first = false;
		}

		echo '<script>function snTopTab(u,t){["pages","sources","countries","devices","browsers"].forEach(function(i){';
		echo 'var p=document.getElementById(u+"_panel_"+i);var b=document.getElementById(u+"_tab_"+i);';
		echo 'if(p)p.style.display=i===t?"":"none";';
		echo 'if(b){b.style.borderBottomColor=i===t?"#e00404":"transparent";b.style.color=i===t?"#1d2327":"#787c82";}';
		echo '});}</script>';
	} );

	// ── VISITOR MAP ──
	wp_add_dashboard_widget( 'sn_pa_map', 'Visitor Map (30 days)', function() {
		$countries = sn_plausible_api( 'breakdown', array( 'period' => '30d', 'property' => 'visit:country', 'metrics' => 'visitors', 'limit' => '100' ), 30 );
		$results = $countries['results'] ?? array();

		if ( empty( $results ) ) { echo '<p style="color:#787c82;font-size:0.85em;">No data yet.</p>'; return; }

		$map_data = array();
		foreach ( $results as $c ) {
			$code = strtolower( $c['country'] ?? '' );
			if ( $code ) $map_data[ $code ] = $c['visitors'];
		}

		$map_id = 'sn_map_' . wp_rand();
		echo '<div id="' . $map_id . '" style="width:100%;height:300px;"></div>';
		echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap/dist/css/jsvectormap.min.css">';
		echo '<script src="https://cdn.jsdelivr.net/npm/jsvectormap"></script>';
		echo '<script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world.js"></script>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){';
		echo 'var d=' . wp_json_encode( $map_data ) . ';';
		echo 'var vals=Object.values(d);var mx=Math.max.apply(null,vals)||1;';
		echo 'var colors={};Object.keys(d).forEach(function(k){var p=d[k]/mx;colors[k]="rgba(224,4,4,"+Math.max(0.15,p)+")";});';
		echo 'new jsVectorMap({selector:"#' . $map_id . '",map:"world",';
		echo 'backgroundColor:"transparent",';
		echo 'regionStyle:{initial:{fill:"#e8e8e8",stroke:"#fff",strokeWidth:0.5},hover:{fill:"#e00404"}},';
		echo 'series:{regions:[{values:colors,attribute:"fill"}]},';
		echo 'showTooltip:true,';
		echo 'onRegionTooltipShow:function(e,el,code){var v=d[code]||0;el.html(el.html()+(v?" — "+v+" visitors":""));}';
		echo '});});</script>';
	} );
} );

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
 * Prevent Breeze from minifying critical inline CSS.
 */
add_filter( 'breeze_exclude_css', function( $excluded ) {
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
	$active_tab    = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';

	// Handle form actions.
	if ( isset( $_POST['sn_action'] ) && check_admin_referer( 'sn_theme_options_nonce' ) ) {
		$action = sanitize_text_field( $_POST['sn_action'] );

		if ( 'clear_overrides' === $action ) {
			$count = sn_clear_template_overrides();
			$notices[] = array( 'success', $count . ' database override(s) cleared. Site is reading from theme files.' );
		}

		if ( 'purge_caches' === $action ) {
			wp_cache_flush();
			delete_site_transient( 'update_themes' );
			wp_clean_themes_cache();

			// Delete transients via DB.
			global $wpdb;
			if ( $wpdb ) {
				$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" );
			}

			// Trigger Breeze purge via its own action hooks.
			do_action( 'breeze_clear_all_cache' );
			do_action( 'breeze_clear_varnish' );

			$notices[] = array( 'success', 'All caches purged.' );
		}

		if ( 'check_updates' === $action ) {
			delete_transient( 'sn_github_release' );
			delete_site_transient( 'update_themes' );
			wp_clean_themes_cache();
			$notices[] = array( 'info', 'Update cache cleared. Visit <a href="' . admin_url( 'update-core.php' ) . '">Dashboard &rarr; Updates</a> to check for new versions.' );
		}

		if ( 'full_reset' === $action ) {
			$count = sn_clear_template_overrides();
			wp_cache_flush();
			delete_site_transient( 'update_themes' );
			delete_transient( 'sn_github_release' );
			wp_clean_themes_cache();

			global $wpdb;
			if ( $wpdb ) {
				$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" );
			}

			do_action( 'breeze_clear_all_cache' );
			do_action( 'breeze_clear_varnish' );

			$notices[] = array( 'success', 'Full reset: ' . $count . ' override(s) cleared + all caches purged.' );
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

	$overrides     = get_posts( array( 'post_type' => array( 'wp_template', 'wp_template_part', 'wp_navigation' ), 'posts_per_page' => -1, 'post_status' => 'any' ) );
	$is_up_to_date = version_compare( $local_version, $github_version, '>=' );
	$base_url      = admin_url( 'themes.php?page=sn-theme-options' );

	// ── PAGE SHELL ──
	echo '<div class="wrap">';
	echo '<h1 style="font-size:1.6em;margin-bottom:0.2em;">Signal &amp; Noise</h1>';
	echo '<p style="color:#666;margin-top:0;margin-bottom:1em;">Theme management, maintenance, and analytics.</p>';

	// Notices.
	foreach ( $notices as $n ) {
		echo '<div class="notice notice-' . $n[0] . ' is-dismissible"><p>' . $n[1] . '</p></div>';
	}

	// ── TABS ──
	echo '<nav class="nav-tab-wrapper" style="margin-bottom:1.5em;">';
	echo '<a href="' . esc_url( $base_url . '&tab=dashboard' ) . '" class="nav-tab' . ( 'dashboard' === $active_tab ? ' nav-tab-active' : '' ) . '">Dashboard</a>';
	echo '<a href="' . esc_url( $base_url . '&tab=analytics' ) . '" class="nav-tab' . ( 'analytics' === $active_tab ? ' nav-tab-active' : '' ) . '">Analytics</a>';
	echo '<a href="' . esc_url( $base_url . '&tab=links' ) . '" class="nav-tab' . ( 'links' === $active_tab ? ' nav-tab-active' : '' ) . '">Links</a>';
	echo '</nav>';

	// ════════════════════════════════════════
	// TAB: DASHBOARD
	// ════════════════════════════════════════
	if ( 'dashboard' === $active_tab ) {

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
			echo ' <span style="color:#dba617;">&#9888; Reading from database, not theme files</span>';
		} else {
			echo ' <span style="color:#00a32a;">&#10003; Clean</span>';
		}
		echo '</td></tr>';
		echo '<tr><th style="padding:8px 10px 8px 0;">Self-updater</th><td style="padding:8px 0;">';
		echo defined( 'SN_GITHUB_TOKEN' ) ? '<span style="color:#00a32a;">&#10003; Connected</span>' : '<span style="color:#d63638;">&#10005; SN_GITHUB_TOKEN not set</span>';
		echo '</td></tr>';
		echo '</table>';

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

		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:260px;">';
		echo '<strong style="display:block;margin-bottom:4px;">Full Reset</strong>';
		echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">Clears all overrides and purges every cache. Use after theme updates.</p>';
		echo '<button type="submit" name="sn_action" value="full_reset" class="button button-primary">Run Full Reset</button>';
		echo '</div>';

		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:260px;">';
		echo '<strong style="display:block;margin-bottom:4px;">Clear Overrides</strong>';
		echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">Removes template, template part, and navigation DB entries.</p>';
		echo '<button type="submit" name="sn_action" value="clear_overrides" class="button">Clear Overrides</button>';
		echo '</div>';

		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:260px;">';
		echo '<strong style="display:block;margin-bottom:4px;">Purge Caches</strong>';
		echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">WP object cache, transients, Breeze page/minification, Varnish.</p>';
		echo '<button type="submit" name="sn_action" value="purge_caches" class="button">Purge All Caches</button>';
		echo '</div>';

		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:260px;">';
		echo '<strong style="display:block;margin-bottom:4px;">Check for Updates</strong>';
		echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">Clears GitHub cache and checks for new versions now.</p>';
		echo '<button type="submit" name="sn_action" value="check_updates" class="button">Check Now</button>';
		echo '</div>';

		echo '</div>';
		echo '</form>';

	// ════════════════════════════════════════
	// TAB: ANALYTICS
	// ════════════════════════════════════════
	} elseif ( 'analytics' === $active_tab ) {

		echo '<p>Analytics widgets are on the <a href="' . admin_url( 'index.php' ) . '">main Dashboard</a>. For the full interactive dashboard:</p>';
		echo '<p style="margin-top:1em;"><a href="' . SN_PLAUSIBLE_URL . '/' . SN_PLAUSIBLE_SITE . '" target="_blank" class="button button-primary">Open Plausible Dashboard &rarr;</a></p>';
		echo '<p style="margin-top:1.5em;color:#666;font-size:0.85em;">Widgets refresh every 5-15 minutes. Realtime visitor count refreshes every minute.</p>';

	// ════════════════════════════════════════
	// TAB: LINKS
	// ════════════════════════════════════════
	} elseif ( 'links' === $active_tab ) {

		echo '<table class="form-table" style="max-width:500px;">';
		echo '<tr><th style="width:180px;padding:8px 10px 8px 0;">GitHub Repository</th><td style="padding:8px 0;"><a href="https://github.com/juanlentino/signal-and-noise" target="_blank">juanlentino/signal-and-noise</a></td></tr>';
		echo '<tr><th style="padding:8px 10px 8px 0;">Release History</th><td style="padding:8px 0;"><a href="https://github.com/juanlentino/signal-and-noise/releases" target="_blank">All releases</a></td></tr>';
		if ( '#' !== $github_url && ! $is_up_to_date ) {
			echo '<tr><th style="padding:8px 10px 8px 0;">Latest Release</th><td style="padding:8px 0;"><a href="' . esc_url( $github_url ) . '" target="_blank">v' . esc_html( $github_version ) . ' release notes</a></td></tr>';
		}
		echo '<tr><th style="padding:8px 10px 8px 0;">Plausible Dashboard</th><td style="padding:8px 0;"><a href="https://plausible-analytics-ce-production-fcb9.up.railway.app/juanlentino.com" target="_blank">Open in Plausible</a></td></tr>';
		echo '<tr><th style="padding:8px 10px 8px 0;">Cloudflare</th><td style="padding:8px 0;"><a href="https://dash.cloudflare.com" target="_blank">Cloudflare Dashboard</a></td></tr>';
		echo '<tr><th style="padding:8px 10px 8px 0;">Cloudways</th><td style="padding:8px 0;"><a href="https://platform.cloudways.com" target="_blank">Cloudways Platform</a></td></tr>';
		echo '</table>';

	}

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
