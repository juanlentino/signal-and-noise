<?php
/**
 * Tests: the version header on main must have a matching tag ON main.
 *
 * THE GAP THIS CLOSES. On 2026-08-20 an ancestry sweep of this repo's 40 most
 * recent tags found THREE — v12.0.2, v12.0.3 and v12.0.4 — pointing at their
 * PRE-SQUASH branch commits rather than at main. That is the linked-worktree
 * trap: a tag cut from `.claude/worktrees/<name>/` lands on the branch tip, and
 * the log SUBJECT matches the squash commit exactly, so it reads as correct in
 * `git tag -l` and in any log comparison.
 *
 * It matters here more than in the sibling plugin repo: `deploy.yml` is
 * dispatched ON A TAG and checks that ref out, so the tag is literally what
 * ships. In this instance the trees were byte-identical and nothing wrong
 * reached the site — luck, not design. A branch tip that diverged from its
 * squash result would have deployed code that was never on main.
 *
 * v12.0.3's commit was reachable ONLY through its tag by the time this was
 * found: its branch was already deleted.
 *
 * WHY THE VERDICT IS A PURE FUNCTION. The real check needs `git` and a network
 * fetch, neither of which belongs in the standalone sweep. sn_vtp_verdict()
 * takes the two lists as ARGUMENTS, so this suite drives the real decision
 * logic on synthetic input — including the cases that are hard to manufacture
 * in a live repo — while tools/version-tag-parity.php supplies the real lists.
 *
 * WHAT THIS CANNOT SEE: whether the tag's COMMIT is the one that carries that
 * version header (a tag moved by hand onto an unrelated commit passes), and
 * whether the draft release exists — that is step 3, reported by the workflow
 * as a note rather than gated here.
 *
 * @since 2026-08-20 (CI tooling; ships nothing)
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

require_once dirname( __DIR__ ) . '/tools/version-tag-parity.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }
echo "version header <-> tag parity\n\n";

// The healthy case.
$v = sn_vtp_verdict( '12.4.0', array( 'v12.3.0', 'v12.4.0' ), array( 'v12.3.0', 'v12.4.0' ) );
ok( true === $v['ok'], 'a version with a tag ON main is OK' );
ok( 'tagged' === $v['code'], 'and reports code `tagged`' );

// The gap that prompted this: merged, header bumped, never tagged.
$v = sn_vtp_verdict( '12.4.0', array( 'v12.3.0' ), array( 'v12.3.0' ) );
ok( false === $v['ok'], 'THE REPORTED GAP: a bumped header with no tag anywhere FAILS' );
ok( 'missing-tag' === $v['code'], 'and reports code `missing-tag`' );
ok( false !== strpos( $v['message'], '12.4.0' ), 'the message names the version, so the failure row is actionable without opening the run' );

// The documented linked-worktree trap: the tag exists but sits off main.
$v = sn_vtp_verdict( '12.4.0', array( 'v12.3.0' ), array( 'v12.3.0', 'v12.4.0' ) );
ok( false === $v['ok'], 'a tag that EXISTS but is not an ancestor of main FAILS' );
ok( 'tag-off-main' === $v['code'], 'and is distinguished from a missing tag — the fix differs (a tag cut in a linked worktree lands on the pre-squash commit)' );

// An unreadable header must never read as healthy.
foreach ( array( '', '   ', 'not-a-version' ) as $bad ) {
	$v = sn_vtp_verdict( $bad, array( 'v12.4.0' ), array( 'v12.4.0' ) );
	ok( false === $v['ok'] && 'no-version' === $v['code'], 'an unreadable version header (' . var_export( $bad, true ) . ') FAILS rather than passing vacuously' );
}

// Whitespace and a leading `v` in the header must not change the verdict.
ok( true === sn_vtp_verdict( ' 12.4.0 ', array( 'v12.4.0' ), array( 'v12.4.0' ) )['ok'], 'a padded version string is trimmed, not failed' );
ok( true === sn_vtp_verdict( 'v12.4.0', array( 'v12.4.0' ), array( 'v12.4.0' ) )['ok'], 'a header that already carries the `v` prefix is not double-prefixed' );

// A four-part or pre-release version is still a version.
ok( true === sn_vtp_verdict( '12.4.0.1', array( 'v12.4.0.1' ), array( 'v12.4.0.1' ) )['ok'], 'a four-part version is accepted (the gate is parity, not a SemVer opinion)' );

// The header reader, driven against the REAL plugin file — not a fixture, so a
// header format change is caught here rather than at 03:00 by the cron.
$real = sn_vtp_read_header( dirname( __DIR__ ) . '/style.css' );
ok( 1 === preg_match( '/^\d+\.\d+\.\d+/', $real ), "the real theme header parses to a version ($real)" );
ok( '' === sn_vtp_read_header( dirname( __DIR__ ) . '/does-not-exist.php' ), 'a missing file yields an EMPTY version, which the verdict above treats as a failure' );
ok( '' === sn_vtp_read_header( dirname( __DIR__ ) . '/README.md' ), 'and a file with no Version: header yields empty too, rather than a stray match' );
// The theme header carries no leading `*` (it is a CSS comment, not a PHP
// docblock) while the sibling plugin's does. One reader serves both; assert
// BOTH shapes here so a future tightening cannot silently break the other repo.
$tmp = sys_get_temp_dir() . '/sn-vtp-docblock-probe.php';
file_put_contents( $tmp, "<?php\n/**\n * Version:     9.9.9\n */\n" );
ok( '9.9.9' === sn_vtp_read_header( $tmp ), 'the PHP-docblock header shape (` * Version:`) still parses — the sibling plugin uses it' );
unlink( $tmp );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
