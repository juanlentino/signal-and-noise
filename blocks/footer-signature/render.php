<?php
/**
 * Footer signature: the JL stamp beside the wordmark and descriptor.
 *
 * CONVENTION, NOT DECORATION. The header carries the monogram alone (the
 * name is in the title, the H1 and this footer); the full lockup lives where
 * a site signs itself, once, in the footer. docs/BRAND.md: the stamp is the
 * container for "attribution, rights notices, credits"; the wordmark is Bebas
 * Neue at letter-spacing `wide`, the descriptor DM Mono 500 at `ultra`.
 *
 * Inline SVG in currentColor, the stamp's canonical geometry (200 tile, rect
 * 6/6/188 stroke 12, mark at translate(45 51.8) scale(0.753) stroke 20). The
 * whole thing is a home link with one accessible name; the SVG is decorative
 * to assistive tech because the name is right beside it as text.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo '<a href="' . esc_url( home_url( '/' ) ) . '" class="sn-signature" aria-label="' . esc_attr__( 'Juan Lentino, home', 'signal-and-noise' ) . '">'
	. '<svg class="sn-signature__stamp" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" fill="none" stroke="currentColor" aria-hidden="true" focusable="false">'
	. '<rect x="6" y="6" width="188" height="188" stroke-width="12"/>'
	. '<g transform="translate(45 51.8) scale(0.753)" stroke-width="20"><path d="M54 10V96A22 22 0 0 1 10 96"/><path d="M94 10V118H136"/></g>'
	. '</svg>'
	. '<span class="sn-signature__text" aria-hidden="true">'
	. '<span class="sn-signature__name">' . esc_html__( 'Juan Lentino', 'signal-and-noise' ) . '</span>'
	. '<span class="sn-signature__role">' . esc_html__( 'Producer / Mix engineer / Strategist', 'signal-and-noise' ) . '</span>'
	. '</span>'
	. '</a>' . "\n";
