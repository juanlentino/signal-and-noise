<?php
/**
 * Tests: cut-release.sh diagnoses an archive with no '## [' heading instead of
 * exiting silently (theme issue #327).
 *
 * tools/cut-release.sh:168 does
 *   first_heading_line="$(grep -n -m1 -E '^## \[' "$ARCHIVE" | cut -d: -f1)"
 * under `set -euo pipefail`. When the archive has no such heading, grep exits
 * 1; pipefail makes the whole pipeline exit 1; set -e kills the script inside
 * the assignment, before the `die` on the next line ever runs — and only
 * after step 1 already rewrote style.css and readme.txt. The fix adds
 * `|| true` to the pipeline and moves the archive check above step 1, so a
 * bad archive is diagnosed before anything is mutated.
 *
 * Run: php tests/cut-release-empty-archive.php
 * @since 12.19.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$root = dirname( __DIR__ );

/** Build a throwaway git repo with everything cut-release.sh needs, an
 * archive that has NO '## [' heading, and a CHANGELOG with a previous cut
 * to trigger the archive-transfer branch. Returns the temp dir path. */
function make_fixture() {
	$dir = sys_get_temp_dir() . '/cut-release-test-' . uniqid();
	mkdir( $dir . '/tools/lib', 0777, true );
	mkdir( $dir . '/docs/changelog', 0777, true );

	copy( dirname( __DIR__ ) . '/tools/cut-release.sh', $dir . '/tools/cut-release.sh' );
	chmod( $dir . '/tools/cut-release.sh', 0755 );
	copy( dirname( __DIR__ ) . '/tools/lib/consolidate-changelog-sections.awk', $dir . '/tools/lib/consolidate-changelog-sections.awk' );

	file_put_contents( $dir . '/style.css', "/*\nVersion: 1.2.3\n*/\n" );
	file_put_contents( $dir . '/readme.txt', "Stable tag: 1.2.3\n" );
	file_put_contents( $dir . '/CHANGELOG.md', "## [Unreleased]\n- a new bullet\n\n## [1.2.2] - 2026-01-01 — previous headline\n- old bullet\n" );
	// No '## [' heading anywhere in the archive — just a preamble.
	file_put_contents( $dir . '/docs/changelog/archive.md', "Archive of older releases.\n" );

	$cmds = array(
		'git init -q',
		'git config user.email test@example.com',
		'git config user.name Test',
		'git add -A',
		'git commit -q -m init',
	);
	foreach ( $cmds as $cmd ) {
		exec( 'cd ' . escapeshellarg( $dir ) . ' && ' . $cmd . ' 2>&1', $out, $code );
		if ( 0 !== $code ) { return null; }
	}
	return $dir;
}

function run_cut_release( $dir ) {
	$cmd = 'cd ' . escapeshellarg( $dir ) . ' && bash tools/cut-release.sh fix "test headline" 2>&1';
	exec( $cmd, $out, $code );
	return array( 'code' => $code, 'output' => implode( "\n", $out ) );
}

function rrmdir( $dir ) {
	if ( ! is_dir( $dir ) ) { return; }
	foreach ( scandir( $dir ) as $f ) {
		if ( '.' === $f || '..' === $f ) { continue; }
		$p = $dir . '/' . $f;
		is_dir( $p ) ? rrmdir( $p ) : unlink( $p );
	}
	rmdir( $dir );
}

echo "cut-release-empty-archive — theme #327\n\n";

$dir = make_fixture();
ok( null !== $dir, 'fixture git repo builds cleanly' );

if ( null !== $dir ) {
	$result = run_cut_release( $dir );

	ok( 0 !== $result['code'], 'the script exits non-zero on a headingless archive' );
	ok( false !== strpos( $result['output'], "no '## [' heading found" ),
		"the script prints the die() diagnostic, not a silent exit — got: {$result['output']}" );

	$style = (string) file_get_contents( $dir . '/style.css' );
	ok( false !== strpos( $style, 'Version: 1.2.3' ) && false === strpos( $style, 'Version: 1.2.4' ),
		'style.css is untouched — the archive check ran BEFORE step 1 mutated it' );

	rrmdir( $dir );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
