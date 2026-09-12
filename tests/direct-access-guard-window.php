<?php
/**
 * Every PHP file the theme ships guards direct access, within the first fifty
 * lines.
 *
 * A theme file requested directly runs without WordPress loaded: a call into
 * a core function is a fatal, and the server answers 500 with whatever the
 * error handler prints instead of exiting silently. `if ( ! defined(
 * 'ABSPATH' ) ) { exit; }` is the guard. The window mirrors Plugin Check's
 * Direct_File_Access_Check regex fallback, which reads only the first fifty
 * lines; a guard pushed past that by a longer header docblock reads as
 * missing to the checker.
 *
 * inc/reading-path-slot.php shipped without the guard and answered 500 to a
 * direct request (#336). The sweep reads every PHP file under inc/, blocks/
 * and patterns/ and fails on a missing OR late guard, so the omission is found
 * here rather than on the server.
 *
 * Run: php tests/direct-access-guard-window.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$pass = 0;
$fail = 0;
function ok( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "PASS: $msg\n";
	} else {
		$fail++;
		echo "FAIL: $msg\n";
	}
}

/** Plugin Check's window, from Direct_File_Access_Check::has_direct_access_protection_regex(). */
const PLUGIN_CHECK_GUARD_WINDOW = 50;

/**
 * The line (1-based) of the guard, or 0. Both shapes Plugin Check accepts:
 * `if ( ! defined( 'ABSPATH' ) … )` (a test-aware `&&` clause may follow)
 * and `defined( 'ABSPATH' ) || exit;`.
 *
 * @param string $contents File contents.
 * @return int
 */
function guard_line( $contents ) {
	foreach ( explode( "\n", $contents ) as $i => $line ) {
		if ( preg_match( "/if\\s*\\(\\s*!\\s*defined\\s*\\(\\s*['\"]ABSPATH['\"]\\s*\\)/", $line )
			|| preg_match( "/defined\\s*\\(\\s*['\"]ABSPATH['\"]\\s*\\)\\s*\\|\\|\\s*exit/", $line ) ) {
			return $i + 1;
		}
	}
	return 0;
}

/**
 * Does the guard exist, and does Plugin Check's regex window see it?
 *
 * @param string $contents File contents.
 * @return bool
 */
function guard_within_window( $contents ) {
	$line = guard_line( $contents );
	return $line > 0 && $line <= PLUGIN_CHECK_GUARD_WINDOW;
}

echo "direct-access-guard-window\n\nGroup 1: the rule itself, on synthetic files\n";
$short = "<?php\n/**\n * Header.\n */\n\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n";
$long  = "<?php\n/**\n" . str_repeat( " * a line of header prose\n", 55 ) . " */\n\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n";
$none  = "<?php\n/**\n * Header.\n */\n\nfunction f() {}\n";
ok( 6 === guard_line( $short ) && guard_within_window( $short ), 'a guard on line 6 is inside the window' );
ok( 60 === guard_line( $long ) && ! guard_within_window( $long ), 'a guard on line 60 is outside it' );
ok( 0 === guard_line( $none ) && ! guard_within_window( $none ), 'no guard at all fails -- the #336 shape' );
ok( 2 === guard_line( "<?php\ndefined( 'ABSPATH' ) || exit;\n" ), 'the one-line `defined() || exit;` form counts' );
ok( 2 === guard_line( "<?php\nif ( ! defined( 'ABSPATH' ) && ! defined( 'SN_X_TEST' ) ) {\n\texit;\n}\n" ), 'the test-aware `&&` form counts' );

echo "\nGroup 2: every PHP file under inc/, blocks/ and patterns/\n";
$root  = dirname( __DIR__ );
$files = array();
foreach ( array( 'inc', 'blocks', 'patterns' ) as $dir ) {
	if ( ! is_dir( $root . '/' . $dir ) ) {
		continue;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = (string) $file;
		// Another session's worktree under .claude/ is not this tree; the path is
		// tested relative to $root because this tree may itself sit under one.
		if ( 'php' !== pathinfo( $path, PATHINFO_EXTENSION ) || false !== strpos( substr( $path, strlen( $root ) ), '/.claude/' ) ) {
			continue;
		}
		$files[] = $path;
	}
}
sort( $files );
$bad = array();
foreach ( $files as $path ) {
	$contents = (string) file_get_contents( $path );
	if ( ! guard_within_window( $contents ) ) {
		$bad[] = substr( $path, strlen( $root ) + 1 ) . ':' . guard_line( $contents );
	}
}
ok( count( $files ) > 50, sprintf( '%d PHP files are swept (a sweep that found none would pass vacuously)', count( $files ) ) );
ok( array() === $bad, 'every one of them guards direct access within the first fifty lines' . ( $bad ? ' (missing=0 or late: ' . implode( ', ', $bad ) . ')' : '' ) );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
