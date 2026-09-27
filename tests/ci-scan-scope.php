<?php
/**
 * .github/scripts/scan-scope.sh: when the Claude security scan may be skipped.
 * Only docs/Markdown, or a release cut (CHANGELOG plus style.css `Version:` and
 * readme.txt `Stable tag:` and nothing else). Every doubt scans.
 * Run: php tests/ci-scan-scope.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { echo "PASS: $m\n"; $pass++; } else { echo "FAIL: $m\n"; $fail++; } }

function scope( array $rows ) {
	$in  = implode( "\n", array_map( 'json_encode', $rows ) );
	$cmd = 'bash ' . escapeshellarg( __DIR__ . '/../.github/scripts/scan-scope.sh' );
	$p   = proc_open( $cmd, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $io );
	fwrite( $io[0], $in );
	fclose( $io[0] );
	$out = trim( stream_get_contents( $io[1] ) );
	proc_close( $p );
	return $out;
}

if ( '' === trim( (string) shell_exec( 'command -v jq' ) ) ) {
	echo "SKIP: jq not installed\n";
	exit( 0 );
}

$style  = array( 'filename' => 'style.css', 'patch' => "@@ -1,6 +1,6 @@\n Theme Name: x\n-Version: 14.4.1\n+Version: 14.4.2\n" );
$readme = array( 'filename' => 'readme.txt', 'patch' => "@@\n-Stable tag: 14.4.1\n+Stable tag: 14.4.2\n" );
$log    = array( 'filename' => 'CHANGELOG.md', 'patch' => '+## [14.4.2]' );
$docs   = array( 'filename' => 'docs/changelog/archive.md', 'patch' => '+x' );

ok( 'true' === scope( array( $log, $docs, $readme, $style ) ), 'a release cut skips the scan' );
ok( 'true' === scope( array( $log, $docs ) ), 'docs only skips the scan' );
ok( 'false' === scope( array( $log, $style, array( 'filename' => 'inc/x.php', 'patch' => '+y' ) ) ), 'a code file riding a cut is scanned' );
ok( 'false' === scope( array( array( 'filename' => 'style.css', 'patch' => "-Version: 1.0.0\n+Version: 1.0.1\n+body{background:url(x)}" ) ) ), 'a style.css edit beside the Version: line is scanned' );
ok( 'false' === scope( array( array( 'filename' => 'readme.txt', 'patch' => "+Requires PHP: 7.0" ) ) ), 'a readme.txt edit that is not the Stable tag is scanned' );
ok( 'false' === scope( array( array( 'filename' => 'style.css' ) ) ), 'a style.css with no patch is scanned' );
ok( 'false' === scope( array() ), 'an empty file list is scanned' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
