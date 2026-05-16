# Companion Plugin — Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create `juanlentino/signal-and-noise-tools` `v1.0.0` (the companion plugin) with 9 modules moved from the theme via WP action/filter contracts, and ship a coordinated theme `v8.2.0` that drops the moved files and registers the listener side of those contracts.

**Architecture:** Two-repo coordinated release. Plugin lives at `/Users/juanlentino/projects/signal-and-noise-tools/` (cloned from GitHub). Theme work happens in this worktree. Cross-package coupling resolves via 5 WP hooks (3 filters with return values, 2 actions as side-effect triggers).

**Tech Stack:** PHP 8.0+, WordPress 6.9+, GitHub for both repos. No build step.

**Commit policy:** One release commit per repo, matching the theme's established `vX.Y.Z:` pattern. Tasks stage edits without committing; the per-repo release task produces the single tagged commit.

---

## Cross-package contracts (reference table)

Filters (return values):

| Hook | Plugin dispatches | Theme listens with | Returns |
| --- | --- | --- | --- |
| `sn_purge_all_caches_result` | `(int) apply_filters( 'sn_purge_all_caches_result', 0, $args )` | calls `sn_purge_all_caches( $args )` and returns count | int (items cleared) |
| `sn_self_heal_force_run_result` | `apply_filters( 'sn_self_heal_force_run_result', null )` | calls `sn_self_heal_force_run()` and returns result | array(`fixed`, `failed`) or null |
| `sn_updater_branch` | `(string) apply_filters( 'sn_updater_branch', 'main' )` | calls `sn_updater_branch()` and returns string | string (tracked branch) |

Actions (side-effect triggers):

| Hook | Plugin dispatches | Theme listens with | Effect |
| --- | --- | --- | --- |
| `sn_updater_force_check` | `do_action( 'sn_updater_force_check' )` | calls new `sn_updater_force_check()` function | clears all SN updater caches + `wp_update_themes()` |
| `sn_updater_clear_error` | `do_action( 'sn_updater_clear_error' )` | calls new `sn_updater_clear_error()` function | clears `sn_github_error` transient only |

**Direct dependencies kept (no contract — option/transient keys are stable):**
- `get_option( 'sn_github_local_sha', '' )` — option key stable per spec
- `get_transient( 'sn_github_branch_*' )` — transient key stable per spec
- `wp_update_themes()`, `wp_clean_themes_cache()`, etc. — WP core

---

### Task 1: Clone plugin repo to local workspace

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/` (cloned dir)

- [ ] **Step 1: Clone the plugin repo**

```bash
cd /Users/juanlentino/projects
git clone https://github.com/juanlentino/signal-and-noise-tools.git
cd signal-and-noise-tools
git status
```

Expected: clean working tree, on `main`, only `LICENSE` present (auto-added by `gh repo create`).

- [ ] **Step 2: Confirm directory layout**

```bash
ls -la /Users/juanlentino/projects/signal-and-noise-tools/
```

Expected: `LICENSE`, `.git/`, nothing else.

---

### Task 2: Plugin bootstrap file with header + constants

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/signal-and-noise-tools.php`

- [ ] **Step 1: Write the bootstrap file**

Use Write on `/Users/juanlentino/projects/signal-and-noise-tools/signal-and-noise-tools.php`:

```php
<?php
/**
 * Plugin Name: Signal & Noise Tools
 * Plugin URI:  https://github.com/juanlentino/signal-and-noise-tools
 * Description: Companion plugin for the Signal & Noise theme. Operational tooling: REST surface, Plausible integration, security headers, Cloudflare purge, admin UI. Self-updater migrates in Phase 2.
 * Version:     1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author:      Juan Lentino
 * Author URI:  https://juanlentino.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SignalNoiseTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SNT_VERSION', '1.0.0' );
define( 'SNT_PATH', plugin_dir_path( __FILE__ ) );
define( 'SNT_URL', plugin_dir_url( __FILE__ ) );

// Module includes — extended in Task 8 once modules land.
// Each include path is relative to SNT_PATH.
require_once SNT_PATH . 'inc/seo.php';
require_once SNT_PATH . 'inc/security-headers.php';
require_once SNT_PATH . 'inc/cloudflare-purge.php';
require_once SNT_PATH . 'inc/plausible-api.php';
require_once SNT_PATH . 'inc/plausible-admin.php';
require_once SNT_PATH . 'inc/plausible-widget.php';
require_once SNT_PATH . 'inc/admin-bar.php';
require_once SNT_PATH . 'inc/admin-page.php';
require_once SNT_PATH . 'inc/rest-api.php';
```

- [ ] **Step 2: Verify**

```bash
head -25 /Users/juanlentino/projects/signal-and-noise-tools/signal-and-noise-tools.php
```

Expected: shows the plugin header with `Plugin Name: Signal & Noise Tools` and `Version: 1.0.0`.

---

### Task 3: Plugin auxiliary files

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/README.md`
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/CHANGELOG.md`
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/composer.json`
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/.gitignore`
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/.github/workflows/lint.yml`

- [ ] **Step 1: README**

Write `/Users/juanlentino/projects/signal-and-noise-tools/README.md`:

```markdown
# Signal & Noise Tools

Companion plugin for the [Signal & Noise theme](https://github.com/juanlentino/signal-and-noise). Holds the operational tooling that lives outside theme presentation: REST surface, Plausible integration, Cloudflare purge, security headers, admin UI.

## Status

Phase 1 of a 4-phase split from the theme repo. See the theme's `docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md` for the full architecture spec.

## Installation (Phase 1, manual)

1. Download a release zip from this repo's *Releases* tab (or `https://github.com/juanlentino/signal-and-noise-tools/archive/refs/tags/v1.0.0.zip`).
2. WP admin → Plugins → Add New → Upload Plugin → choose the zip → Install Now → Activate.
3. If WP unzips to `wp-content/plugins/signal-and-noise-tools-1.0.0/` (with the version suffix), rename via SFTP to `wp-content/plugins/signal-and-noise-tools/`.

Phase 2 will add a GitHub-poll self-updater that handles install/update automatically.

## Cross-package contracts

This plugin coordinates with the theme via WP hooks. See [docs/CONTRACTS.md](docs/CONTRACTS.md) for the full list (added in a future phase if the contract surface grows).

| Hook | Direction | Purpose |
| --- | --- | --- |
| `sn_purge_all_caches_result` | Plugin → Theme | Trigger theme's cache-purge function, get count back |
| `sn_self_heal_force_run_result` | Plugin → Theme | Trigger theme's template self-heal, get result array back |
| `sn_updater_branch` | Plugin → Theme | Read the theme updater's tracked branch |
| `sn_updater_force_check` | Plugin → Theme | Force the theme updater to re-poll GitHub |
| `sn_updater_clear_error` | Plugin → Theme | Dismiss the theme updater's error notice |

## License

GPL-2.0-or-later — same as the theme.
```

- [ ] **Step 2: CHANGELOG**

Write `/Users/juanlentino/projects/signal-and-noise-tools/CHANGELOG.md`:

```markdown
# Changelog

All notable changes to Signal & Noise Tools are documented here.

## [1.0.0] — Phase 1: scaffold + easy moves

First release. Nine modules moved from the theme repo via the WP action/filter contract pattern.

### Added

- Plugin bootstrap (`signal-and-noise-tools.php`) with standard WP plugin header.
- 9 modules under `inc/`, mirroring the theme's flat module structure: `seo.php`, `security-headers.php`, `cloudflare-purge.php`, `plausible-api.php`, `plausible-admin.php`, `plausible-widget.php`, `admin-bar.php`, `admin-page.php`, `rest-api.php`.
- Cross-package contracts: three filters (`sn_purge_all_caches_result`, `sn_self_heal_force_run_result`, `sn_updater_branch`) and two actions (`sn_updater_force_check`, `sn_updater_clear_error`).
- GitHub Actions lint workflow (`php -l` on every PHP file).

### Coordination

Ships alongside theme Signal & Noise `v8.2.0`, which deletes the original copies of these 9 modules and registers the listener side of the contracts. Install plugin first, then ship the theme update.

### Spec + plan (from theme repo)

- `docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md`
- `docs/superpowers/plans/2026-05-15-companion-plugin-phase-1.md`
```

- [ ] **Step 3: composer.json**

Write `/Users/juanlentino/projects/signal-and-noise-tools/composer.json`:

```json
{
	"name": "juanlentino/signal-and-noise-tools",
	"description": "Companion plugin for the Signal & Noise WordPress theme.",
	"type": "wordpress-plugin",
	"license": "GPL-2.0-or-later",
	"authors": [
		{
			"name": "Juan Lentino",
			"homepage": "https://juanlentino.com"
		}
	],
	"require": {
		"php": ">=8.0"
	},
	"minimum-stability": "stable"
}
```

- [ ] **Step 4: .gitignore**

Write `/Users/juanlentino/projects/signal-and-noise-tools/.gitignore`:

```
.DS_Store
*.log
vendor/
node_modules/
.idea/
.vscode/
```

- [ ] **Step 5: Lint workflow**

Create directory first:

```bash
mkdir -p /Users/juanlentino/projects/signal-and-noise-tools/.github/workflows
```

Write `/Users/juanlentino/projects/signal-and-noise-tools/.github/workflows/lint.yml`:

```yaml
name: Lint

on:
  push:
    branches: [main]
  pull_request:
  workflow_dispatch:

jobs:
  lint:
    name: PHP syntax check
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          coverage: none
          tools: none

      - name: Lint all .php files
        run: |
          set -e
          fail=0
          while IFS= read -r f; do
            if ! php -l "$f" > /dev/null; then
              echo "::error file=$f::PHP parse error"
              fail=1
            fi
          done < <(find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*')
          exit $fail
```

- [ ] **Step 6: Verify**

```bash
ls -la /Users/juanlentino/projects/signal-and-noise-tools/
ls -la /Users/juanlentino/projects/signal-and-noise-tools/.github/workflows/
```

Expected: README.md, CHANGELOG.md, composer.json, .gitignore present; lint.yml inside workflows/.

---

### Task 4: Move 6 self-contained modules to plugin

**Files (copied byte-identical from theme to plugin):**
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/inc/seo.php` (from theme `inc/seo.php`)
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/inc/security-headers.php`
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/inc/cloudflare-purge.php`
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/inc/plausible-api.php`
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/inc/plausible-admin.php`
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/inc/plausible-widget.php`

These 6 modules have no cross-coupling to theme-resident functions; they copy byte-identical.

- [ ] **Step 1: Create plugin inc/ directory**

```bash
mkdir -p /Users/juanlentino/projects/signal-and-noise-tools/inc
```

- [ ] **Step 2: Copy the 6 self-contained modules**

```bash
cp /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/seo.php /Users/juanlentino/projects/signal-and-noise-tools/inc/seo.php
cp /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/security-headers.php /Users/juanlentino/projects/signal-and-noise-tools/inc/security-headers.php
cp /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/cloudflare-purge.php /Users/juanlentino/projects/signal-and-noise-tools/inc/cloudflare-purge.php
cp /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/plausible-api.php /Users/juanlentino/projects/signal-and-noise-tools/inc/plausible-api.php
cp /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/plausible-admin.php /Users/juanlentino/projects/signal-and-noise-tools/inc/plausible-admin.php
cp /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/plausible-widget.php /Users/juanlentino/projects/signal-and-noise-tools/inc/plausible-widget.php
```

- [ ] **Step 3: Audit each for unexpected theme-path references**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
grep -nE "get_theme_file_(path|uri)|get_template_directory|get_stylesheet_directory|wp_get_theme.*signal" inc/seo.php inc/security-headers.php inc/cloudflare-purge.php inc/plausible-api.php inc/plausible-admin.php inc/plausible-widget.php
```

Expected: zero hits. If any line returns, that module has hidden theme coupling — STOP and investigate before proceeding. Add the surfaced coupling to the contract table.

- [ ] **Step 4: Verify 6 files present + line counts roughly match originals**

```bash
ls -la /Users/juanlentino/projects/signal-and-noise-tools/inc/
wc -l /Users/juanlentino/projects/signal-and-noise-tools/inc/*.php
```

Expected: 6 files, line counts identical to corresponding theme files.

---

### Task 5: Move admin-bar.php with contract dispatch

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/inc/admin-bar.php` (adapted copy of theme's `inc/admin-bar.php`)

`admin-bar.php` has TWO cross-coupling sites:
1. Line ~151: `sn_purge_all_caches( array( 'template_overrides' => false ) );` (purge button)
2. Lines ~181–204: inline force-check sequence (check-updates button — clears SN updater transients + `wp_update_themes()`). The `$branch` variable at line ~185 also comes from `sn_updater_branch()` somewhere upstream.

Replace both with contract dispatches.

- [ ] **Step 1: Copy the file**

```bash
cp /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/admin-bar.php /Users/juanlentino/projects/signal-and-noise-tools/inc/admin-bar.php
```

- [ ] **Step 2: Replace the purge call with filter dispatch**

Use Read on `/Users/juanlentino/projects/signal-and-noise-tools/inc/admin-bar.php` to find the exact line context around the `sn_purge_all_caches` call (around line 146–151). The current pattern is:

```php
if ( ! function_exists( 'sn_purge_all_caches' ) ) {
	// (existing fallback)
}
sn_purge_all_caches( array( 'template_overrides' => false ) );
```

Use Edit to replace with:

```php
$cleared = (int) apply_filters( 'sn_purge_all_caches_result', 0, array( 'template_overrides' => false ) );
```

Remove the `function_exists` guard block above the call entirely — the filter returns 0 when no listener is registered, which is the correct fallback.

- [ ] **Step 3: Replace the force-check sequence with action dispatch**

Find the block around line ~181–204 — the sequence that calls `sn_updater_branch()`, deletes multiple transients, runs `$wpdb->query` to LIKE-delete revcount transients, calls `delete_site_transient('update_themes')`, `wp_clean_themes_cache()`, `wp_update_themes()`.

Replace the entire sequence (everything from the `$branch = ` line through `wp_update_themes()`) with:

```php
do_action( 'sn_updater_force_check' );
```

If there are display strings around it (notice text confirming the check ran), keep those — only the cache-manipulation lines collapse.

- [ ] **Step 4: Replace any `sn_updater_branch()` reads**

Search for any remaining `sn_updater_branch()` call in the file:

```bash
grep -n "sn_updater_branch" /Users/juanlentino/projects/signal-and-noise-tools/inc/admin-bar.php
```

For each hit (if any), replace with:

```php
(string) apply_filters( 'sn_updater_branch', 'main' )
```

- [ ] **Step 5: Verify no remaining direct cross-calls**

```bash
grep -nE "sn_purge_all_caches\s*\(|sn_self_heal_force_run\s*\(|sn_updater_branch\s*\(|sn_updater_force_check\s*\(|delete_transient\(\s*['\"]sn_github" /Users/juanlentino/projects/signal-and-noise-tools/inc/admin-bar.php
```

Expected: zero hits.

---

### Task 6: Move admin-page.php with contract dispatch

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/inc/admin-page.php`

`admin-page.php` is the most cross-coupled file. Sites to convert:

1. Line ~75: `sn_purge_all_caches( array( 'template_overrides' => false ) );`
2. Line ~80–104: full force-check sequence in the `check_updates` action handler
3. Line ~111: `delete_transient( 'sn_github_error' );` in `full_reset` handler
4. Line ~112: `$count = sn_purge_all_caches();`
5. Line ~123–124: `sn_self_heal_force_run()` call with return-value usage
6. Line ~81, ~156, ~160: `sn_updater_branch()` calls
7. Line ~157: `get_option( 'sn_github_local_sha', '' )` — KEEP direct (option key stable)
8. Line ~169: `get_transient( 'sn_github_branch_' . $branch )` — KEEP direct (transient key stable)

- [ ] **Step 1: Copy the file**

```bash
cp /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/admin-page.php /Users/juanlentino/projects/signal-and-noise-tools/inc/admin-page.php
```

- [ ] **Step 2: Replace the first purge call (line ~75 area)**

Use Edit on `/Users/juanlentino/projects/signal-and-noise-tools/inc/admin-page.php`:

OLD (the lines including `sn_purge_all_caches( array( 'template_overrides' => false ) );`):

```php
			// Single source of truth for "purge everything" — see
			// sn_purge_all_caches() in inc/template-maintenance.php.
			// Skip template_overrides here so the button reads as
			// "purge caches", not "also delete admin Site Editor edits".
			sn_purge_all_caches( array( 'template_overrides' => false ) );
			$notices[] = array( 'success', 'All caches purged.' );
```

NEW:

```php
			// Cache-purge dispatched via the sn_purge_all_caches_result
			// filter contract — the theme module owns the implementation;
			// this filter returns 0 when the theme isn't loaded (no-op
			// fallback). See docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md.
			$cleared = (int) apply_filters( 'sn_purge_all_caches_result', 0, array( 'template_overrides' => false ) );
			$notices[] = array( 'success', 'All caches purged.' );
```

- [ ] **Step 3: Replace the check_updates force-check sequence**

OLD (the entire `if ( 'check_updates' === $action ) { ... }` block contents, lines ~79–107):

```php
		if ( 'check_updates' === $action ) {
			delete_transient( 'sn_github_error' );
			$branch = function_exists( 'sn_updater_branch' ) ? sanitize_key( sn_updater_branch() ) : 'main';
			delete_transient( 'sn_github_branch_' . $branch );
			delete_transient( 'sn_github_remote_version_' . $branch );
			// Revcount cache is keyed by branch + base_version so we LIKE-delete
			// all variants for this branch (covers both legacy and bumped forms).
			global $wpdb;
			if ( $wpdb ) {
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$wpdb->options}
					 WHERE option_name LIKE %s
					    OR option_name LIKE %s",
					$wpdb->esc_like( '_transient_sn_github_revcount_' . $branch ) . '%',
					$wpdb->esc_like( '_transient_timeout_sn_github_revcount_' . $branch ) . '%'
				) );
			}
			delete_site_transient( 'update_themes' );
			wp_clean_themes_cache();

			// Repopulate update_themes immediately. wp_update_themes() will run
			// our pre_set_site_transient_update_themes filter, which fetches the
			// fresh main HEAD and sets $transient->response if there's drift.
			// Without this call, Dashboard → Updates renders an empty transient
			// and falsely reports "all up to date" until the next cron run.
			wp_update_themes();

			$notices[] = array( 'info', 'Update check complete. Visit <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">Dashboard &rarr; Updates</a> to install pending updates.' );
		}
```

NEW:

```php
		if ( 'check_updates' === $action ) {
			// Updater force-check dispatched via the sn_updater_force_check
			// action contract — theme owns the cache-key-naming details and
			// does the wp_update_themes() repopulate. No-op when theme not
			// loaded.
			do_action( 'sn_updater_force_check' );
			$notices[] = array( 'info', 'Update check complete. Visit <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">Dashboard &rarr; Updates</a> to install pending updates.' );
		}
```

- [ ] **Step 4: Replace full_reset's delete_transient + purge call**

OLD (the `if ( 'full_reset' === $action ) { ... }` block, lines ~109–114):

```php
		if ( 'full_reset' === $action ) {
			// Full reset = purge everything including DB template overrides.
			delete_transient( 'sn_github_error' );
			$count = sn_purge_all_caches();
			$notices[] = array( 'success', 'Full reset: ' . $count . ' override(s) cleared + all caches purged.' );
		}
```

NEW:

```php
		if ( 'full_reset' === $action ) {
			// Full reset = purge everything including DB template overrides.
			do_action( 'sn_updater_clear_error' );
			$count = (int) apply_filters( 'sn_purge_all_caches_result', 0, array() );
			$notices[] = array( 'success', 'Full reset: ' . $count . ' override(s) cleared + all caches purged.' );
		}
```

- [ ] **Step 5: Replace heal_templates handler**

OLD (the `if ( 'heal_templates' === $action ) { ... }` block, lines ~116–147 — the part calling `sn_self_heal_force_run`):

```php
		if ( 'heal_templates' === $action ) {
			// Force-sync every monitored template/part file from GitHub
			// main, bypassing the 5-min rate limit and clearing the per-
			// file failure cooldown so retries happen now. This is the
			// recovery path for the "deploy didn't take effect on one
			// specific route" failure mode — see inc/template-self-heal.php
			// for the validation gates this runs through.
			if ( function_exists( 'sn_self_heal_force_run' ) ) {
				$heal = sn_self_heal_force_run();
```

NEW (replace the `if ( function_exists ... ) { $heal = sn_self_heal_force_run();` line with the filter dispatch):

```php
		if ( 'heal_templates' === $action ) {
			// Force-sync every monitored template/part file from GitHub
			// main, bypassing the 5-min rate limit and clearing the per-
			// file failure cooldown so retries happen now. Dispatched via
			// the sn_self_heal_force_run_result filter contract — theme
			// owns the implementation; returns null when not loaded.
			$heal = apply_filters( 'sn_self_heal_force_run_result', null );
			if ( is_array( $heal ) ) {
```

(The closing `} else { $notices[] = array( 'error', 'Self-heal module not loaded.' ); }` block at the end of the heal_templates handler stays in place — it now triggers when the filter returned null.)

- [ ] **Step 6: Replace `sn_updater_branch()` reads**

Find each remaining `sn_updater_branch()` call (lines ~156, ~160 — they're for display, in the read-only "Status" section):

```bash
grep -n "sn_updater_branch" /Users/juanlentino/projects/signal-and-noise-tools/inc/admin-page.php
```

For each call (likely 2 remaining after Step 3's removal of the line ~81 occurrence), use Edit to replace the pattern `function_exists( 'sn_updater_branch' ) ? sn_updater_branch() : 'main'` with `apply_filters( 'sn_updater_branch', 'main' )`. And replace bare `sn_updater_branch()` calls (if any remain) with `apply_filters( 'sn_updater_branch', 'main' )`.

- [ ] **Step 7: KEEP direct option/transient reads**

`get_option( 'sn_github_local_sha', '' )` and `get_transient( 'sn_github_branch_' . sanitize_key( $branch ) )` stay as-is. Option key + transient key are part of the spec's "stable" contract.

The comment in [admin-page.php:163-168] referencing `inc/updater.php` should be updated to acknowledge cross-package: change "Read-only since v7.3.1 — the shared `sn_github_branch_$branch` transient is warmed by sn_updater_refresh_cache() in inc/updater.php via WP-Cron" to "Read-only since v7.3.1 — the shared `sn_github_branch_$branch` transient is warmed by the theme's updater via WP-Cron (sn_updater_refresh_cache())".

- [ ] **Step 8: Verify no remaining direct cross-calls**

```bash
grep -nE "sn_purge_all_caches\s*\(|sn_self_heal_force_run\s*\(|delete_transient\(\s*['\"]sn_github|wp_update_themes\s*\(|wp_clean_themes_cache\s*\(|delete_site_transient\(\s*['\"]update_themes" /Users/juanlentino/projects/signal-and-noise-tools/inc/admin-page.php
```

Expected: zero hits.

Also verify `sn_updater_branch` only appears via apply_filters:

```bash
grep -n "sn_updater_branch" /Users/juanlentino/projects/signal-and-noise-tools/inc/admin-page.php
```

Expected: only inside `apply_filters( 'sn_updater_branch', ... )` calls (zero bare function calls).

---

### Task 7: Move rest-api.php with contract dispatch

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/inc/rest-api.php`

`rest-api.php` cross-coupling sites:
1. Line ~167–170: `function_exists` guard + `sn_purge_all_caches( ... )` in `/purge` endpoint
2. Line ~196–199: `function_exists` guard + `sn_self_heal_force_run()` in `/heal-templates` endpoint
3. Line ~233–237: `function_exists` guard + `sn_purge_all_caches()` in `/full-reset` endpoint
4. Line ~236, ~259–275: updater transient ops in `/check-updates` endpoint (full force-check sequence)
5. Various `sn_updater_branch()` calls

- [ ] **Step 1: Copy the file**

```bash
cp /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/rest-api.php /Users/juanlentino/projects/signal-and-noise-tools/inc/rest-api.php
```

- [ ] **Step 2: Replace the `/purge` endpoint's purge call**

Find around line 165–172. The current block is:

```php
function sn_rest_purge_caches( WP_REST_Request $request ) {
	if ( ! function_exists( 'sn_purge_all_caches' ) ) {
		return new WP_Error( 'sn_rest_unavailable', 'Cache purge module not loaded.', array( 'status' => 500 ) );
	}
	$cleared = (int) sn_purge_all_caches( array( 'template_overrides' => false ) );
	return sn_rest_ok( 'All caches purged.', array( 'cleared' => $cleared ) );
}
```

Replace with:

```php
function sn_rest_purge_caches( WP_REST_Request $request ) {
	$cleared = (int) apply_filters( 'sn_purge_all_caches_result', 0, array( 'template_overrides' => false ) );
	if ( 0 === $cleared && ! has_filter( 'sn_purge_all_caches_result' ) ) {
		return new WP_Error( 'sn_rest_unavailable', 'Cache purge module not loaded.', array( 'status' => 500 ) );
	}
	return sn_rest_ok( 'All caches purged.', array( 'cleared' => $cleared ) );
}
```

(The `has_filter` check distinguishes "filter exists but cleared 0 things" — legitimate — from "no listener at all" — error.)

- [ ] **Step 3: Replace the `/heal-templates` endpoint's self-heal call**

Find around line 195–212. Current pattern:

```php
function sn_rest_heal_templates( WP_REST_Request $request ) {
	if ( ! function_exists( 'sn_self_heal_force_run' ) ) {
		return new WP_Error( 'sn_rest_unavailable', 'Self-heal module not loaded.', array( 'status' => 500 ) );
	}
	$result    = sn_self_heal_force_run();
	$fixed_n   = count( $result['fixed'] );
	$failed_n  = count( $result['failed'] );
	// ... (rest of handler)
}
```

Replace the first 4 lines with:

```php
function sn_rest_heal_templates( WP_REST_Request $request ) {
	$result = apply_filters( 'sn_self_heal_force_run_result', null );
	if ( ! is_array( $result ) ) {
		return new WP_Error( 'sn_rest_unavailable', 'Self-heal module not loaded.', array( 'status' => 500 ) );
	}
	$fixed_n  = count( $result['fixed'] );
	$failed_n = count( $result['failed'] );
	// ... (rest of handler unchanged)
}
```

- [ ] **Step 4: Replace the `/full-reset` endpoint's purge call**

Find around line 230–243. The current block:

```php
function sn_rest_full_reset( WP_REST_Request $request ) {
	if ( ! function_exists( 'sn_purge_all_caches' ) ) {
		return new WP_Error( 'sn_rest_unavailable', 'Cache purge module not loaded.', array( 'status' => 500 ) );
	}
	delete_transient( 'sn_github_error' );
	$count = (int) sn_purge_all_caches();
	return sn_rest_ok( sprintf( 'Full reset: %d override(s) cleared and all caches purged.', $count ), array( 'cleared' => $count ) );
}
```

Replace with:

```php
function sn_rest_full_reset( WP_REST_Request $request ) {
	do_action( 'sn_updater_clear_error' );
	$count = (int) apply_filters( 'sn_purge_all_caches_result', 0, array() );
	if ( 0 === $count && ! has_filter( 'sn_purge_all_caches_result' ) ) {
		return new WP_Error( 'sn_rest_unavailable', 'Cache purge module not loaded.', array( 'status' => 500 ) );
	}
	return sn_rest_ok( sprintf( 'Full reset: %d override(s) cleared and all caches purged.', $count ), array( 'cleared' => $count ) );
}
```

- [ ] **Step 5: Replace the `/check-updates` endpoint's force-check sequence**

Find around line 250–285. The current block:

```php
function sn_rest_check_updates( WP_REST_Request $request ) {
	if ( ! function_exists( 'sn_updater_branch' ) ) {
		return new WP_Error( 'sn_rest_unavailable', 'Self-updater module not loaded.', array( 'status' => 500 ) );
	}
	$branch = sanitize_key( sn_updater_branch() );
	delete_transient( 'sn_github_error' );
	delete_transient( 'sn_github_branch_' . $branch );
	delete_transient( 'sn_github_remote_version_' . $branch );
	global $wpdb;
	if ( $wpdb ) {
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE %s
			    OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_sn_github_revcount_' . $branch ) . '%',
			$wpdb->esc_like( '_transient_timeout_sn_github_revcount_' . $branch ) . '%'
		) );
	}
	delete_site_transient( 'update_themes' );
	wp_clean_themes_cache();
	// (possibly more — check the offered-update return shape)
	wp_update_themes();
	return sn_rest_ok( 'Update check complete.', array( /* offered update info if present */ ) );
}
```

Replace with:

```php
function sn_rest_check_updates( WP_REST_Request $request ) {
	if ( ! has_action( 'sn_updater_force_check' ) ) {
		return new WP_Error( 'sn_rest_unavailable', 'Self-updater module not loaded.', array( 'status' => 500 ) );
	}
	do_action( 'sn_updater_force_check' );
	return sn_rest_ok( 'Update check complete.', array() );
}
```

(If the original handler returned offered-update info in the payload — checking the actual `$transient->response` — preserve that read by adding a `get_site_transient( 'update_themes' )` read after the `do_action` and including the relevant slice in the response. Confirm by reading the actual file before editing.)

- [ ] **Step 6: Replace any other `sn_updater_branch()` calls**

```bash
grep -n "sn_updater_branch" /Users/juanlentino/projects/signal-and-noise-tools/inc/rest-api.php
```

Replace each remaining call with `apply_filters( 'sn_updater_branch', 'main' )`.

- [ ] **Step 7: Verify no remaining direct cross-calls**

```bash
grep -nE "sn_purge_all_caches\s*\(|sn_self_heal_force_run\s*\(|delete_transient\(\s*['\"]sn_github|wp_clean_themes_cache\s*\(|delete_site_transient\(\s*['\"]update_themes" /Users/juanlentino/projects/signal-and-noise-tools/inc/rest-api.php
```

Expected: zero hits. (`wp_update_themes()` calls are gone — they're inside the dispatched action's listener now.)

---

### Task 8: Plugin commit + tag v1.0.0 + push

**Files:**
- All plugin repo files created in Tasks 2–7.

- [ ] **Step 1: Verify the plugin file tree**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
find . -type f -not -path './.git/*' | sort
```

Expected output:

```
./.github/workflows/lint.yml
./.gitignore
./CHANGELOG.md
./LICENSE
./README.md
./composer.json
./inc/admin-bar.php
./inc/admin-page.php
./inc/cloudflare-purge.php
./inc/plausible-admin.php
./inc/plausible-api.php
./inc/plausible-widget.php
./inc/rest-api.php
./inc/security-headers.php
./inc/seo.php
./signal-and-noise-tools.php
```

- [ ] **Step 2: Final lint via line count check (no `php` CLI locally; CI will run real lint)**

```bash
wc -l /Users/juanlentino/projects/signal-and-noise-tools/signal-and-noise-tools.php /Users/juanlentino/projects/signal-and-noise-tools/inc/*.php
```

Expected: bootstrap is ~30 lines; each `inc/*.php` matches its corresponding theme file's line count (within ±20 lines accounting for adapted lines).

- [ ] **Step 3: Stage all files**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add .
git status --short
```

Expected: all new files staged as `A` (Added).

- [ ] **Step 4: Create the release commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git commit -m "$(cat <<'EOF'
v1.0.0: Phase 1 — scaffold + 9 module moves from theme

Initial release. Companion plugin to the Signal & Noise theme. Moves
9 low-coupling modules from the theme repo's inc/ directory into a
proper plugin structure:

  - seo.php, security-headers.php, cloudflare-purge.php
  - plausible-api.php, plausible-admin.php, plausible-widget.php
  - admin-bar.php, admin-page.php, rest-api.php

Cross-package contracts wired via WP hooks (3 filters, 2 actions). See
README.md "Cross-package contracts" section + the theme repo's
docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md.

Ships alongside theme v8.2.0 which drops the moved files from theme
inc/ and registers the listener side of the contracts.

Spec: theme repo docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md
Plan: theme repo docs/superpowers/plans/2026-05-15-companion-plugin-phase-1.md

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 5: Push main**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git push origin main
```

Expected: push succeeds; CI lint job triggers on GitHub.

- [ ] **Step 6: Create + push the v1.0.0 tag**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git tag -a v1.0.0 -m "v1.0.0 — Phase 1: scaffold + 9 module moves from theme"
git push origin v1.0.0
```

- [ ] **Step 7: Verify on GitHub**

```bash
gh release view v1.0.0 --repo juanlentino/signal-and-noise-tools 2>&1 || echo "no release created (tag only — that's fine for Phase 1)"
gh api repos/juanlentino/signal-and-noise-tools/git/refs/tags/v1.0.0 | python3 -c "import sys,json; d=json.load(sys.stdin); print('tag SHA:', d.get('object',{}).get('sha'))"
```

Expected: tag SHA returned. (We're not creating a GitHub Release surface for Phase 1; the tag alone is enough for the zipball download URL.)

---

### Task 9: Theme — add updater contract functions + listener

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/updater.php` (add 2 new functions + 3 hook registrations)

- [ ] **Step 1: Read the end of updater.php**

Use Read on `inc/updater.php` with `offset=580, limit=20` to see the current file ending.

- [ ] **Step 2: Append new functions + hook registrations**

Append to the end of `inc/updater.php` (before any closing PHP tag — none should exist in WordPress style):

```php

/**
 * Companion-plugin contract listeners.
 *
 * Three contracts exposed via WP hooks for the signal-and-noise-tools
 * plugin (Phase 1 split). The plugin dispatches via these hooks rather
 * than calling theme functions directly, so plugin code can degrade
 * gracefully when the theme isn't loaded.
 *
 * See docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md
 * for the full contract surface and rationale.
 *
 * @since 8.2.0
 */

/**
 * Filter listener: return the tracked branch name for the companion plugin.
 *
 * @param string $default Default value (typically 'main') passed by caller.
 * @return string Branch name from sn_updater_branch().
 */
add_filter( 'sn_updater_branch', function( $default ) {
	return sn_updater_branch();
} );

/**
 * Action listener: force-check the updater. Clears all SN GitHub-derived
 * caches and triggers WP's update_themes recheck so the next admin
 * pageview re-runs our pre_set_site_transient_update_themes filter
 * against fresh GitHub state. Consolidates the cache-clearing logic
 * that was previously duplicated in admin-page.php, admin-bar.php,
 * and rest-api.php (all of which moved to the companion plugin and now
 * dispatch through this hook).
 */
function sn_updater_force_check() {
	$branch = sanitize_key( sn_updater_branch() );
	delete_transient( 'sn_github_error' );
	delete_transient( 'sn_github_branch_' . $branch );
	delete_transient( 'sn_github_remote_version_' . $branch );
	global $wpdb;
	if ( $wpdb ) {
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE %s
			    OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_sn_github_revcount_' . $branch ) . '%',
			$wpdb->esc_like( '_transient_timeout_sn_github_revcount_' . $branch ) . '%'
		) );
	}
	delete_site_transient( 'update_themes' );
	wp_clean_themes_cache();
	wp_update_themes();
}
add_action( 'sn_updater_force_check', 'sn_updater_force_check' );

/**
 * Action listener: clear just the updater's error transient.
 * Lightweight version of sn_updater_force_check() for code paths
 * that only want to dismiss the error notice without forcing a full
 * re-poll. Called from the companion plugin's full_reset admin action.
 */
function sn_updater_clear_error() {
	delete_transient( 'sn_github_error' );
}
add_action( 'sn_updater_clear_error', 'sn_updater_clear_error' );
```

- [ ] **Step 3: Verify the new functions + hooks landed**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
grep -n "function sn_updater_force_check\|function sn_updater_clear_error\|add_filter( 'sn_updater_branch'\|add_action( 'sn_updater_force_check'\|add_action( 'sn_updater_clear_error'" inc/updater.php
```

Expected: 5 lines printed (2 function declarations + 3 hook registrations).

- [ ] **Step 4: Stage**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git add inc/updater.php
```

---

### Task 10: Theme — register cache-purge + self-heal contract listeners

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/template-maintenance.php` (add filter listener)
- Modify: `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/template-self-heal.php` (add filter listener)

- [ ] **Step 1: Append the sn_purge_all_caches_result filter listener to template-maintenance.php**

Read the end of `inc/template-maintenance.php` first to know where to append. Then add:

```php

/**
 * Filter contract listener for the companion plugin (Phase 1, since v8.2.0):
 * accept dispatched purge calls from signal-and-noise-tools, run the local
 * sn_purge_all_caches() implementation, return the count cleared.
 *
 * @param int   $count Seed value (typically 0) passed by caller.
 * @param array $args  Purge args (e.g., array('template_overrides' => false)).
 * @return int Items cleared.
 */
add_filter( 'sn_purge_all_caches_result', function( $count, $args ) {
	return (int) sn_purge_all_caches( is_array( $args ) ? $args : array() );
}, 10, 2 );
```

- [ ] **Step 2: Append the sn_self_heal_force_run_result filter listener to template-self-heal.php**

Read the end of `inc/template-self-heal.php`. Then add:

```php

/**
 * Filter contract listener for the companion plugin (Phase 1, since v8.2.0):
 * accept dispatched self-heal calls from signal-and-noise-tools, run the
 * local sn_self_heal_force_run() implementation, return the result array.
 *
 * @param mixed $value Seed value (typically null) passed by caller.
 * @return array|null array('fixed' => string[], 'failed' => string[]) or null
 *                    if the local function isn't loaded.
 */
add_filter( 'sn_self_heal_force_run_result', function( $value ) {
	if ( function_exists( 'sn_self_heal_force_run' ) ) {
		return sn_self_heal_force_run();
	}
	return $value;
} );
```

- [ ] **Step 3: Verify both listeners present**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
grep -n "add_filter( 'sn_purge_all_caches_result'" inc/template-maintenance.php
grep -n "add_filter( 'sn_self_heal_force_run_result'" inc/template-self-heal.php
```

Expected: 1 line each.

- [ ] **Step 4: Stage**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git add inc/template-maintenance.php inc/template-self-heal.php
```

---

### Task 11: Theme — drop requires + delete 9 moved files

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/functions.php` (remove 9 require_once lines + update module map)
- Delete: 9 files from `inc/`

- [ ] **Step 1: Read functions.php to confirm current require_once lines**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
grep -n "require_once" functions.php
```

Expected: shows ~20 require_once lines; 9 of them point at the files being moved.

- [ ] **Step 2: Edit functions.php — remove 9 require_once lines + update module map docblock**

Use Edit on `functions.php`. Remove these 9 lines from the require_once block (exact text):

```
require_once __DIR__ . '/inc/seo.php';
```

```
require_once __DIR__ . '/inc/security-headers.php';
```

```
require_once __DIR__ . '/inc/cloudflare-purge.php';
```

```
require_once __DIR__ . '/inc/admin-page.php';
```

```
require_once __DIR__ . '/inc/admin-bar.php';
```

```
require_once __DIR__ . '/inc/plausible-api.php';
```

```
require_once __DIR__ . '/inc/plausible-admin.php';
```

```
require_once __DIR__ . '/inc/plausible-widget.php';
```

```
require_once __DIR__ . '/inc/rest-api.php';
```

Use Edit's replace_all=false for each removal (each line is unique once context lines are included).

Also update the docblock comment in functions.php (lines 9–28 region — the "Module map" docblock) to remove the entries for the 9 moved files. Replace the module-map docblock paragraph with one that reflects only the modules that stay in the theme. After edits, the docblock should reference only: `setup.php`, `assets-frontend.php`, `frontend-filters.php`, `notes-and-provenance.php`, `reading-time.php`, `og-image.php`, `template-maintenance.php`, `template-self-heal.php`, `page-notes-template.php`, `patterns.php`, `updater.php`. Add a single sentence pointing to the companion plugin for the moved modules: "Operational tooling (REST surface, Plausible integration, admin UI, security headers, Cloudflare purge) lives in the [signal-and-noise-tools companion plugin](https://github.com/juanlentino/signal-and-noise-tools) since v8.2.0."

- [ ] **Step 3: Delete the 9 moved files from theme inc/**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
rm inc/seo.php
rm inc/security-headers.php
rm inc/cloudflare-purge.php
rm inc/admin-page.php
rm inc/admin-bar.php
rm inc/plausible-api.php
rm inc/plausible-admin.php
rm inc/plausible-widget.php
rm inc/rest-api.php
ls inc/
```

Expected: `inc/` now contains only the modules that stay (12 files: `notes-and-provenance.php`, `og-image.php`, `page-notes-render.php`, `page-notes-template.php`, `patterns.php`, `reading-time.php`, `frontend-filters.php`, `assets-frontend.php`, `setup.php`, `template-maintenance.php`, `template-self-heal.php`, `updater.php`).

- [ ] **Step 4: Verify functions.php has 9 fewer requires**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
grep -c "require_once" functions.php
```

Expected: count = previous count − 9. (If the original count was 20, this should now be 11.)

- [ ] **Step 5: Stage all changes**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git add functions.php
git add -u inc/  # picks up the 9 deletions
git status --short
```

Expected: `M  functions.php`, `D  inc/seo.php`, `D  inc/security-headers.php`, etc. (9 deletions).

---

### Task 12: Theme — update WORDPRESS-REFERENCE.md + CLAUDE.md

**Files:**
- Modify: `docs/WORDPRESS-REFERENCE.md` (§10 — replace "self-updater + self-heal architecture" with the new theme+plugin split)
- Modify: `CLAUDE.md` (add a note about the companion plugin)

- [ ] **Step 1: Read WORDPRESS-REFERENCE.md §10 first**

Read `docs/WORDPRESS-REFERENCE.md` from offset 280, limit 80 (the §10 block from prior context).

- [ ] **Step 2: Update §10 — add a §10.0 "Theme + companion plugin split" preamble**

Use Edit to insert a new subsection at the top of §10, before the existing §10.1, with the following content:

```markdown
## 10. The self-updater + self-heal architecture (with companion plugin since v8.2.0)

This theme has a non-standard update mechanism AND a companion plugin that holds operational tooling. Understand both before touching `inc/updater.php`, `inc/template-self-heal.php`, or any of the WP hook contracts listed below.

### 10.0 The theme + companion plugin split (Phase 1 — v8.2.0 / Tools v1.0.0)

The theme is presentation; the companion plugin [`signal-and-noise-tools`](https://github.com/juanlentino/signal-and-noise-tools) holds operational tooling. They communicate via 5 WP hooks. **The split is partial as of v8.2.0** — 9 modules moved (Phase 1); Phases 2–4 will migrate the rest. See `docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md` and successors.

Modules currently in plugin (Phase 1 moves): `seo.php`, `security-headers.php`, `cloudflare-purge.php`, `plausible-api.php`, `plausible-admin.php`, `plausible-widget.php`, `admin-bar.php`, `admin-page.php`, `rest-api.php`.

Modules still in theme (will migrate in Phases 2–4): `updater.php`, `template-self-heal.php`, `template-maintenance.php` (Phase 2), `og-image.php`, `reading-time.php`, `notes-and-provenance.php`, `page-notes-*.php` (Phase 3), `mu-plugins/rss-plausible-tracker.php` (Phase 4).

**Contract hooks (since v8.2.0):**

| Hook | Type | Dispatched by plugin | Implemented by theme |
| --- | --- | --- | --- |
| `sn_purge_all_caches_result` | filter | `apply_filters( 'sn_purge_all_caches_result', 0, $args )` returns int count | `inc/template-maintenance.php` wraps `sn_purge_all_caches()` |
| `sn_self_heal_force_run_result` | filter | `apply_filters( 'sn_self_heal_force_run_result', null )` returns array or null | `inc/template-self-heal.php` wraps `sn_self_heal_force_run()` |
| `sn_updater_branch` | filter | `apply_filters( 'sn_updater_branch', 'main' )` returns string | `inc/updater.php` wraps `sn_updater_branch()` |
| `sn_updater_force_check` | action | `do_action( 'sn_updater_force_check' )` | `inc/updater.php`'s new `sn_updater_force_check()` function clears all SN updater caches + `wp_update_themes()` |
| `sn_updater_clear_error` | action | `do_action( 'sn_updater_clear_error' )` | `inc/updater.php`'s `sn_updater_clear_error()` clears `sn_github_error` transient |

**Direct dependencies kept (no contract — stable by design):**
- `sn_*` option keys (e.g. `sn_github_local_sha`) — plugin reads via `get_option()`.
- `sn_github_*` transient keys — plugin reads via `get_transient()`. Option/transient *key names* are part of the public contract surface; renaming them would require migration shims for zero benefit.

**When adding new cross-package interactions:** add an entry to the table above and document the listener side in the theme file that owns the underlying function. Never let plugin code directly call a theme function — even with `function_exists` guards. The contract pattern is non-negotiable.
```

(The existing §10.1, §10.2, §10.3, §10.4 stay below this new §10.0 — they continue to be relevant for the modules still in the theme.)

- [ ] **Step 3: Add companion plugin pointer to CLAUDE.md**

Use Edit on `CLAUDE.md`. Find the "## Project" section near the top. Add a paragraph at the end of that section:

OLD:

```markdown
## Project
Custom WordPress Full Site Editing theme for juanlentino.com.
Repo: juanlentino/signal-and-noise. Hosted on Cloudways (syntharchy-wp), Cloudflare CDN.
```

NEW:

```markdown
## Project
Custom WordPress Full Site Editing theme for juanlentino.com.
Repo: juanlentino/signal-and-noise. Hosted on Cloudways (syntharchy-wp), Cloudflare CDN.

**Companion plugin (since v8.2.0):** Operational tooling (REST surface, Plausible integration, admin UI, security headers, Cloudflare purge) lives in a separate companion plugin: [juanlentino/signal-and-noise-tools](https://github.com/juanlentino/signal-and-noise-tools). The split is partial — see [docs/WORDPRESS-REFERENCE.md](docs/WORDPRESS-REFERENCE.md) §10.0 for the contract surface and the migration phase plan.
```

- [ ] **Step 4: Stage**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git add docs/WORDPRESS-REFERENCE.md CLAUDE.md
```

---

### Task 13: Theme — CHANGELOG entry + version bump 8.1.1 → 8.2.0

**Files:**
- Modify: `style.css` (Version field)
- Modify: `CHANGELOG.md` (new top entry)

- [ ] **Step 1: Bump style.css Version**

Use Edit on `style.css`:

OLD: `Version: 8.1.1`
NEW: `Version: 8.2.0`

Verify:

```bash
grep "^Version:" style.css
```

Expected: `Version: 8.2.0`.

- [ ] **Step 2: Insert CHANGELOG entry at the top (between line 3 and the current top entry)**

Use Edit on `CHANGELOG.md`:

OLD:

```markdown
All notable changes to Signal & Noise are documented here.

## [8.1.1] — Handbook hygiene pass — strip i18n, refresh headers
```

NEW:

```markdown
All notable changes to Signal & Noise are documented here.

## [8.2.0] — Phase 1 of theme + companion plugin split

First minor in the 8.x line. Nine modules (`seo.php`, `security-headers.php`, `cloudflare-purge.php`, `plausible-api.php`, `plausible-admin.php`, `plausible-widget.php`, `admin-bar.php`, `admin-page.php`, `rest-api.php`) moved out of `inc/` into the new companion plugin [`signal-and-noise-tools`](https://github.com/juanlentino/signal-and-noise-tools) `v1.0.0`. Cross-package coupling resolves via 5 WP hooks (3 filters, 2 actions) — the theme registers the listener side; the plugin dispatches.

This is Phase 1 of a 4-phase split. Phase 2 will migrate the self-updater itself. See [docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md](docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md).

### Changed

- **[`functions.php`](functions.php) — 9 `require_once` lines removed.** Module map docblock updated to reflect the reduced theme surface; companion plugin referenced.
- **[`inc/`](inc/) — 9 files deleted.** Files moved to companion plugin's `inc/`; same filenames preserved for parity.
- **[`inc/updater.php`](inc/updater.php) — 2 new functions + 3 hook listeners.** `sn_updater_force_check()` consolidates the cache-clearing sequence previously duplicated in `admin-page.php`, `admin-bar.php`, and `rest-api.php` (all of which moved to the plugin). `sn_updater_clear_error()` handles the lightweight error-dismiss path. Filter listener on `sn_updater_branch` exposes the tracked branch to plugin code.
- **[`inc/template-maintenance.php`](inc/template-maintenance.php) — filter listener added.** Wraps existing `sn_purge_all_caches()` for plugin dispatch via `sn_purge_all_caches_result` filter.
- **[`inc/template-self-heal.php`](inc/template-self-heal.php) — filter listener added.** Wraps existing `sn_self_heal_force_run()` for plugin dispatch via `sn_self_heal_force_run_result` filter.

### Added (docs)

- **[`docs/WORDPRESS-REFERENCE.md`](docs/WORDPRESS-REFERENCE.md) §10.0** — new "Theme + companion plugin split" section documenting the contract surface, migration phases, and conventions for adding new cross-package interactions.
- **[`CLAUDE.md`](CLAUDE.md)** — companion plugin pointer added to the *Project* section.

### Coordinated release

Ships with companion plugin `v1.0.0`. **Install order matters:**
1. Install + activate `signal-and-noise-tools` `v1.0.0` plugin first (download zip from `https://github.com/juanlentino/signal-and-noise-tools/archive/refs/tags/v1.0.0.zip`, WP admin → Plugins → Add New → Upload).
2. Click the theme's *Update* in WP admin to install `v8.2.0` (which removes the now-duplicate files).

During the brief window between steps 1 and 2, both packages have the 9 modules — WP registers hooks twice (duplicate admin menus, REST endpoints last-write-wins, dashboard widgets duplicated). The theme's menu entry continues to work; the plugin's menu shows but its purge/heal/check-updates buttons silently no-op until step 2 lands and registers the contract listeners. Maintainer should use the theme's menu entry during the window and ship the theme update promptly.

### Why minor

Meaningful capability shift — PHP includes shrink ~45%, new contract surface introduced — but no breaking user-visible change. First minor in 8.x; well within the 5-per-major cap.

### Migration

None for end users; runtime behavior is identical after both releases land. For the maintainer: follow the install order above.

### Spec + plan

- [docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md](docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md)
- [docs/superpowers/plans/2026-05-15-companion-plugin-phase-1.md](docs/superpowers/plans/2026-05-15-companion-plugin-phase-1.md)

Authored via the `superpowers:brainstorming` → `superpowers:writing-plans` → `superpowers:executing-plans` skill chain.

## [8.1.1] — Handbook hygiene pass — strip i18n, refresh headers
```

- [ ] **Step 3: Verify**

```bash
grep -m 1 "^## \[" CHANGELOG.md
grep "^Version:" style.css
```

Expected: top entry `## [8.2.0]`; Version `8.2.0`.

- [ ] **Step 4: Stage**

```bash
git add style.css CHANGELOG.md
```

---

### Task 14: Theme — final pre-commit verification + release commit

**Files:** No new files; this task verifies the full set of theme changes and produces the release commit.

- [ ] **Step 1: Stage the plan document**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git add docs/superpowers/plans/2026-05-15-companion-plugin-phase-1.md
```

- [ ] **Step 2: Full pre-commit verification**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
echo "=== 1. 9 files deleted from inc/ ==="
ls inc/ | sort
echo ""
echo "=== 2. functions.php requires count ==="
grep -c "require_once" functions.php
echo ""
echo "=== 3. updater.php new functions present ==="
grep -c "function sn_updater_force_check\|function sn_updater_clear_error" inc/updater.php
echo ""
echo "=== 4. updater.php hook registrations present ==="
grep -c "add_filter( 'sn_updater_branch'\|add_action( 'sn_updater_force_check'\|add_action( 'sn_updater_clear_error'" inc/updater.php
echo ""
echo "=== 5. template-maintenance filter listener present ==="
grep -c "add_filter( 'sn_purge_all_caches_result'" inc/template-maintenance.php
echo ""
echo "=== 6. template-self-heal filter listener present ==="
grep -c "add_filter( 'sn_self_heal_force_run_result'" inc/template-self-heal.php
echo ""
echo "=== 7. Version + CHANGELOG ==="
grep "^Version:" style.css
grep -m 1 "^## \[" CHANGELOG.md
```

Expected:
- #1: 12 files (the modules that STAY).
- #2: 11 (or whatever the post-removal count is — must be 9 less than the previous count).
- #3: 2 (two function declarations).
- #4: 3 (three hook registrations).
- #5: 1.
- #6: 1.
- #7: `Version: 8.2.0` and `## [8.2.0] — Phase 1 of theme + companion plugin split`.

- [ ] **Step 3: Confirm full staged file set**

```bash
git status --short
```

Expected files staged:
- `M  CHANGELOG.md`
- `M  CLAUDE.md`
- `M  docs/WORDPRESS-REFERENCE.md`
- `A  docs/superpowers/plans/2026-05-15-companion-plugin-phase-1.md`
- `M  functions.php`
- `M  inc/template-maintenance.php`
- `M  inc/template-self-heal.php`
- `M  inc/updater.php`
- `D  inc/admin-bar.php`
- `D  inc/admin-page.php`
- `D  inc/cloudflare-purge.php`
- `D  inc/plausible-admin.php`
- `D  inc/plausible-api.php`
- `D  inc/plausible-widget.php`
- `D  inc/rest-api.php`
- `D  inc/security-headers.php`
- `D  inc/seo.php`
- `M  style.css`

- [ ] **Step 4: Create the release commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git commit -m "$(cat <<'EOF'
v8.2.0: Phase 1 of theme + companion plugin split

Nine modules moved out of inc/ into a new companion plugin
(signal-and-noise-tools v1.0.0):
  - seo.php, security-headers.php, cloudflare-purge.php
  - plausible-api.php, plausible-admin.php, plausible-widget.php
  - admin-bar.php, admin-page.php, rest-api.php

Cross-package coupling resolves via 5 WP hooks:
  - filters: sn_purge_all_caches_result, sn_self_heal_force_run_result,
    sn_updater_branch
  - actions: sn_updater_force_check, sn_updater_clear_error

Theme-side changes:
  - functions.php: 9 require_once lines removed; module map docblock
    updated to reflect the reduced surface + reference the plugin.
  - inc/updater.php: new sn_updater_force_check() and
    sn_updater_clear_error() functions consolidate the cache-clearing
    sequence previously duplicated in 3 plugin-side modules.
  - inc/template-maintenance.php, inc/template-self-heal.php: filter
    listeners added wrapping existing functions for plugin dispatch.
  - docs/WORDPRESS-REFERENCE.md §10.0: new section documenting the
    theme + companion plugin split, contract table, and conventions.
  - CLAUDE.md: companion plugin pointer in the Project section.

First minor in the 8.x line. Coordinated release: install plugin
v1.0.0 first, then click theme update to install v8.2.0. See
CHANGELOG entry for the duplication-window behavior between steps.

This is Phase 1 of a 4-phase split. Phase 2 (updater migration)
deferred to its own brainstorming session.

Spec: docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md
Plan: docs/superpowers/plans/2026-05-15-companion-plugin-phase-1.md

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 5: Push HEAD to origin/main + tag + push tag**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git push origin HEAD:main
git tag -a v8.2.0 -m "v8.2.0 — Phase 1 of theme + companion plugin split"
git push origin v8.2.0
```

Expected: both pushes succeed; theme CI smoke-test workflow triggers on main push.

- [ ] **Step 6: Verify remote state**

```bash
git ls-remote origin refs/heads/main refs/tags/v8.2.0
```

Expected: two refs returned; main points at the just-created commit; v8.2.0 tag exists.

---

### Task 15: Live deployment + verification (USER-RUN, not agent)

**Files:** None — this task is performed by the maintainer in WP admin + browser.

The plan ends in code. Live deployment requires browser actions on `https://juanlentino.com/wp-admin/`. Listed here so the engineer doesn't claim the work is "shipped" before the live site reflects it.

- [ ] **Step 1: User downloads plugin zip**

Visit `https://github.com/juanlentino/signal-and-noise-tools/archive/refs/tags/v1.0.0.zip` and save the zip.

- [ ] **Step 2: User installs plugin**

In WP admin: *Plugins* → *Add New* → *Upload Plugin* → choose `signal-and-noise-tools-1.0.0.zip` → *Install Now* → *Activate Plugin*.

- [ ] **Step 3: User verifies plugin activated cleanly**

- No fatal-error white screen.
- *Plugins* page shows *Signal & Noise Tools* with version 1.0.0 listed as Active.
- *Appearance* admin menu now shows TWO *Signal & Noise* entries (one from theme, one from plugin) — this is expected during the duplication window.

- [ ] **Step 4: User installs theme v8.2.0**

In WP admin: *Dashboard* → *Updates* (or *Themes*) → click *Update* on the Signal & Noise theme tile. (Per the latency conversation: expect ~5–10 min for the update offer to appear depending on Cloudways cron interval. Hitting *Dashboard → Updates → Check Again* can shortcut this.)

- [ ] **Step 5: User verifies post-install state**

- *Appearance* menu shows only ONE *Signal & Noise* entry (the plugin's).
- *Appearance → Signal & Noise* options page loads, all 4 tabs render, *Purge All Caches* button works (verify by checking the success notice mentions a count).
- *Heal Templates* button works (success or "all in sync" notice).
- *Check Now* button works (Update check complete notice).
- Plausible dashboard widgets render with last-known cached data.
- One REST endpoint smoke: `curl -u <admin>:<pass> https://juanlentino.com/wp-json/signal-noise/v1/plausible/stats` returns the JSON shape.
- Public site renders normally; no fatal errors in `/wp-content/debug.log` if `WP_DEBUG_LOG` is on.

- [ ] **Step 6: If something is broken**

- *Appearance → Signal & Noise* options page 404s or fatals → check `Plugins` page; if `signal-and-noise-tools` is inactive, reactivate.
- Plugin won't activate (PHP error on activation) → check error message; most likely a missing function call from the contract surface. Fix in plugin code, push patch v1.0.1.
- Public site broken → revert theme to v8.1.1 via WP CLI or SFTP rollback of `style.css` Version field + restore the deleted `inc/*.php` files from git. Plugin stays activated (its modules continue serving).

---

## Out-of-band post-flight (user runs after merge)

These are NOT part of the agent's implementation but are the standard post-release operations per CLAUDE.md:

- Push the theme branch to remote (`git push`) — done by Task 14's Step 5.
- Tag pushed in Task 14's Step 5.
- Plugin v1.0.0 zip downloaded + installed in WP admin (Task 15 Step 1–3).
- Theme v8.2.0 update clicked in WP admin (Task 15 Step 4).
- Verify (Task 15 Step 5).
