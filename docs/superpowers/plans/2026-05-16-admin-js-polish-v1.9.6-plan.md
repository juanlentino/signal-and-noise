# Admin JS Polish v1.9.6 Implementation Plan — Identity tab dirty-tracking + sameAs "+ Add" button

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add two JS-driven UX polish features to the Identity tab — dirty-tracking on the sticky save bar (shows "N unsaved change(s)" when fields differ from initial values) and a "+ Add another profile URL" button replacing the always-shown empty trailing input.

**Architecture:** New `assets/admin.js` (~150 LOC vanilla JS, no dependencies). Enqueued only on SN admin pages via the same `wp_enqueue_scripts` hook guard that already loads `assets/admin.css`. All JS scoped to `.sn-identity-form` so it doesn't touch other forms. Server-side markup gains `<noscript>` graceful-degradation block: with JS disabled, the page falls back to the v1.9.5 behaviour (single trailing empty input).

**Tech Stack:** WordPress (PHP), vanilla JS (no jQuery, no build pipeline), CSS variables (already established).

**Spec:** Design inlined in the chat where the user approved Option 2 (dirty-tracking + "+ Add another" button); no separate spec file.

**Working directory:** `/Users/juanlentino/Projects/signal-and-noise-tools` (branch `main`; auto-deploys on tag push)

---

## File Structure

| File | Status | Responsibility | Net LOC |
|---|---|---|---|
| `assets/admin.js` | NEW | Dirty-tracking + addRow handler. Two functions, one IIFE. Targets `.sn-identity-form` only. | +150 |
| `assets/admin.css` | MODIFY | Add `.sn-add-row-btn` button styling + `[data-dirty="true"]` hint variant + animation for new row | +25 |
| `inc/admin-page.php` | MODIFY | Enqueue script; replace trailing empty sameAs input with `+ Add another` button + `<noscript>` fallback | ±20 |
| `signal-and-noise-tools.php` | MODIFY | Version 1.9.5 → 1.9.6; SNT_VERSION bump | ±2 |
| `CHANGELOG.md` | MODIFY | Prepend v1.9.6 entry | +20 |

Total: ~217 LOC added. 4 atomic commits + tag.

No test files — the plugin has no JS test harness and adding one for 150 LOC isn't justified. Verification is post-deploy smoke test (user-side).

---

## Task 1: Create assets/admin.js

**Files:**
- Create: `/Users/juanlentino/Projects/signal-and-noise-tools/assets/admin.js`

- [ ] **Step 1: Write the full JS module**

Create the file with this content:

```javascript
/**
 * Signal & Noise Tools — admin JS.
 *
 * Loaded only on SN admin pages (same hook guard as assets/admin.css).
 * Pure vanilla JS, no jQuery, no build step. Keeps the zero-build-
 * pipeline architecture of the rest of the plugin.
 *
 * Two responsibilities — both scoped to the Identity tab via
 * `.sn-identity-form`:
 *
 *   1. dirty-tracking on the sticky save bar
 *      — snapshots all input values on load; on any change, compares
 *        current vs initial; updates the save bar hint to show
 *        "N unsaved change(s)" or "No unsaved changes"
 *
 *   2. "+ Add another profile URL" button
 *      — clones the existing input template into a new row above the
 *        button. Submission still works as social_same_as[] array.
 *
 * Added in v1.9.6 (2026-05-16).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.querySelector( '.sn-identity-form' );
		if ( ! form ) {
			return;
		}

		initDirtyTracking( form );
		initAddRowButton( form );
	} );

	/**
	 * Dirty-tracking: snapshot initial values, listen for input changes,
	 * update the save bar hint with the count of changed fields.
	 */
	function initDirtyTracking( form ) {
		var hint = form.querySelector( '.sn-savebar-hint' );
		if ( ! hint ) {
			return;
		}

		var initial = snapshotForm( form );
		hint.dataset.cleanCopy = hint.textContent;

		var update = function () {
			var current = snapshotForm( form );
			var changed = countChanges( initial, current );
			if ( changed === 0 ) {
				form.removeAttribute( 'data-dirty' );
				hint.textContent = hint.dataset.cleanCopy;
			} else {
				form.setAttribute( 'data-dirty', 'true' );
				hint.textContent = changed === 1
					? '1 unsaved change'
					: changed + ' unsaved changes';
			}
		};

		form.addEventListener( 'input', update );
		// Dynamic rows added via the "+ Add" button also fire 'input'
		// when typed in, so update naturally catches them. We also need
		// to refresh the initial snapshot if a new row is added empty —
		// otherwise adding a row appears "dirty" before any typing.
		form.addEventListener( 'sn:row-added', function () {
			initial = snapshotForm( form );
			update();
		} );
	}

	/**
	 * Snapshot all form input/textarea/select values into a plain
	 * key+index map. Repeated input names (e.g. social_same_as[]) get
	 * stable per-index keys so adding/removing rows shows accurately.
	 */
	function snapshotForm( form ) {
		var snap = {};
		var counters = {};
		var inputs = form.querySelectorAll( 'input, textarea, select' );
		for ( var i = 0; i < inputs.length; i++ ) {
			var el = inputs[ i ];
			if ( el.type === 'hidden' || el.type === 'submit' || el.disabled ) {
				continue;
			}
			var name = el.name || '';
			if ( ! name ) {
				continue;
			}
			counters[ name ] = ( counters[ name ] || 0 ) + 1;
			var key = name + '#' + counters[ name ];
			snap[ key ] = el.type === 'checkbox' ? el.checked : el.value;
		}
		return snap;
	}

	/**
	 * Count keys that differ between two snapshots. Keys present in only
	 * one snapshot count as changed (covers row add/remove cases).
	 */
	function countChanges( a, b ) {
		var changed = 0;
		var keys = {};
		Object.keys( a ).forEach( function ( k ) { keys[ k ] = true; } );
		Object.keys( b ).forEach( function ( k ) { keys[ k ] = true; } );
		Object.keys( keys ).forEach( function ( k ) {
			if ( a[ k ] !== b[ k ] ) {
				changed++;
			}
		} );
		return changed;
	}

	/**
	 * "+ Add another profile URL" button handler. Clones the sameAs
	 * input template into a new row above the button. New input is
	 * empty and immediately focused.
	 */
	function initAddRowButton( form ) {
		var btn = form.querySelector( '.sn-add-row-btn' );
		if ( ! btn ) {
			return;
		}
		var container = form.querySelector( '.sn-sameas' );
		if ( ! container ) {
			return;
		}

		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var input = document.createElement( 'input' );
			input.type = 'url';
			input.name = 'social_same_as[]';
			input.value = '';
			input.placeholder = 'https://...';
			container.insertBefore( input, btn );
			input.focus();

			// Custom event so the dirty-tracker can refresh its snapshot
			// (otherwise an empty new row reads as "dirty" before typing).
			form.dispatchEvent( new CustomEvent( 'sn:row-added' ) );
		} );
	}
} )();
```

- [ ] **Step 2: Verify file shape**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
wc -l assets/admin.js && \
grep -c 'function ' assets/admin.js
```

Expected: ~115 LOC, 4 function declarations (the IIFE wrapper plus 3 named helpers).

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add assets/admin.js && \
git commit -m "admin.js: dirty-tracking + addRow handlers for Identity tab

New file. Vanilla JS, no jQuery, no build pipeline (preserves the
plugin's zero-build architecture).

Two responsibilities, both scoped to .sn-identity-form:

1. Dirty-tracking — snapshots input values on DOMContentLoaded,
   listens for 'input' events, updates the save bar hint text to
   show 'N unsaved change(s)' or 'No unsaved changes'. Sets
   data-dirty='true' on the form so CSS can style the dirty state.

2. addRow handler — '+ Add another profile URL' button clones a
   fresh empty sameAs input above the button, focuses it, fires
   a custom sn:row-added event so the dirty-tracker can refresh
   its baseline (otherwise the new empty row reads as dirty
   before any typing).

Snapshots use per-input-name index counters so multi-value fields
(social_same_as[]) compare cleanly per-row across add/remove.

Markup change in inc/admin-page.php (next commit) replaces the
v1.9.5 always-shown trailing empty input with the button + a
<noscript> single-row fallback for no-JS graceful degradation."
```

---

## Task 2: Add CSS for the new button + dirty state

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/assets/admin.css`

- [ ] **Step 1: Read the end of admin.css to find insertion point**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
tail -10 assets/admin.css
```

Expected: file ends with the `.sn-page-subtitle` rule from v1.9.1 (last addition).

- [ ] **Step 2: Append the new CSS rules**

Append these rules to `assets/admin.css`:

```css

/* ─── Add-row button + dirty state (v1.9.6) ──────────────────── */

.sn-add-row-btn {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 6px 12px;
	background: transparent;
	border: 1px dashed var(--sn-border);
	border-radius: var(--sn-radius);
	color: var(--sn-text-muted);
	font-size: 0.85em;
	cursor: pointer;
	transition: border-color 0.12s ease, color 0.12s ease, background 0.12s ease;
	align-self: flex-start;
	margin-top: var(--sn-space-1);
}

.sn-add-row-btn:hover,
.sn-add-row-btn:focus {
	border-color: var(--sn-link);
	color: var(--sn-link);
	background: rgba(34,113,177,0.04);
	outline: none;
}

.sn-add-row-btn::before {
	content: '+';
	font-size: 1.15em;
	line-height: 1;
	font-weight: 600;
}

/* Dirty state — sticky save bar hint takes on a muted accent so
   "N unsaved changes" reads as actionable, not informational. */
.sn-identity-form[data-dirty="true"] .sn-savebar-hint {
	color: var(--sn-text);
	font-weight: 500;
}

.sn-identity-form[data-dirty="true"] .sn-savebar-hint::before {
	content: '●';
	display: inline-block;
	color: var(--sn-warn);
	margin-right: 6px;
	font-size: 0.75em;
	transform: translateY(-1px);
}

/* Newly-added sameAs row animation — soft fade-in so the user
   visually catches the addition without it feeling jarring. */
.sn-sameas input.sn-row-fresh {
	animation: sn-fade-in 0.18s ease;
}

@keyframes sn-fade-in {
	from { opacity: 0; transform: translateY(-4px); }
	to   { opacity: 1; transform: translateY(0); }
}
```

- [ ] **Step 3: Verify shape**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
wc -l assets/admin.css && \
grep -cE '^\.sn-add-row-btn|^\.sn-identity-form\[data-dirty|^@keyframes sn-fade-in' assets/admin.css
```

Expected: file grew by ~40 LOC; 3 new top-level rules added.

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add assets/admin.css && \
git commit -m "admin.css: button + dirty state + row-fresh animation (v1.9.6)

- .sn-add-row-btn: dashed-border ghost button, hover lifts to the
  accent color. Used for the '+ Add another profile URL' control
  that replaces the trailing empty input in the sameAs section.
- .sn-identity-form[data-dirty=true] .sn-savebar-hint: dirty state
  styling on the sticky save bar hint. Adds a small amber dot
  prefix and bumps the text from muted to default color so the
  'N unsaved changes' message reads as actionable.
- @keyframes sn-fade-in + .sn-row-fresh: subtle 180ms fade for
  newly-added sameAs rows so the addition has a visual beat
  rather than appearing instantly."
```

---

## Task 3: Enqueue the script + restructure the sameAs markup

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/admin-page.php`

- [ ] **Step 1: Add wp_enqueue_script next to the existing wp_enqueue_style**

Find the `admin_enqueue_scripts` handler in `inc/admin-page.php`. It currently calls only `wp_enqueue_style`. Modify it:

```php
// REPLACE the existing handler body:
add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( in_array( $hook, sn_admin_page_hooks(), true ) ) {
		wp_enqueue_style(
			'sn-admin',
			SNT_URL . 'assets/admin.css',
			array(),
			SNT_VERSION
		);
	}
} );

// WITH:
add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( ! in_array( $hook, sn_admin_page_hooks(), true ) ) {
		return;
	}
	wp_enqueue_style(
		'sn-admin',
		SNT_URL . 'assets/admin.css',
		array(),
		SNT_VERSION
	);
	wp_enqueue_script(
		'sn-admin',
		SNT_URL . 'assets/admin.js',
		array(),
		SNT_VERSION,
		true // load in footer
	);
} );
```

The early-return refactor + footer-load (`$in_footer = true`) is the WP-canonical pattern for admin scripts that touch DOM after render.

- [ ] **Step 2: Replace the trailing empty sameAs input with the button + <noscript> fallback**

Find the sameAs render block inside the Identity tab. It currently ends with this empty trailing input:

```php
// One trailing empty row, styled subtly so it reads as "add another"
// rather than a forgotten dangling input.
echo '<div class="sn-sameas-empty">';
echo '<input type="url" name="social_same_as[]" value="" placeholder="Add another profile URL…">';
echo '</div>';
echo '</div>'; // .sn-sameas
```

Replace with:

```php
// "+ Add another" button — JS handler in assets/admin.js inserts a
// new <input> above the button on click. <noscript> fallback
// preserves the v1.9.5 single-trailing-input behaviour for users
// with JavaScript disabled.
echo '<button type="button" class="sn-add-row-btn" aria-label="Add another profile URL">Add another profile URL</button>';
echo '<noscript>';
echo '<input type="url" name="social_same_as[]" value="" placeholder="https://..." style="margin-top:6px;">';
echo '</noscript>';
echo '</div>'; // .sn-sameas
```

- [ ] **Step 3: Verify the edit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -nE "sn-add-row-btn|sn-sameas-empty|<noscript>" inc/admin-page.php
```

Expected: 1 match for `sn-add-row-btn` (the new button), 0 matches for `sn-sameas-empty` (the old wrapper is gone), 1 match for `<noscript>`.

Also verify the enqueue handler:

```bash
grep -nE "wp_enqueue_script.*sn-admin|wp_enqueue_style.*sn-admin" inc/admin-page.php
```

Expected: 2 lines — one style enqueue, one script enqueue, both with `'sn-admin'` handle.

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/admin-page.php && \
git commit -m "admin-page: enqueue admin.js + swap trailing input for + Add button

Adds wp_enqueue_script for assets/admin.js inside the existing
hook-guarded admin_enqueue_scripts handler. Script loads in the
footer (\$in_footer = true) so it runs after DOM is parsed. Same
hook guard and SNT_VERSION cache-buster as admin.css.

Replaces the v1.9.5 always-shown trailing empty sameAs input with
a '+ Add another profile URL' button. JS in admin.js handles clicks
by inserting a fresh <input> above the button. <noscript> block
keeps the v1.9.5 behaviour (single trailing input) when JS is
disabled — graceful degradation for the no-JS edge case."
```

---

## Task 4: Version bump + CHANGELOG + tag + push + verify

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/signal-and-noise-tools.php` (Version + SNT_VERSION)
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/CHANGELOG.md` (prepend entry)

- [ ] **Step 1: Bump Version + SNT_VERSION via sed**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
sed -i '' 's/^ \* Version:     1.9.5$/ * Version:     1.9.6/' signal-and-noise-tools.php && \
sed -i '' "s/define( 'SNT_VERSION', '1.9.5' );/define( 'SNT_VERSION', '1.9.6' );/" signal-and-noise-tools.php && \
grep "Version:\|SNT_VERSION" signal-and-noise-tools.php | head -2
```

Expected: both lines show `1.9.6`.

- [ ] **Step 2: Prepend CHANGELOG entry**

Open `CHANGELOG.md` and insert this block above the existing `## [1.9.5] - 2026-05-16` heading:

```markdown
## [1.9.6] - 2026-05-16

### Added
- **Identity tab dirty-tracking on the sticky save bar.** JS snapshots all form values on page load; on any field change, the save bar hint switches from default copy to "N unsaved change(s)" with a subtle amber dot prefix. Reverts cleanly when you type back to the original value. Scoped to `.sn-identity-form` only — Login (single field), Cloudflare, and Plausible already have inline saves where this is overkill.
- **"+ Add another profile URL" button** in the sameAs section, replacing the v1.9.5 always-shown trailing empty input. Click → JS clones a fresh empty `<input type="url">` row above the button, focuses it, fires a custom `sn:row-added` event so the dirty-tracker doesn't read the empty row as "dirty" before typing. `<noscript>` fallback preserves the v1.9.5 single-trailing-input behaviour for users with JS disabled.
- New `assets/admin.js` (~115 LOC vanilla JS, no jQuery, no build pipeline). Enqueued only on SN admin pages via the same hook-suffix guard as `admin.css`.

### Notes
- **Pure UX polish — no schema change, no server-side behaviour change.** The form submits identically: `social_same_as[]` array with one or more URLs, sanitized by `sn_settings_save()` (empty values filtered, valid URLs persisted).
- Zero-build-pipeline architecture preserved. The plugin still has no webpack / babel / npm pipeline; admin.js is hand-written vanilla JS that ships as-is.
- PATCH bump within `1.9.x` (counter at 6/7 of the per-minor cap).

## [1.9.5] - 2026-05-16
```

- [ ] **Step 3: Commit + tag + push**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add signal-and-noise-tools.php CHANGELOG.md && \
git commit -m "v1.9.6: Identity tab JS polish — dirty-tracking + add-row button

Pure UX polish on the Identity tab. New assets/admin.js (~115 LOC
vanilla JS, no jQuery, no build pipeline) ships two features:

1. Dirty-tracking on the sticky save bar — snapshots form values
   on load, updates hint to 'N unsaved change(s)' on any field
   change, reverts when typed back to initial.

2. '+ Add another profile URL' button replaces the v1.9.5 always-
   shown trailing empty input in the sameAs section. JS inserts a
   fresh empty input above the button on click. <noscript> fallback
   preserves the single-trailing-input behaviour for no-JS users.

No schema or server-side behaviour change. PATCH bump within 1.9.x." && \
git push origin main && \
git tag -a v1.9.6 -m "v1.9.6 — Identity tab JS polish" && \
git push origin v1.9.6
```

- [ ] **Step 4: Watch deploy + verify assets**

```bash
sleep 12 && \
gh run list --repo juanlentino/signal-and-noise-tools --workflow=deploy.yml --limit 1 --json status,conclusion -q '.[0]' && \
echo "" && \
echo "=== admin.js asset reachable + cache-busted ===" && \
curl -sSI "https://juanlentino.com/wp-content/plugins/signal-and-noise-tools/assets/admin.js?ver=1.9.6" | head -3 && \
echo "" && \
echo "=== admin.css unchanged (still v1.9.6) ===" && \
curl -sSI "https://juanlentino.com/wp-content/plugins/signal-and-noise-tools/assets/admin.css?ver=1.9.6" | head -2
```

Expected:
- Deploy `success` in ~12s.
- admin.js: `HTTP/2 200`, `content-type: application/javascript` (or `text/javascript`).
- admin.css: `HTTP/2 200`, `content-type: text/css`.

- [ ] **Step 5: User-side acceptance criteria (manual — `wps-hide-login` blocks automated admin checks)**

Walk through in a logged-in browser:

1. Visit `wp-admin/admin.php?page=sn-identity`.
2. Save bar at the bottom should show the default hint copy (whatever you left it at).
3. Type one character in Site Name → save bar hint should change to "1 unsaved change" with a small amber dot prefix.
4. Type back to the original → hint reverts to default, dot gone.
5. Scroll down to sameAs section → no more dangling empty input; instead a "+ Add another profile URL" button (dashed border, ghost styling).
6. Click the button → new empty `<input>` slides in above the button (subtle fade animation), focus jumps to it.
7. Type a URL → save bar hint shows "1 unsaved change" (the dirty tracker correctly ignores the empty row insertion itself, only flags the typed value).
8. Click Save → PRG redirect fires, page re-renders with success notice, save bar back to clean state.
9. Verify with JS disabled (browser devtools → Settings → Disable JavaScript → reload): sameAs section shows existing URLs + one trailing empty `<input>` (the `<noscript>` fallback). Add button doesn't render. Saving still works the v1.9.5 way.

---

## Rollback paths

**If the dirty-tracker reads as dirty on initial page load:**
- Cause: a field's value differs between PHP-rendered HTML and the JS snapshot — usually because of an autocomplete-driven browser fill or a hidden checkbox state.
- Fix: in `assets/admin.js` `snapshotForm()`, log the diff between two adjacent snapshots and identify which field. May need to filter out `autocomplete` fields explicitly.

**If the "+ Add another" button doesn't work:**
- Check the browser console for JS errors.
- Verify the script enqueued — view page source, look for `<script src=".../assets/admin.js?ver=1.9.6">` in the footer.
- Verify the form has class `sn-identity-form` and contains a `.sn-sameas` container — if these were renamed in the markup, the JS hooks find nothing.

**If newly-added rows don't submit:**
- Make sure the new input has `name="social_same_as[]"` (the JS sets this explicitly). Inspect the form on submit.
- Check `sn_settings_save()` in `inc/settings.php` line ~159 — it does `(array) ( $raw['social_same_as'] ?? array() )` and filters empty/invalid. New rows submit identically to existing rows.

**If you want to fully revert v1.9.6:**
- `git revert <v1.9.6 commit SHAs>` in the plugin repo.
- Push the revert; deploy fires; old v1.9.5 behaviour restored.

---

## Out of scope (deferred to later versions)

- Dirty-tracking on Login / Cloudflare / Plausible forms (their forms are short enough that inline Save buttons + no scrolling = no need for it).
- "Remove this URL" button per sameAs row (currently: clear the input value + save → empty rows get filtered server-side).
- Drag-to-reorder sameAs URLs.
- Confirmation dialog if you navigate away while dirty (browser-level `beforeunload`). Could add later but UX-wise often considered annoying.

---

## Self-review

- ✅ **Spec coverage:** both features (dirty-tracking, "+ Add" button) covered by Task 1 (JS) + Task 2 (CSS) + Task 3 (markup). `<noscript>` fallback covered in Task 3 Step 2. Enqueue covered in Task 3 Step 1.
- ✅ **No placeholders:** every code block is concrete and copy-pasteable.
- ✅ **Type consistency:** function names (`initDirtyTracking`, `initAddRowButton`, `snapshotForm`, `countChanges`) used consistently in Task 1. Event name `sn:row-added` used in both `initAddRowButton` (dispatch) and `initDirtyTracking` (listener).
- ✅ **Scope:** focused on Identity tab only. Doesn't touch other forms.
