# Accessibility — Signal & Noise

This doc is the project's **WCAG 2.1 AA accessibility baseline**. It records the measured contrast ratios for every brand color × background pairing in the current palette, identifies the tight margins, and lists watch thresholds for future palette tweaks. Created 2026-05-26 from Audit D's measurements (`docs/superpowers/specs/2026-05-26-audit-d-perf-a11y-findings.md` §4).

**Purpose:** the brutalist palette uses deliberate near-failure-edge contrast as a brand choice (Audit D flagged this as observation-grade — failing reflexively would be wrong). This doc makes the *measured ratios* visible so future palette nudges can be made consciously, not by accident.

## WCAG AA thresholds (refresher)

| Use case | Minimum ratio |
|---|---|
| Normal-size text | **4.5 : 1** |
| Large text (≥18pt or ≥14pt bold) | **3 : 1** |
| Non-text UI components (focus rings, form borders carrying state) | **3 : 1** |
| Decorative-only elements | no requirement |

[WCAG 2.1 SC 1.4.3 (Contrast Minimum, Level AA)](https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum)
[WCAG 2.1 SC 1.4.11 (Non-text Contrast, Level AA)](https://www.w3.org/WAI/WCAG21/Understanding/non-text-contrast)

## Current palette — measured ratios

Measured against `theme.json` v9.4.5 palette. Color tokens are defined in `theme.json` under `settings.color.palette` and exposed as CSS custom properties `--wp--preset--color--<slug>`.

### Primary text pairings

| Foreground | Background | Hex pair | Ratio | AA verdict | Notes |
|---|---|---|---|---|---|
| `bone` | `void` | `#000000` on `#ffffff` | **21.00 : 1** | ✅ PASS | Maximum contrast — primary body text |
| `bone` | `asphalt` | `#000000` on `#f5f5f5` | **19.26 : 1** | ✅ PASS | Primary text on card surfaces |
| `rust` | `void` | `#666666` on `#ffffff` | **5.74 : 1** | ✅ PASS | Secondary text |
| `rust` | `asphalt` | `#666666` on `#f5f5f5` | **5.27 : 1** | ✅ PASS | Secondary text on cards |
| `blood` | `void` | `#e00404` on `#ffffff` | **5.01 : 1** | ✅ PASS | Brand accent — main link / heading color |
| `blood` | `asphalt` | `#e00404` on `#f5f5f5` | **4.60 : 1** | ⚠️ PASS — tight (0.10 margin) | Brand accent on card surfaces — **watch this** |

### Hover-state pairings (non-default)

| Foreground | Background | Hex pair | Ratio | AA verdict | Notes |
|---|---|---|---|---|---|
| `signal` | `void` | `#ff4c47` on `#ffffff` | **3.29 : 1** | ⚠️ FAIL normal / ✅ PASS large | Hover-state for blood-coloured links. Underline-on-hover restores non-color affordance per WCAG 1.4.1 (Use of Color) — color is NOT the sole means of conveying the state change. |
| `signal` | `asphalt` | `#ff4c47` on `#f5f5f5` | **3.02 : 1** | ⚠️ FAIL normal / ✅ PASS large | Same caveat. |

### Plugin admin notice colors (companion plugin context)

Used by `signal-and-noise-tools` in `wp-admin` notices.

| Foreground | Background | Hex pair | Ratio | AA verdict |
|---|---|---|---|---|
| `#dc3232` | `void` | red on `#ffffff` | **4.62 : 1** | ✅ PASS |
| `#d63638` | `void` | red on `#ffffff` | **4.73 : 1** | ✅ PASS |

### Non-text / decorative

| Element | Color | Used for | Ratio | Verdict |
|---|---|---|---|---|
| Hairline borders, `<hr>` separators, scrollbar thumb | `concrete` (`#d9d9d9`) on `void` | Decorative only — does NOT convey state | **1.41 : 1** | ✅ Acceptable per WCAG 1.4.11 (applies only to "informational" UI). These elements are purely visual structure, not state indicators. |

## Watch thresholds

The palette has **two tight margins** that future tweaks could push under AA. Document any palette changes that affect either pair.

### Watch 1: `blood` on `asphalt` (currently 4.60 : 1)

**Sensitivity:** 0.10 above the 4.5 : 1 threshold. Any of these would push it under:

- `--asphalt` darkening from `#f5f5f5` toward `#eaeaea` (already at 4.36 : 1)
- `--blood` lightening from `#e00404` toward `#e22020` or `#e63838`
- Any tonal shift that reduces the lightness gap

**Impact if it fails:** the blood-coloured brand accent inside card surfaces (which is the most common visual hierarchy pattern across the theme) becomes WCAG-non-compliant for normal text. Headings inside cards would specifically be affected.

### Watch 2: `signal` hover state (currently 3.29 : 1 on `void`)

**Sensitivity:** already below normal-text AA at 3.29 : 1; passes only as large text (≥18pt or ≥14pt bold).

**Current usage:** applied only to hover state of links, where it appears at body-paragraph font sizes (~16px = ~12pt, NOT large by WCAG definition). The reason it ships AA-clean today: **WCAG 1.4.1 (Use of Color)** is satisfied because hover ALSO triggers an underline (in `assets/css/base.css:97-105` and equivalents). Color is not the sole affordance.

**Impact if it fails:** if the underline is ever removed from the hover state for `signal`-coloured links, the color-only signal would fail WCAG 1.4.1 and 1.4.3 simultaneously.

**Don't break the underline-on-hover convention** without re-evaluating contrast.

## Verification recipe

When adding or modifying any palette token, re-run the measurement:

1. **Identify the new/changed pairing.** What foreground × background combinations will the new color appear on?
2. **Compute the ratio.** Use a tool like the [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/) or [oddbird.net/contrast](https://oddbird.net/2022/05/19/contrast-checker/). Both report the luminance ratio.
3. **Check against thresholds:**
   - Normal text (everything not large): must hit 4.5 : 1
   - Large text (≥18pt or ≥14pt bold): must hit 3 : 1
   - Non-text UI carrying state (focus rings, error borders): must hit 3 : 1
   - Decorative only: no requirement
4. **If sub-AA:**
   - Document in the same brand-choice-vs-fix framing the audit used. Is this an intentional brutalist accent? Then ensure a non-color affordance exists (underline, weight, icon, position).
   - Add it to the **Watch thresholds** section above.

## Audits + measurements history

| Date | Audit | Outcome |
|---|---|---|
| 2026-05-26 | [Audit D — Perf + A11y](superpowers/specs/2026-05-26-audit-d-perf-a11y-findings.md) | 🟢 GREEN. Every text pairing passes AA. Tightest margins documented above. |

## Related files

- `theme.json` — palette source of truth (`settings.color.palette` array)
- `assets/css/critical.css` — uses `var(--wp--preset--color--<slug>)` throughout
- `assets/css/base.css:81-130` — skip-link + focus-visible styling (WCAG 2.4.1 + 2.4.7)
- `assets/css/layout.css:290-297` — 44×44 touch target enforcement for social icons (WCAG 2.5.5)

## Beyond contrast — other a11y coverage in this codebase

Audit D verified the following as ✅ PASS (recorded here as a discoverable summary):

- **Focus visible** (WCAG 2.4.7 AA) — brand-coloured 2px `:focus-visible` outline on every interactive element via `assets/css/base.css:116-128`. Form inputs additionally get a border-color focus state.
- **Skip link** (WCAG 2.4.1 AA) — `inc/frontend-filters.php:21-23` injects a skip-to-content link as the first body element; styled off-screen until focused (`assets/css/base.css:81-100`).
- **Touch targets** (WCAG 2.5.5 AAA — exceeded, not required) — footer social icons explicitly meet 44×44px.
- **ARIA** — landmark roles + `aria-current` (since v4.4.4) + `aria-label` on icon-only links + `aria-hidden` on decorative elements all verified correct across templates + render callbacks.
- **Keyboard navigation** — footnote popover supports both pointer hover AND keyboard focus (since v9.4.5).
- **Reduced motion** — hero entrance, view transitions, `/notes` reveals, and (since v9.4.4) service-card + button hover transforms all honour `prefers-reduced-motion: reduce`.
- **Heading hierarchy** — clean on `/notes/` index. **One known content-side WCAG 1.3.1 failure on single-note posts** (h1→h3 skip in published notes); content fix recipe in `docs/superpowers/handoffs/2026-05-26-audits-c-d-cycle-shipped.md` §3.
