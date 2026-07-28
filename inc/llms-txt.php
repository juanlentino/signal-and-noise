<?php
/**
 * Signal & Noise — llms.txt + llms-full.txt (llmstxt.org AEO discoverability).
 *
 * Serves a flat /llms.txt (curated key pages + feeds) and /llms-full.txt (the
 * same, plus a Notes section listing recent published posts) for LLM answer
 * engines. The reader-facing complement to robots.txt's AI-crawler policy: where
 * robots.txt says *who* may crawl, llms.txt says *what's worth reading*.
 *
 * Same flush-free virtual-route mechanism as /humans.txt and /.well-known/
 * security.txt (template_redirect priority 0, no add_rewrite_rule — the theme
 * must not flush; that is the plugin's job). Body is built from home_url() +
 * get_bloginfo() so it stays portable.
 *
 * @package SignalNoise
 * @since 10.19.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which llms file is being requested? Pure helper (takes the path) so it is
 * testable without $_SERVER.
 *
 * @param string $uri Request URI (may carry a query string).
 * @return string 'basic' for /llms.txt, 'full' for /llms-full.txt, '' otherwise.
 */
function sn_llms_txt_variant( $uri ) {
	$path = strtok( (string) $uri, '?' );
	$path = '/' . trim( (string) $path, '/' );
	if ( '/llms.txt' === $path ) {
		return 'basic';
	}
	if ( '/llms-full.txt' === $path ) {
		return 'full';
	}
	return '';
}

/**
 * Build the llms.txt markdown body. Trusted by construction: home_url() +
 * get_bloginfo() values + (for the full variant) published post titles/permalinks
 * + (v10.49.0) the curated pillar descriptors.
 *
 * @param bool  $full    Append the Notes corpus section.
 * @param array $notes   Rows of array{title,url,summary} (injected; queried in send()).
 * @param array $pillars Pillar descriptors array{slug,title,dek,designation}
 *                       (injected in send() from sn_theme_pillar_descriptors();
 *                       parameterized so this builder stays standalone-testable,
 *                       mirroring $notes). Empty set = section omitted entirely.
 * @return string
 */
function sn_llms_txt_body( $full = false, $notes = array(), $pillars = array() ) {
	$name = (string) get_bloginfo( 'name' );
	if ( '' === $name ) {
		$name = 'Signal & Noise';
	}
	$home = rtrim( home_url( '/' ), '/' );

	$lines = array(
		'# ' . $name,
		'',
		'> Personal site of Juan Lentino — writer, music producer, and audio engineer. Long-form notes on craft, a discography, and professional background.',
		'',
		'Content is hand-written. Analytics are first-party and cookieless; the site sets no advertising or cross-site tracking cookies.',
		'',
		'## Key pages',
		'',
		'- [About](' . $home . '/about/): who Juan Lentino is — background and identity.',
		'- [Notes](' . $home . '/notes/): the full essay and notes index (primary writing).',
		'- [Résumé](' . $home . '/resume/): professional experience and credentials.',
		'- [Music](' . $home . '/music/): discography and featured work.',
		'- [Uses](' . $home . '/about/uses/): tools, gear, and software.',
		'- [Now](' . $home . '/now/): what Juan is focused on right now.',
		'- [Accessibility](' . $home . '/accessibility/): accessibility statement and conformance notes.',
		'- [Contact](' . $home . '/contact/): how to get in touch.',
		'',
	);

	// v10.49.0: the curated pillar essays — the site's cornerstone long-form
	// work (signal-noise/pillar-essays block, command palette, and the
	// get-page-notes-pillars Ability all derive from the same descriptors).
	// The "№ 1.01 · Title" shape mirrors the block cards' vocabulary. An
	// empty descriptor set omits the whole section (honest empty).
	if ( is_array( $pillars ) && array() !== $pillars ) {
		$pillar_lines = array();
		foreach ( $pillars as $pillar ) {
			if ( empty( $pillar['slug'] ) || empty( $pillar['title'] ) ) {
				continue;
			}
			$label = (string) $pillar['title'];
			if ( ! empty( $pillar['designation'] ) ) {
				$label = '№ ' . $pillar['designation'] . ' · ' . $label;
			}
			$row = '- [' . $label . '](' . $home . '/' . trim( (string) $pillar['slug'], '/' ) . '/)';
			if ( ! empty( $pillar['dek'] ) ) {
				$row .= ': ' . $pillar['dek'];
			}
			$pillar_lines[] = $row;
		}
		if ( array() !== $pillar_lines ) {
			$lines[] = '## Pillar essays';
			$lines[] = '';
			$lines   = array_merge( $lines, $pillar_lines );
			$lines[] = '';
		}
	}

	$lines = array_merge( $lines, array(
		'## Feeds',
		'',
		'- [RSS feed](' . $home . '/feed/): subscribe to new Notes.',
		'- [JSON Feed](' . $home . '/feed/json/): machine-readable Notes feed.',
		'',
		'## Machine surfaces',
		'',
		'- [Discovery manifest](' . $home . '/.well-known/agents.json): a JSON index of every machine surface — feeds, sitemap, abilities, and provenance verification — for agents and crawlers.',
		'- [Abilities API](' . $home . '/wp-json/wp-abilities/v1/abilities): discover and run site abilities (the agent/automation surface).',
		'',
		'## Rights',
		'',
		'- [RSL license](' . $home . '/license.xml): machine-readable licensing terms (AI training permitted with attribution; the licence sits on top of the TDM reservation).',
		'- [TDM policy](' . $home . '/tdm-policy/): the text-and-data-mining policy behind the TDM-Reservation headers and [tdmrep.json](' . $home . '/.well-known/tdmrep.json).',
		'',
	) );

	if ( $full && ! empty( $notes ) ) {
		$lines[] = '## Notes';
		$lines[] = '';
		foreach ( $notes as $note ) {
			if ( empty( $note['title'] ) || empty( $note['url'] ) ) {
				continue;
			}
			$row = '- [' . $note['title'] . '](' . $note['url'] . ')';
			if ( ! empty( $note['summary'] ) ) {
				$row .= ': ' . $note['summary'];
			}
			$lines[] = $row;
		}
		$lines[] = '';
	}

	return implode( "\n", $lines ) . "\n";
}

/**
 * Collect recent published posts for /llms-full.txt. Not exercised by the
 * standalone fixture (which injects rows) — it needs a live WP_Query.
 *
 * @param int $limit Max posts.
 * @return array<int,array{title:string,url:string,summary:string}>
 */
function sn_llms_txt_recent_notes( $limit = 40 ) {
	$notes = array();
	if ( ! class_exists( 'WP_Query' ) ) {
		return $notes;
	}
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			// v10.51.1 (hardening gate): a password-protected post IS
			// status=publish, and the no-excerpt branch below trims raw
			// post_content — bypassing the post_password_required() guard core
			// applies inside get_the_excerpt(). /llms-full.txt is read by LLM
			// crawlers, so ingestion is irreversible. Matches feed-json's gate.
			'has_password'        => false,
			'posts_per_page'      => (int) $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		)
	);
	foreach ( $query->posts as $post ) {
		// Defense in depth: the query gate above is the fix; this is the belt
		// (feed-json carries the same pair).
		if ( '' !== (string) ( $post->post_password ?? '' ) ) {
			continue;
		}
		$summary = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 28, '…' );
		$notes[] = array(
			'title'   => wp_strip_all_tags( get_the_title( $post ) ),
			'url'     => get_permalink( $post ),
			'summary' => trim( (string) preg_replace( '/\s+/', ' ', (string) $summary ) ),
		);
	}
	wp_reset_postdata();
	return $notes;
}

/**
 * Emit the 200 status + text/plain header + body. Split from the handler so it
 * is testable without exit(). status_header( 200 ) is REQUIRED — a postless
 * virtual path is already a 404 by template_redirect (WORDPRESS-REFERENCE #40).
 *
 * @param bool $full Serve the full corpus variant.
 */
function sn_llms_txt_send( $full = false ) {
	if ( function_exists( 'status_header' ) ) {
		status_header( 200 );
	}
	header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
	$notes = $full ? sn_llms_txt_recent_notes() : array();
	// v10.49.0: the curated pillar essays ride both variants. Memoized +
	// hardened derivation (inc/abilities-helpers.php, v10.48.0) — safe to
	// call per request; degrades to array() (section omitted) standalone.
	$pillars = function_exists( 'sn_theme_pillar_descriptors' ) ? (array) sn_theme_pillar_descriptors() : array();
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text markdown from home_url() + published post titles/permalinks + the curated pillar descriptors; esc_html would corrupt the "&" and markdown punctuation in a text/plain document.
	echo sn_llms_txt_body( $full, $notes, $pillars );
}

/**
 * template_redirect handler: serve the requested llms file, then exit.
 */
function sn_llms_txt_maybe_serve() {
	$req     = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$variant = sn_llms_txt_variant( $req );
	if ( '' === $variant ) {
		return;
	}
	sn_llms_txt_send( 'full' === $variant );
	exit;
}

if ( ! defined( 'SN_LLMS_TXT_TEST' ) || ! SN_LLMS_TXT_TEST ) {
	add_action( 'template_redirect', 'sn_llms_txt_maybe_serve', 0 );
}
