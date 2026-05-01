<?php
/**
 * Signal & Noise — Per-post OG/Twitter card generator.
 *
 * Renders a 1200×630 brutalist text card per post/page using PHP GD
 * with the brand's own typefaces (Bebas Neue Regular for the title,
 * DM Mono Light for the eyebrow/dek/footer). Cards are cached as PNG
 * files in `wp-content/uploads/sn-og/` and rebuilt on every save via
 * `wp_after_insert_post`. If the cache is missing on first request,
 * the URL helper generates it lazily — no backfill migration needed.
 *
 * Resolution order for "what image should Yoast/the theme emit":
 *
 *   1. Featured image, if the post has one — never overridden.
 *   2. Generated card (this module) — if the cache exists, or can be
 *      built on demand.
 *   3. Theme default (`sn_og_image_url` filter falls through to the
 *      site icon URL the existing inc/seo.php hard-codes).
 *
 * Yoast SEO is the authoritative emitter on this site — its tags
 * appear first in `<head>` and win the social-card scrape race. Hooks
 * `wpseo_opengraph_image`, `wpseo_twitter_image`, and
 * `wpseo_opengraph_image_size` ensure Yoast and the theme agree on
 * which URL to ship.
 *
 * Robustness: every code path that touches GD is gated behind
 * `function_exists('imagettftext')`. If GD/FreeType isn't compiled in,
 * the helper returns null and callers fall back to the existing
 * default. The module never throws on a missing font file or an
 * unwriteable uploads dir — it just bails and logs nothing, since OG
 * cards aren't user-blocking.
 *
 * @package SignalNoise
 * @since 6.3.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_OG_DIRNAME = 'sn-og';
const SN_OG_WIDTH   = 1200;
const SN_OG_HEIGHT  = 630;

/**
 * Resolve the upload dir for OG cards. Creates the subdirectory on
 * first call. Returns false if WP can't determine an upload dir at all
 * (e.g. permissions error in a misconfigured install).
 *
 * @return array{path: string, url: string}|false
 */
function sn_og_upload_dir() {
	$up = wp_upload_dir();
	if ( ! empty( $up['error'] ) ) {
		return false;
	}
	$path = $up['basedir'] . '/' . SN_OG_DIRNAME;
	$url  = $up['baseurl'] . '/' . SN_OG_DIRNAME;
	if ( ! file_exists( $path ) && ! wp_mkdir_p( $path ) ) {
		return false;
	}
	return array( 'path' => $path, 'url' => $url );
}

/**
 * Return the OG image URL for a post, with a cache-buster query string
 * tied to post-modified time. Featured image wins; otherwise the
 * generated card; null if neither is available so callers can fall
 * back to their default.
 *
 * @param int|WP_Post $post
 * @return string|null
 */
function sn_og_image_url_for_post( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return null;
	}

	if ( has_post_thumbnail( $post ) ) {
		$thumb = get_the_post_thumbnail_url( $post, 'large' );
		if ( $thumb ) {
			return $thumb;
		}
	}

	$dir = sn_og_upload_dir();
	if ( ! $dir ) {
		return null;
	}

	$filename = 'post-' . $post->ID . '.png';
	$path     = $dir['path'] . '/' . $filename;
	if ( ! file_exists( $path ) ) {
		sn_generate_og_card( $post->ID );
	}
	if ( ! file_exists( $path ) ) {
		return null;
	}

	$bust = (int) get_post_modified_time( 'U', true, $post );
	return $dir['url'] . '/' . $filename . '?v=' . $bust;
}

/**
 * Render and cache an OG card for a post. Returns true on success,
 * false on any failure (missing GD, missing fonts, write error). The
 * function is intentionally quiet — OG card generation isn't critical
 * path, and failure modes naturally cascade to the default fallback.
 *
 * @param int $post_id
 * @return bool
 */
function sn_generate_og_card( $post_id ) {
	if ( ! function_exists( 'imagettftext' ) ) {
		return false;
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}

	$dir = sn_og_upload_dir();
	if ( ! $dir ) {
		return false;
	}

	$bebas_path  = get_theme_file_path( 'assets/fonts/og/BebasNeue-Regular.ttf' );
	$dmmono_path = get_theme_file_path( 'assets/fonts/og/DMMono-Light.ttf' );
	if ( ! file_exists( $bebas_path ) || ! file_exists( $dmmono_path ) ) {
		return false;
	}

	$im    = imagecreatetruecolor( SN_OG_WIDTH, SN_OG_HEIGHT );
	$white = imagecolorallocate( $im, 255, 255, 255 );
	$black = imagecolorallocate( $im, 0, 0, 0 );
	$gray  = imagecolorallocate( $im, 102, 102, 102 ); // matches "rust" #666666
	$red   = imagecolorallocate( $im, 224, 4, 4 );     // matches "blood" #e00404
	imagefilledrectangle( $im, 0, 0, SN_OG_WIDTH, SN_OG_HEIGHT, $white );

	$pad_x     = 80;
	$right     = SN_OG_WIDTH - $pad_x;
	$max_width = $right - $pad_x;

	// Top accent: red bar + site eyebrow.
	imagefilledrectangle( $im, $pad_x, 80, $pad_x + 60, 84, $red );
	imagettftext( $im, 18, 0, $pad_x, 130, $gray, $dmmono_path, 'JUANLENTINO.COM' );

	// Title: Bebas Neue, big, up to 3 lines.
	$title       = (string) $post->post_title;
	$title_lines = sn_og_wrap_lines( $title, 88, $bebas_path, $max_width, 3 );
	$y           = 250;
	foreach ( $title_lines as $line ) {
		imagettftext( $im, 88, 0, $pad_x, $y, $black, $bebas_path, $line );
		$y += 100;
	}

	// Excerpt: prefer post_excerpt; otherwise derive from cleaned content.
	$excerpt = trim( (string) $post->post_excerpt );
	if ( '' === $excerpt ) {
		$cleaned = preg_replace( '/<!--\s*\/?wp:[^>]*-->/', ' ', $post->post_content );
		$cleaned = wp_strip_all_tags( strip_shortcodes( $cleaned ) );
		$excerpt = wp_trim_words( $cleaned, 36, '…' );
	}
	$excerpt_lines = sn_og_wrap_lines( $excerpt, 26, $dmmono_path, $max_width, 3 );
	$y             = SN_OG_HEIGHT - 180;
	foreach ( $excerpt_lines as $line ) {
		imagettftext( $im, 26, 0, $pad_x, $y, $gray, $dmmono_path, $line );
		$y += 42;
	}

	// Footer: reading time in red, right-aligned site mark would clutter the
	// brutalist treatment so we keep it minimal.
	$minutes = function_exists( 'sn_get_reading_time' ) ? sn_get_reading_time( $post ) : 1;
	$footer  = strtoupper( $minutes . ' MIN READ' );
	imagettftext( $im, 18, 0, $pad_x, SN_OG_HEIGHT - 60, $red, $dmmono_path, $footer );

	$path = $dir['path'] . '/post-' . (int) $post_id . '.png';
	$ok   = imagepng( $im, $path, 6 );
	imagedestroy( $im );
	return (bool) $ok;
}

/**
 * Greedy word-wrap that uses imagettfbbox to measure exact pixel widths
 * for the active font and size. Truncates the last line with an
 * ellipsis if the remaining text would overflow.
 *
 * @return string[] Up to $max_lines lines.
 */
function sn_og_wrap_lines( $text, $size, $font, $max_width, $max_lines ) {
	$text = trim( (string) $text );
	if ( '' === $text ) {
		return array();
	}
	$words   = preg_split( '/\s+/', $text );
	$lines   = array();
	$current = '';

	foreach ( $words as $i => $word ) {
		$candidate = ( '' === $current ) ? $word : ( $current . ' ' . $word );
		$bbox      = imagettfbbox( $size, 0, $font, $candidate );
		$width     = $bbox[2] - $bbox[0];

		if ( $width <= $max_width ) {
			$current = $candidate;
			continue;
		}
		// Overflow — flush current line and start a new one with this word.
		if ( '' !== $current ) {
			$lines[] = $current;
			if ( count( $lines ) >= $max_lines - 1 ) {
				// Last allowed line: pack remaining words and ellipsize.
				$rest = implode( ' ', array_slice( $words, $i ) );
				$bbox = imagettfbbox( $size, 0, $font, $rest );
				while ( ( $bbox[2] - $bbox[0] ) > $max_width && strlen( $rest ) > 1 ) {
					$rest = rtrim( substr( $rest, 0, -1 ) );
					$rest = rtrim( $rest, ".,;:!? \t" ) . '…';
					$bbox = imagettfbbox( $size, 0, $font, $rest );
				}
				$lines[] = $rest;
				return $lines;
			}
		}
		$current = $word;
	}
	if ( '' !== $current ) {
		$lines[] = $current;
	}
	return array_slice( $lines, 0, $max_lines );
}

/**
 * Rebuild the cached card whenever a post or page is saved. Skips
 * revisions/autosaves and unpublished content.
 */
add_action( 'wp_after_insert_post', function( $post_id, $post, $update, $post_before ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}
	if ( 'publish' !== $post->post_status ) {
		return;
	}
	sn_generate_og_card( $post_id );
}, 20, 4 );

/**
 * Theme's own OG emitter (inc/seo.php) reads through this filter.
 */
add_filter( 'sn_og_image_url', function( $default ) {
	$post = get_post();
	if ( ! $post ) {
		return $default;
	}
	$url = sn_og_image_url_for_post( $post );
	return $url ? $url : $default;
} );

/**
 * Yoast SEO emits its OG/Twitter tags first and wins the scrape race,
 * so we filter its values to point at the same generated card. If
 * Yoast isn't installed these filters simply never fire.
 */
add_filter( 'wpseo_opengraph_image', function( $image ) {
	if ( ! is_singular() ) {
		return $image;
	}
	$url = sn_og_image_url_for_post( get_post() );
	return $url ? $url : $image;
} );

add_filter( 'wpseo_twitter_image', function( $image ) {
	if ( ! is_singular() ) {
		return $image;
	}
	$url = sn_og_image_url_for_post( get_post() );
	return $url ? $url : $image;
} );

add_filter( 'wpseo_opengraph_image_size', function( $size ) {
	// Tell Yoast the generated card is full-bleed; suppresses thumbnail-size logic.
	return 'full';
} );
