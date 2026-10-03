<?php
/**
 * Standalone test: nothing foreign inside the signed content (15.2.2).
 * Run: php tests/signed-content-guard.php
 *
 * @package SignalNoise
 */

namespace MailPoet\Form {
	class DisplayFormInWPContent {
		public function contentDisplay( $c = null ) { return $c . '<div id="mp_form_slide_in1"></div>'; }
		public function maybeRenderFormsInFooter() {}
	}
}

namespace {
	if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
	define( 'ABSPATH', '/' );
	$pass = 0; $fail = 0;
	function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

	$GLOBALS['__actions'] = array(); $GLOBALS['__singular'] = true;
	function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['__actions'][] = array( $h, $cb, $p ); }
	function apply_filters( $h, $v ) { return $v; }
	$GLOBALS['__type'] = 'post'; $GLOBALS['__uid'] = '';
	function is_singular( $types = '' ) { return $GLOBALS['__singular'] && $types === $GLOBALS['__type']; }
	function get_queried_object_id() { return 7; }
	function get_post_meta( $id, $key, $single = false ) { return '_sn_prov_uid' === $key ? $GLOBALS['__uid'] : ''; }
	function remove_filter( $h, $fn, $p = 10 ) {
		foreach ( $GLOBALS['wp_filter'][ $h ]->callbacks[ $p ] ?? array() as $id => $e ) {
			if ( $e['function'] === $fn ) { unset( $GLOBALS['wp_filter'][ $h ]->callbacks[ $p ][ $id ] ); return true; }
		}
		return false;
	}
	function add_filter( $h, $fn, $p = 10, $a = 1 ) { $GLOBALS['wp_filter'][ $h ]->callbacks[ $p ][ 'k' . count( $GLOBALS['wp_filter'][ $h ]->callbacks[ $p ] ?? array() ) ] = array( 'function' => $fn, 'accepted_args' => $a ); return true; }

	require __DIR__ . '/../inc/signed-content-guard.php';

	$mp     = new \MailPoet\Form\DisplayFormInWPContent();
	$filled = static function () use ( $mp ) {
		return (object) array( 'callbacks' => array(
			9  => array( 'a' => array( 'function' => 'do_blocks', 'accepted_args' => 1 ) ),
			10 => array(
				'b' => array( 'function' => 'wpautop', 'accepted_args' => 1 ),
				'c' => array( 'function' => array( $mp, 'contentDisplay' ), 'accepted_args' => 1 ),
				'd' => array( 'function' => array( $mp, 'maybeRenderFormsInFooter' ), 'accepted_args' => 1 ),
				'e' => array( 'function' => static fn( $c ) => $c, 'accepted_args' => 1 ),
			),
		) );
	};

	echo "The finder\n";
	$found = sn_signed_content_find_appenders( (array) $filled()->callbacks, SN_SIGNED_CONTENT_APPENDERS );
	ok( 1 === count( $found ) && 10 === $found[0]['priority'] && array( $mp, 'contentDisplay' ) === $found[0]['function'], 'MailPoet\'s the_content callback is found by class and method; core callbacks, a closure and its other method are not' );
	ok( array() === sn_signed_content_find_appenders( array(), SN_SIGNED_CONTENT_APPENDERS ) && array() === sn_signed_content_find_appenders( (array) $filled()->callbacks, array() ), 'no callbacks, or nothing known: nothing found' );

	echo "\nOn a signed page\n";
	$GLOBALS['wp_filter'] = array( 'the_content' => $filled() );
	sn_signed_content_detach();
	$left = array_column( $GLOBALS['wp_filter']['the_content']->callbacks[10], 'function' );
	ok( ! in_array( array( $mp, 'contentDisplay' ), $left, true ) && in_array( 'wpautop', $left, true ) && 3 === count( $left ), 'before the page renders the appender is off the_content and nothing else is touched' );
	sn_signed_content_reattach();
	$back = array_column( $GLOBALS['wp_filter']['the_content']->callbacks[10], 'function' );
	ok( in_array( array( $mp, 'contentDisplay' ), $back, true ) && array() === $GLOBALS['sn_signed_content_detached'], 'before the footer hooks run it is registered again, which is what MailPoet\'s own footer path checks for' );

	echo "\nElsewhere\n";
	$GLOBALS['__singular'] = false; $GLOBALS['wp_filter'] = array( 'the_content' => $filled() );
	sn_signed_content_detach();
	ok( in_array( array( $mp, 'contentDisplay' ), array_column( $GLOBALS['wp_filter']['the_content']->callbacks[10], 'function' ), true ), 'an archive or the home page is not signed content: left alone' );

	$GLOBALS['__singular'] = true; $GLOBALS['__type'] = 'page'; $GLOBALS['__uid'] = ''; $GLOBALS['wp_filter'] = array( 'the_content' => $filled() );
	sn_signed_content_detach();
	ok( in_array( array( $mp, 'contentDisplay' ), array_column( $GLOBALS['wp_filter']['the_content']->callbacks[10], 'function' ), true ), 'an ordinary page carries no provenance UID: a below-the-page form keeps showing there' );
	$GLOBALS['__uid'] = '9d49e140-10a2-4c62-943e-e98270fe09d2'; $GLOBALS['wp_filter'] = array( 'the_content' => $filled() );
	sn_signed_content_detach();
	ok( ! in_array( array( $mp, 'contentDisplay' ), array_column( $GLOBALS['wp_filter']['the_content']->callbacks[10], 'function' ), true ), 'a page that opted into signing is protected like a note' );
	sn_signed_content_reattach();

	echo "\nWiring\n";
	ok( in_array( array( 'template_redirect', 'sn_signed_content_detach', 1 ), $GLOBALS['__actions'], true ) && in_array( array( 'wp_footer', 'sn_signed_content_reattach', 1 ), $GLOBALS['__actions'], true ), 'off at template_redirect (the block template renders after it), back at wp_footer priority 1 (MailPoet\'s footer hook is at 10)' );
	ok( false !== strpos( (string) file_get_contents( __DIR__ . '/../functions.php' ), "/inc/signed-content-guard.php'" ), 'functions.php loads the file' );

	echo "\nResult: $pass passed, $fail failed.\n";
	exit( $fail ? 1 : 0 );
}
