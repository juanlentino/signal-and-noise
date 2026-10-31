<?php
/**
 * Abilities API: the get-design-system-summary callback.
 *
 * Moved verbatim out of inc/abilities-diagnostics.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Execute callback: signal-and-noise/get-design-system-summary.
 *
 * @since 9.1.0
 */
function sn_theme_ability_design_system_summary( $input = array() ) {
	try {
		$format = isset( $input['format'] ) ? (string) $input['format'] : 'markdown';
		if ( ! in_array( $format, array( 'markdown', 'compact-text', 'json' ), true ) ) {
			$format = 'markdown';
		}

		$tokens = sn_theme_ability_design_tokens();
		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}

		// Never format a plausible-looking empty document. A hollow read (no
		// colors, no named font family/size) is a failure of the read, not an
		// empty design system — surface it as the SAME design_tokens_empty
		// error sn_theme_ability_design_tokens() itself guards against, so
		// this holds even if that guard is ever bypassed or regresses.
		if ( ! sn_theme_design_tokens_has_content( $tokens ) ) {
			return new WP_Error(
				'design_tokens_empty',
				'Cannot summarize: the design-tokens read came back empty (no colors, font families, or font sizes). Refusing to format a plausible-looking empty summary.',
				array( 'status' => 500 )
			);
		}

		$summary = '';
		switch ( $format ) {
			case 'compact-text':
				// EVERY palette, labelled with its id and scheme. A model told
				// only one will happily approve a colour that is unreadable in
				// another — which is the whole reason this field changed shape.
				$color_pairs = array();
				foreach ( (array) ( $tokens['colors']['palettes'] ?? array() ) as $id => $meta ) {
					if ( empty( $meta['colors'] ) ) {
						continue;
					}
					$pairs = array();
					foreach ( (array) $meta['colors'] as $slug => $hex ) {
						$pairs[] = $slug . $hex;
					}
					$mark = ( isset( $tokens['colors']['served'] ) && $id === $tokens['colors']['served'] ) ? '*' : '';
					$color_pairs[] = $id . $mark . ':' . ( $meta['scheme'] ?? '?' ) . '(' . implode( ',', $pairs ) . ')';
				}
				$font_slugs = array();
				foreach ( (array) $tokens['typography']['fontFamilies'] as $ff ) {
					if ( isset( $ff['slug'] ) ) { $font_slugs[] = (string) $ff['slug']; }
				}
				$size_slugs = array();
				foreach ( (array) $tokens['typography']['fontSizes'] as $fs ) {
					if ( isset( $fs['slug'] ) ) { $size_slugs[] = (string) $fs['slug']; }
				}
				$summary = sprintf(
					'colors:%s; fonts:%s; sizes:%s',
					implode( ',', $color_pairs ),
					implode( ',', $font_slugs ),
					implode( ',', $size_slugs )
				);
				break;

			case 'json':
				$summary = (string) wp_json_encode( $tokens );
				break;

			case 'markdown':
			default:
				$lines = array();
				$lines[] = '# Signal & Noise design system';
				$lines[] = '';
				$lines[] = '## Colors';
				$served = isset( $tokens['colors']['served'] ) ? (string) $tokens['colors']['served'] : '';
				$lines[] = '';
				$lines[] = 'This site presents more than one palette. A colour choice must hold in ALL of them.';
				if ( '' !== $served ) {
					$lines[] = '';
					$lines[] = 'custom' === $served
						? 'Currently serving: a CUSTOM palette edited in the Site Editor — not any shipped below.'
						: "Currently serving: `$served`.";
				}
				foreach ( (array) ( $tokens['colors']['palettes'] ?? array() ) as $id => $meta ) {
					if ( empty( $meta['colors'] ) ) {
						continue;
					}
					$scheme = isset( $meta['scheme'] ) ? (string) $meta['scheme'] : '';
					$lines[] = '';
					$lines[] = "### `$id`" . ( '' !== $scheme ? " ($scheme)" : '' )
						. ( $id === $served ? ' — currently serving' : '' );
					foreach ( (array) $meta['colors'] as $slug => $hex ) {
						$lines[] = "- `$slug` — $hex";
					}
				}
				$lines[] = '';
				$lines[] = '## Typography';
				$lines[] = '';
				$lines[] = '### Font families';
				foreach ( (array) $tokens['typography']['fontFamilies'] as $ff ) {
					$slug = isset( $ff['slug'] ) ? (string) $ff['slug'] : '';
					$name = isset( $ff['name'] ) ? (string) $ff['name'] : '';
					$fam  = isset( $ff['fontFamily'] ) ? (string) $ff['fontFamily'] : '';
					$lines[] = "- `$slug` ($name) — $fam";
				}
				$lines[] = '';
				$lines[] = '### Font sizes';
				foreach ( (array) $tokens['typography']['fontSizes'] as $fs ) {
					$slug = isset( $fs['slug'] ) ? (string) $fs['slug'] : '';
					$size = isset( $fs['size'] ) ? (string) $fs['size'] : '';
					$lines[] = "- `$slug` — $size";
				}
				$lines[] = '';
				$lines[] = '## Spacing';
				if ( ! empty( $tokens['spacing']['spacingSizes'] ) ) {
					foreach ( (array) $tokens['spacing']['spacingSizes'] as $sp ) {
						$slug = isset( $sp['slug'] ) ? (string) $sp['slug'] : '';
						$size = isset( $sp['size'] ) ? (string) $sp['size'] : '';
						$lines[] = "- `$slug` — $size";
					}
				}
				$summary = implode( "\n", $lines );
				break;
		}

		// Chars/4 heuristic for token estimate (matches Anthropic's docs).
		$token_estimate = (int) ceil( strlen( $summary ) / 4 );

		return array(
			'format'         => $format,
			'summary'        => $summary,
			'token_estimate' => $token_estimate,
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in get-design-system-summary: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}
