# Signal & Noise — WP Standards & Security Audit

Generated: 2026-05-08
Auditor: Claude (R1 research pass)
Scope: every PHP file under `inc/` + `functions.php`
Standard: WordPress Coding Standards (latest), security best practices, theme review baseline

## Executive summary

Posture: solid. This is a careful, security-aware codebase: nonces, capability checks, sanitization, and `esc_*()` escaping are applied consistently across admin handlers, AJAX endpoints, and front-end output. There are no CRITICAL findings — no SQLi, no missing nonces on POST handlers, no missing capability checks on privileged actions, no hardcoded credentials. The HIGH findings are all narrow defense-in-depth gaps in admin-only paths (manage_options gated), not exploitable vulnerabilities for unauthenticated visitors. Counts: 0 CRITICAL, 2 HIGH, 9 MEDIUM, 11 LOW, 6 NIT. The single most useful improvement is wrapping the admin-page-callback in an explicit `current_user_can()` check — currently it's only protected by WP's menu-registration capability gate, which is sufficient but conventionally backed up.

## Findings by severity

### CRITICAL

None.

### HIGH

#### H1. Admin page callback lacks an explicit capability check

`inc/admin-page.php:42` — `sn_theme_options_page()` is registered via `add_theme_page( ..., 'manage_options', ..., 'sn_theme_options_page' )` and processes form submissions, but the callback itself never calls `current_user_can( 'manage_options' )`.

```php
function sn_theme_options_page() {
    $theme         = wp_get_theme( 'signal-and-noise' );
    $local_version = $theme->get( 'Version' );
    $notices       = array();
    ...
    if ( isset( $_POST['sn_action'] ) && check_admin_referer( 'sn_theme_options_nonce' ) ) {
        $action = sanitize_text_field( wp_unslash( $_POST['sn_action'] ) );
        if ( 'clear_overrides' === $action ) { ... }
```

Why it matters: WordPress `add_*_page()` enforces capability *for menu visibility and the page request itself* — i.e., `admin.php?page=sn-theme-options` is protected. So this is not exploitable in practice. But: (a) defense-in-depth is the WPCS convention for any admin handler that mutates state, and (b) if anyone ever calls `sn_theme_options_page()` from another context (e.g. an unguarded shortcode or an AJAX dispatcher), the form-handling block runs without re-checking. Cheap to fix.

Fix: add `if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'signal-noise' ) ); }` at the top of the function.

#### H2. `$installed_label` mixes raw `<span>` with `esc_html()` output and is echoed unescaped

`inc/admin-page.php:219-220`:

```php
$installed_label = esc_html( $local_version ) . ( $local_sha ? ' <span style="color:#666;">at ' . esc_html( $local_sha ) . '</span>' : '' );
echo '<tr>...<td style="padding:8px 0;"><code>' . $installed_label . '</code></td></tr>';
```

The string is constructed by concatenating an `esc_html`'d value with a static `<span>` literal and another `esc_html`'d value. Echoed directly. Both variables are `esc_html`'d at the point they enter the string, so this is *not* an XSS — the static `<span>` is hardcoded and the dynamic parts are escaped. But the pattern is fragile: a future edit that adds another dynamic field to the concatenation could easily forget to escape. WPCS would prefer:

```php
echo '<code>' . esc_html( $local_version ) . '</code>';
if ( $local_sha ) {
    echo ' <span style="color:#666;">at <code>' . esc_html( $local_sha ) . '</code></span>';
}
```

I.e., never build pre-escaped HTML strings and concatenate them — print escaped values inline. Same pattern recurs elsewhere in this file (lines 221, 226, 229, 237) but those are static label additions; this one is the only one with two dynamic fields concatenated. Classifying HIGH only because the pattern is the kind that produces real XSS the moment someone adds a non-escaped value to it; the current code is safe.

### MEDIUM

#### M1. `file_get_contents()` on `assets/css/critical.css` echoed directly into `<style>` block

`inc/assets-frontend.php:74-77`:

```php
echo '<style id="sn-critical-inline">' . "\n";
echo file_get_contents( $css_file );  // phpcs:ignore WordPress.WP.AlternativeFunctions
echo '</style>' . "\n";
```

The path is built from `get_theme_file_path()` and the file is shipped with the theme — not user-controlled. No injection vector. Flagging because: (a) `file_get_contents()` of a file inside `<style>` should still be safe-by-construction; the `phpcs:ignore` documents intent. The concern is that critical.css ships unmodified into `<head>` on every front-end pageload, so any future content that ends up in that file (e.g., a future module that programmatically writes to it) lands inside the document unsanitized. Document the contract explicitly or use `wp_get_inline_script_data` patterns. Not currently exploitable.

#### M2. `file_get_contents()` of seed-content HTML used as `post_content`

`inc/notes-and-provenance.php:201, 210, 219`:

```php
function sn_load_provenance_body() {
    $body_file = __DIR__ . '/seed-content/provenance-body.html';
    return file_exists( $body_file ) ? file_get_contents( $body_file ) : '';
}
```

These are then passed to `wp_insert_post( [ 'post_content' => ... ] )`. The path is fixed (no user input), so no path-traversal. WordPress will run KSES on `post_content` for non-`unfiltered_html` users. `wp_insert_post` is called from admin migrations under capability-gated entry points. Not exploitable. The MEDIUM rating is for: file I/O on every `admin_init` for migration checks. Migrations are gated by an option flag so the `file_get_contents()` only runs once, but the gate check (`get_option`) runs every admin_init. Performance, not security.

#### M3. Unsanitized concatenation of `home_url()` derivations into Cloudflare API request body

`inc/cloudflare-purge.php:195-211`. URLs built from `get_permalink()`, `home_url()`, then sent via `wp_remote_post` body. The Cloudflare API parses `files`/`purge_everything` arguments server-side and can't be hijacked via this request body for purposes other than purging — and the values are all WP-internal. Safe; flagging only because the URLs end up in `wp_json_encode`'d body, which is the right way to do it but is worth a one-line comment confirming intent.

#### M4. Plausible Stats API URL recorded into transient may include `site_id` (domain) — fine, but worth a comment

`inc/plausible-api.php:127-132, 162`: `sn_plausible_record_error()` writes the full `$url` (including query string with `site_id=$domain`) into a transient. Token is sent only via `Authorization` header — confirmed clean. Domain is non-secret. No issue; the existing comment block covers this. Including this as MEDIUM only because the audit is supposed to flag any token/credential paths through transients/logs — verified clean. **No action needed.**

#### M5. `_wpnonce` hidden on the option form means manual `?_wpnonce=...` URL crafting can submit `cf_save` via GET

`inc/cloudflare-purge.php:253-289` and `inc/plausible-admin.php:37-77` — `check_admin_referer( 'sn_theme_options_nonce', '_wpnonce', false )`. The third argument `false` means "do not die on failure" — instead the function returns `false`. The code then short-circuits the action. Combined with `isset( $_POST['sn_action'] )` and the `&&` chain, this is correct. Worth noting that the form uses `method="post"` so `$_POST` only fires on POST — GET requests can't trigger the handler regardless. Safe; flagging because using `check_admin_referer( ..., ..., false )` and then re-checking method-via-superglobal is non-idiomatic WPCS. Idiomatic form is to check `'POST' === $_SERVER['REQUEST_METHOD']` first, then the nonce, then the action. **Style issue, not a vulnerability.**

#### M6. Missing capability check on `sn_admin_plausible_tab` AJAX-style handler before it processes `pl_test`

`inc/plausible-admin.php:31-32, 59-77`. The capability check is at the top (line 32). Good. The `pl_test` action then calls `sn_plausible_api()` which makes a real HTTP request to Plausible. Flagging because: a network call (synchronous, 6s timeout) can be triggered by an admin via a single nonce-protected POST. This is intended behaviour ("Test Connection" button), so not a finding per se — included for completeness. **No action needed.**

#### M7. `bool $strict` not passed to `base64_decode()` retry for whitespace-stripped value

`inc/template-self-heal.php:361`:

```php
$remote_content = base64_decode( preg_replace( '/\s+/', '', $meta['content'] ), true );
```

This is correct; the `true` second arg enables strict mode. No issue.

#### M8. `i18n` text domain `signal-noise` is used in one place but never registered via `load_theme_textdomain`

`inc/page-notes-render.php:573` uses `_n( '%d entry', '%d entries', $entry_count, 'signal-noise' )`. `functions.php` does not call `load_theme_textdomain( 'signal-noise', ... )` and there is no `languages/` directory. As of WP 4.6 `load_theme_textdomain()` is auto-loaded for translations on wordpress.org, but a self-hosted theme like this one needs it explicitly to load `.mo` files. In practice this string falls through to the English source, which is fine for a single-language site. Flagging because (a) most other user-visible strings in the codebase are bare English (admin notices, button labels, error messages — see L1) and (b) using a text domain in one place but no registration anywhere is inconsistent.

#### M9. `notice` body run through `wp_kses_post()` allows arbitrary HTML — fine here, but the inputs are constructed with `.` concatenation that escapes user-controlled fragments inconsistently

`inc/admin-page.php:121-132`:

```php
'Self-heal: re-synced ' . $fixed_n . ' template file(s) from GitHub <code>' . esc_html( function_exists( 'sn_updater_branch' ) ? sn_updater_branch() : 'main' ) . '</code> — '
. '<code>' . implode( '</code>, <code>', array_map( 'esc_html', $heal['fixed'] ) ) . '</code>. Caches purged.',
```

`esc_html`'d fragments are concatenated with hardcoded markup, then the whole thing is run through `wp_kses_post()` on output. This is correct: `wp_kses_post` accepts the markup, and the dynamic fragments are pre-escaped. But: `sn_updater_branch()` is not user-controllable (it's either `'main'` or the wp-config-defined `SN_GITHUB_BRANCH` constant), and `$heal['fixed']` is a list of theme file paths — also internal. No injection risk in practice. Documentation/style finding.

### LOW

#### L1. Bare English strings throughout admin UI (no i18n)

Across all admin-facing files: `inc/admin-page.php`, `inc/admin-bar.php`, `inc/cloudflare-purge.php`, `inc/plausible-admin.php`, `inc/reading-time.php`, `inc/template-self-heal.php`, `inc/updater.php`. Examples:

- `inc/admin-bar.php:144`: `wp_send_json_error( array( 'message' => 'Forbidden.' ), 403 );`
- `inc/admin-page.php:58`: `$count . ' database override(s) cleared. Site is reading from theme files.'`
- `inc/cloudflare-purge.php:278`: `'Cloudflare settings saved.'`
- `inc/plausible-admin.php:54`: `'Stats API key saved. Caches purged — widgets refresh on next dashboard view.'`

For a single-author site that's never going to ship to wordpress.org, this is fine. Noting only because a wholesale `__()`/`esc_html__()` pass would take 30 minutes and would future-proof the theme if it's ever distributed.

#### L2. `date( 'Y' )` in shortcode bypasses site timezone

`inc/setup.php:37`:

```php
function signal_noise_current_year() {
    return date( 'Y' );
}
```

`date()` uses the server timezone, which on a US-hosted WordPress can disagree with the site's configured timezone for a few hours per year on Dec 31. WP convention is `wp_date( 'Y' )` (since 5.3) which respects the site setting. Cosmetic.

#### L3. Anonymous closure proliferation

Most of the `add_action`/`add_filter` calls in this codebase use anonymous closures. WPCS doesn't ban this but does prefer named functions for testability and so they can be removed via `remove_action/remove_filter`. Harder to override theme behaviour from a child theme or mu-plugin when every hook is anonymous. Examples: `inc/seo.php:64,80,122,133,143`; `inc/security-headers.php:61,79,102,136,140,144,148`; `inc/cloudflare-purge.php:181,220,245`; `inc/og-image.php:290,369,383,391,399`; `inc/reading-time.php:104,180,330`; `inc/template-maintenance.php:167,180,198,238`; `inc/template-self-heal.php:434`; `inc/updater.php:186,269,332,352,365,413,463`; `inc/admin-page.php:32`; `inc/admin-bar.php:77,129`. Style preference, not a defect.

#### L4. Missing `@param`/`@return` on inline anonymous closures

WPCS technically requires PHPDoc on every callable; named functions in this codebase generally have it, but the (numerous) anonymous closures don't. Same surface area as L3.

#### L5. `if ( $cached_version !== $current )` — Yoda not applied

`inc/template-maintenance.php:203`. WPCS prefers `if ( $current !== $cached_version )` (constant-on-left when there is one) but here both sides are variables, so Yoda doesn't strictly apply. There are scattered places with `=== $string` Yoda applied correctly; the codebase is mostly consistent.

#### L6. Unconditional `wp_safe_redirect` on `?author=N` — fine, but redirect target is hardcoded `home_url('/')`

`inc/security-headers.php:86-89`. Correct. The target is non-user-controlled (`home_url('/')`). Could use `wp_validate_redirect()` for belt-and-suspenders but unnecessary given fixed destination.

#### L7. `strpos()` rather than `str_contains()` — minor

PHP 8+ has `str_contains()`. The codebase targets PHP 8.0+ (per the audit prompt) and uses `str_contains()` in `inc/assets-frontend.php:163`. Mixing the two within the same codebase is inconsistent style.

#### L8. `signal_noise_*` and `sn_*` prefixes both used

`inc/setup.php` uses `signal_noise_editor_styles`, `signal_noise_current_year`. Everything else uses `sn_*`. Pre-v6 legacy or just inconsistent. Either is fine; consistency would be cleaner.

#### L9. `imagepng` quality argument is `6` (line 211 of `inc/og-image.php`)

PNG `quality` is the compression level, 0–9. Fine, just confirm the magic number with a constant or comment.

#### L10. Output-buffer regex (frontend-filters.php:55,59,60) modifies entire HTML response

`inc/frontend-filters.php:52-65` adds an `ob_start()` callback on every front-end request that runs three `preg_replace()` calls against the full HTML. Cost is small but noticeable on large pages. WPCS doesn't ban it; perf concern only.

#### L11. `error_log`/`var_dump`/`print_r`/`die`/`exit` left in code

`grep` shows two `exit;` (`inc/security-headers.php:89`, `inc/page-notes-template.php:113`) — both legitimate (early-return short-circuits). No `error_log`, `var_dump`, `print_r`, or unconditional `die` left in source.

### NIT

#### N1. Double-spacing inside `[ ]` and around operators is consistent throughout — good.

No action — recognising the codebase already follows WPCS spacing in 95%+ of places.

#### N2. `esc_attr( substr( $option_token, -4 ) )` already-escaped, then concatenated to raw `&bull;` HTML entities

`inc/plausible-admin.php:134-136`:

```php
$token_obscured = '' === $option_token ? '' : '&bull;&bull;&bull;&bull;' . esc_attr( substr( $option_token, -4 ) );
echo '...<input type="text" name="sn_pl_token" value="' . $token_obscured . '" ...';
```

The `&bull;` literals are static HTML entities, which are valid inside an attribute value. The dynamic last-4-chars suffix is `esc_attr`'d. So the output is safe — entities pass through `esc_attr` unchanged anyway. But the pattern is a future-bug magnet (someone adds a dynamic prefix and forgets to escape it). Consider `value="' . esc_attr( $token_obscured ) . '"` instead — `esc_attr` on `&bull;` produces `&amp;bull;`, which decodes to `&bull;` in the attribute, same visual result, and the entire value is then guaranteed safe by construction.

#### N3. `home_url()` vs hardcoded `/notes/`, `/provenance/`, `/notes/feed/`

`inc/cloudflare-purge.php:198-200`. These paths are passed to `home_url()`, so they're well-formed URLs. Fine.

#### N4. Hardcoded paths like `home_url('/wp-content/uploads/2026/02/cropped-jl_logo-min-300x300.png')`

`inc/seo.php:91`. The default OG image path is hardcoded. Filterable via `sn_og_image_url` so it's overridable, but a comment noting "this URL must exist on the site" would help future maintainers.

#### N5. Inline CSS in admin pages

Lots of `style="..."` attributes in `inc/admin-page.php` and `inc/cloudflare-purge.php`. WPCS doesn't forbid but prefers external stylesheets. Acceptable for admin scaffolding.

#### N6. `Theme_Upgrader` instanceof check `inc/updater.php:367`

This check requires `class_exists` to be true at filter dispatch time, which is true on theme upgrade context. Fine.

## Strengths

1. **Disciplined nonce + capability gating on every admin handler.** Every `$_POST` consumer pairs a nonce check with a capability check. The admin-bar AJAX handlers are particularly well-done: per-action nonces, JSON responses, `textContent` (not `innerHTML`) on the JS side. This is exactly the right pattern.
2. **Token storage is best-practice.** Cloudflare and Plausible tokens stored as non-autoloaded options (third arg `false` to `update_option`); constants in `wp-config.php` take precedence; obscured display in admin UI; never in HTTP URLs (only in `Authorization` headers); never in error_log or `wp_die` output.
3. **No SQL injection surfaces.** Every `$wpdb->query` uses `$wpdb->prepare` with `$wpdb->esc_like()`. The two LIKE-based transient cleanups in `admin-page.php:79-86` and `admin-bar.php:194-201` are textbook-correct.
4. **GitHub self-updater is hardened beyond what most theme-updaters bother with.** Six validation gates before writing remote content (HTTP 200, JSON parse, `encoding=base64`, `base64_decode` strict, size cross-check, content-shape gate). The historical comment blocks document why each gate exists.
5. **Defense-in-depth on REST user enumeration + author archive blocks** (`inc/security-headers.php`). The architectural notes about edge-vs-PHP duplication are correct and well-reasoned.
6. **Extensive defensive comments on every non-obvious decision.** Explains *why* each migration flag exists, why a particular regex is anchored, why a specific gate fires. This is the kind of code review that future-Juan will thank current-Juan for.
7. **OG generator non-blocking contract** (`inc/og-image.php`). Post-incident architectural fix where `sn_og_image_url_for_post()` explicitly refuses to do synchronous work on render path. Excellent post-mortem hygiene.
8. **Plausible API SWR caching** is correctly implemented — admin renders never block on Plausible. Idempotent fallback when configuration is incomplete.
9. **Self-heal validation gates** are best-in-class. The base64-decode-then-size-check-then-content-shape pattern after the JSON-as-content corruption incident is exactly the right defensive response.
10. **No raw-string interpolation into HTML attributes anywhere user-influenceable.** The places that *do* concatenate (e.g. `installed_label`) only concat already-escaped fragments.

## Suggested next moves

1. **Add explicit `current_user_can()` to `sn_theme_options_page()`** (H1). Two lines, eliminates the only structural defense-in-depth gap in admin handlers. 5-minute fix.
2. **Refactor the `installed_label` concatenation in `admin-page.php:219-220`** to print escaped fragments inline rather than build a pre-escaped string and echo it (H2). Reduces the maintenance footprint for a class of XSS bug that hasn't bitten yet but easily could. 10 minutes.
3. **Bulk-pass `__()`/`esc_html__()` over admin strings with text domain `'signal-noise'` AND register `load_theme_textdomain` in `inc/setup.php`** (M8 + L1). Even without shipping translations, this future-proofs distribution and cleans up the inconsistency. 30 minutes; mechanical.
4. **Replace `date()` with `wp_date()`** (L2) — three-character change, eliminates the New-Year-Eve timezone edge case.
5. **Document the inline CSS injection contract on `assets/css/critical.css`** (M1) — one comment block confirming "this file is theme-owned and never user-influenced; if anything programmatically writes to it in the future, that path must be sanitized at the write site". 5 minutes.

None of these are blocking; the codebase is healthy. Items 1 and 2 are the only two I'd ship as a `v7.2.8: hardening` bump if you want a clean defensive sweep before moving on.
