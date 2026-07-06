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
 *   - /services signals delivery mode per offering (.sn-service-mode tags).
 *   - /contact and /services point studio work at the SAME destination
 *     (panaceastud.io), so the two pages cannot drift apart again.
 *
 * The music@ route itself (remote path on /contact) is leak-guarded in
 * tests/contact-email.php; this file owns the cross-page routing contract.
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

// ── /services offers BOTH paths (no single dead-end CTA) ──────────────
ok( strpos( $services, 'panaceastud.io' ) !== false, '/services offers the in-studio path (links to panaceastud.io)' );
ok( strpos( $services, 'href="/contact"' ) !== false, '/services offers the remote path (links to /contact)' );

// ── /services signals delivery mode per offering ──────────────────────
ok( strpos( $services, 'sn-service-mode' ) !== false, '/services tags offerings with a delivery mode (.sn-service-mode)' );

// ── both pages point studio work at the SAME destination ──────────────
ok( strpos( $contact, 'panaceastud.io' ) !== false, '/contact hands studio work to panaceastud.io' );
ok(
	strpos( $services, 'panaceastud.io' ) !== false && strpos( $contact, 'panaceastud.io' ) !== false,
	'both pages route in-studio work to the same place (panaceastud.io)'
);

// ── /contact no longer frames Panacea as an outside "not here" vendor ──
ok( stripos( $contact, 'not here' ) === false, '/contact drops the third-party "not here" framing of Panacea' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
