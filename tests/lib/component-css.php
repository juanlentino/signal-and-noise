<?php
/**
 * The component CSS layer, as one string, for suites that pin a rule's EXISTENCE
 * rather than its address.
 *
 * WHY. `assets/css/components.css` is being split block by block into
 * `assets/css/blocks/*.css`, delivered through `wp_enqueue_block_style()` (see
 * inc/block-styles-enqueue.php). Every such move relocates rules without
 * changing them, and every suite that did
 * `file_get_contents( 'assets/css/components.css' )` went red on the first
 * move even though nothing it asserts about actually changed. That is the
 * source-text-pin trap: the pin was on the address, not on the behaviour.
 *
 * Suites that care WHERE a rule lives (tests/block-style-related-notes.php
 * asserts the rules left components.css) must keep reading the individual
 * files. Suites that care THAT a rule exists should read this instead.
 *
 * EXPLICIT LIST, NEVER A GLOB. A glob would silently absorb a file that was
 * meant to be somewhere else, which is precisely the failure the split exists
 * to prevent, and it would make "the rules left components.css" untestable.
 * Each migration adds one line here.
 *
 * @package SignalNoise
 */

/**
 * Concatenated source of the component-layer stylesheets.
 *
 * @return string
 */
function snt_component_css() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$root  = dirname( __DIR__, 2 );
	$files = array(
		'assets/css/components.css',
		'assets/css/blocks/related-notes.css',
	);

	$out = '';
	foreach ( $files as $rel ) {
		$path = $root . '/' . $rel;
		if ( ! is_file( $path ) ) {
			// Loud, not silent: a missing file here would make every existence
			// pin below it pass vacuously.
			echo "FAIL: tests/lib/component-css.php lists $rel, which does not exist\n";
			continue;
		}
		$out .= (string) file_get_contents( $path ) . "\n";
	}

	$cache = $out;
	return $out;
}
