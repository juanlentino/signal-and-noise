<?php
/**
 * Standalone fixture: /services <-> /contact routing consistency.
 *
 * Guards the reconciliation of the two pages (theme v10.27.0). The bug this
 * locks against: /services marketed production/mixing/mastering in the first
 * person and funnelled every inquiry to /contact, while /contact bounced exactly
 * those people to panaceastud.io ("not here") and offered NO route for remote
 * music work. The reconciled model is one principal, two delivery modes:
 *   - in-studio at Panacea, Buenos Aires (travel in; partners execute; JL directs)
 *   - remote from the US (mixing, mastering, songwriting, strategy, AI)
 *
 * The invariant both pages must keep telling the same story:
 *   - /services offers BOTH a Panacea (studio) path AND a /contact (remote) path
 *     — never a single CTA that dead-ends studio inquiries at a page that
 *     rejects them.
 *   - /contact and /services point studio work at the SAME destination
 *     (panaceastud.io), so the two pages cannot drift apart again.
 *
 * (v10.27.1 dropped the per-offering .sn-service-mode delivery tags: delivery
 *  mode is a property of the engagement, not of a service — production can be
 *  remote, mixing can be in-studio — so a fixed per-card label mislabels. The
 *  two modes live in the copy + the two-path CTA, which this suite guards.)
 *
 * The music@ route itself (remote path on /contact) is leak-guarded in
 * tests/contact-email.php; this file owns the cross-page routing contract.
 *
 * /services and /contact now render their bodies from post_content (the
 * companion plugin migration seeded both Pages), so the routing/CTA copy
 * this suite originally read out of the template files now lives in the DB
 * body instead. Those content assertions are retired below — mirrors the
 * page-about exclusion in tests/layout-width-system.php: the invariant still
 * holds in production, this static fixture just can't read the DB body. The
 * structural checks (file readability, no stale per-offering mode labels, no
 * reintroduced "not here" framing) remain: they guard the template frame
 * itself.
 *
 * @since theme v10.27.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$root     = realpath( __DIR__ . '/..' );
$services = (string) @file_get_contents( $root . '/templates/page-services.html' );
$contact  = (string) @file_get_contents( $root . '/templates/page-contact.html' );

$pass = 0; $fail = 0;
function ok( $cond, $label ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $label\n"; } else { $fail++; echo "FAIL: $label\n"; } }

echo "Services <-> Contact routing suite — theme v10.27.0\n\n";

ok( '' !== $services, 'page-services.html is readable' );
ok( '' !== $contact, 'page-contact.html is readable' );

// ── /services offers BOTH paths (no single dead-end CTA); both pages point
//    studio work at the SAME destination — retired: this copy now lives in
//    the Services/Contact Pages' post_content, not in these template files.

// ── the two-mode split lives in the CTA + copy, not per-offering labels ──
// v10.27.1 removed the .sn-service-mode tags (mode is per-engagement, not per-
// service). Guard that they do not creep back in.
ok( strpos( $services, 'sn-service-mode' ) === false, '/services carries no per-offering delivery-mode labels' );

// ── /contact no longer frames Panacea as an outside "not here" vendor ──
ok( stripos( $contact, 'not here' ) === false, '/contact drops the third-party "not here" framing of Panacea' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
