# Accessibility baseline

This doc records the **measured** contrast ratio of every brand colour pairing,
in **all three palettes this theme serves**, and says which pairings actually
occur in the CSS. Every number below is computed from source by
`tests/accessibility-doc-parity.php`, which fails if the doc and the palettes
disagree — so these figures cannot rot the way the previous version did.

Rewritten 2026-08-20. The version before it measured one palette (`root`),
predated dark mode, never mentioned High Contrast, and described itself as
covering "every brand color × background pairing in the current palette" —
singular — while the site was serving a variation it did not contain.

## The three palettes are two orthogonal axes

- **`root`** — `theme.json`. The default light variation.
- **`high-contrast`** — `styles/high-contrast.json`. **This is what the live
  site serves**; the activated variation lives in `wp_global_styles` and beats
  `theme.json`. Measure against this one first.
- **`dark`** — the `--wp--preset--color--*` override in
  `assets/css/critical.css`, under `:root[data-theme="dark"]` and under
  `prefers-color-scheme`.

`root`/`high-contrast` are **variations**; `dark` is a **scheme** that overrides
whichever variation is active. A High Contrast reader on a dark OS gets dark,
not a blend — so all three must clear independently.

## Measured ratios

Normal text needs 4.5 : 1; text at 18.66px bold or 24px+ needs 3 : 1
("large-only" below). Non-text marks that convey state need 3 : 1 (WCAG 1.4.11).

### `root` — default light variation

| palette | foreground | background | resolved | ratio | verdict |
|---|---|---|---|---|---|
| `root` | `bone` | `void` | `#000000` on `#ffffff` | **21.00 : 1** | PASS |
| `root` | `bone` | `asphalt` | `#000000` on `#f5f5f5` | **19.26 : 1** | PASS |
| `root` | `rust` | `void` | `#666666` on `#ffffff` | **5.74 : 1** | PASS |
| `root` | `rust` | `asphalt` | `#666666` on `#f5f5f5` | **5.27 : 1** | PASS |
| `root` | `blood` | `void` | `#e00404` on `#ffffff` | **5.01 : 1** | PASS |
| `root` | `blood` | `asphalt` | `#e00404` on `#f5f5f5` | **4.60 : 1** | PASS |
| `root` | `signal` | `void` | `#ff4c47` on `#ffffff` | **3.29 : 1** | large-only |
| `root` | `signal` | `asphalt` | `#ff4c47` on `#f5f5f5` | **3.02 : 1** | large-only |
| `root` | `void` | `blood` | `#ffffff` on `#e00404` | **5.01 : 1** | PASS |
| `root` | `bone` | `blood` | `#000000` on `#e00404` | **4.19 : 1** | large-only |

### `high-contrast` — **the served palette**

| palette | foreground | background | resolved | ratio | verdict |
|---|---|---|---|---|---|
| `high-contrast` | `bone` | `void` | `#000000` on `#ffffff` | **21.00 : 1** | PASS |
| `high-contrast` | `bone` | `asphalt` | `#000000` on `#e0e0e0` | **15.91 : 1** | PASS |
| `high-contrast` | `rust` | `void` | `#333333` on `#ffffff` | **12.63 : 1** | PASS |
| `high-contrast` | `rust` | `asphalt` | `#333333` on `#e0e0e0` | **9.57 : 1** | PASS |
| `high-contrast` | `blood` | `void` | `#e00404` on `#ffffff` | **5.01 : 1** | PASS |
| `high-contrast` | `blood` | `asphalt` | `#e00404` on `#e0e0e0` | **3.80 : 1** | large-only |
| `high-contrast` | `signal` | `void` | `#ff4c47` on `#ffffff` | **3.29 : 1** | large-only |
| `high-contrast` | `signal` | `asphalt` | `#ff4c47` on `#e0e0e0` | **2.49 : 1** | FAIL |
| `high-contrast` | `void` | `blood` | `#ffffff` on `#e00404` | **5.01 : 1** | PASS |
| `high-contrast` | `bone` | `blood` | `#000000` on `#e00404` | **4.19 : 1** | large-only |

### `dark`

| palette | foreground | background | resolved | ratio | verdict |
|---|---|---|---|---|---|
| `dark` | `bone` | `void` | `#ffffff` on `#0a0a0a` | **19.80 : 1** | PASS |
| `dark` | `bone` | `asphalt` | `#ffffff` on `#171717` | **17.93 : 1** | PASS |
| `dark` | `rust` | `void` | `#9e9e9e` on `#0a0a0a` | **7.39 : 1** | PASS |
| `dark` | `rust` | `asphalt` | `#9e9e9e` on `#171717` | **6.69 : 1** | PASS |
| `dark` | `blood` | `void` | `#ff4c47` on `#0a0a0a` | **6.01 : 1** | PASS |
| `dark` | `blood` | `asphalt` | `#ff4c47` on `#171717` | **5.44 : 1** | PASS |
| `dark` | `signal` | `void` | `#ff6b66` on `#0a0a0a` | **7.12 : 1** | PASS |
| `dark` | `signal` | `asphalt` | `#ff6b66` on `#171717` | **6.44 : 1** | PASS |
| `dark` | `void` | `blood` | `#0a0a0a` on `#ff4c47` | **6.01 : 1** | PASS |
| `dark` | `bone` | `blood` | `#ffffff` on `#ff4c47` | **3.29 : 1** | large-only |

## Which of these actually occur

A ratio is a fact about the palette. Whether a reader ever meets it is a fact
about the CSS, and the two are not the same — the previous version of this doc
listed `signal` as "the hover-state for blood-coloured links", which stopped
being true without anything noticing.

| pairing | occurs? | evidence |
|---|---|---|
| `signal` as text | **No** | `signal` appears twice in `assets/css/*.css`, both as `background`. Its 2.49 : 1 against High Contrast `asphalt` is therefore not reachable today. |
| `blood` text on `asphalt` | **Not today** | Four rules paint `blood` text (post-closing link, drop cap, footnote sup, footnote marker); none is inside the four asphalt-backed pattern blocks. A live scan of 12 notes on 2026-08-20 found **zero** asphalt-backed blocks rendered. |
| `void` on `blood` | Yes | Filled chips, the command palette's active row, `kbd` caps. 5.01 : 1 light, 6.01 : 1 dark. |
| `bone` on `blood` | Yes, large only | 4.19 : 1 light. Used for large/short labels only. |

## Watch thresholds

**`blood` on `asphalt` is the tight one, and it is tighter than it looks.**
`root` measures 4.60 : 1 — a 0.10 margin over AA. **High Contrast measures
3.80 : 1, already below AA**, and High Contrast is what ships. It is not a live
defect only because nothing currently puts blood text on an asphalt ground —
one authored pull-quote containing a link changes that, and **no test can catch
it**, because whether one element sits inside another is a fact about the HTML,
not the stylesheet.

If that combination is ever wanted, the fix is the one the companion plugin
already uses: an explicit ink token for the emphasis red rather than `blood`
itself.

## What enforces this

| check | what it covers |
|---|---|
| `tests/accessibility-doc-parity.php` | **this document** — every ratio recomputed from `theme.json`, `styles/high-contrast.json` and `critical.css`; fails on any drift |
| `tests/contrast-baseline.php` | the palette's own pairings, source-level |
| `tests/dark-mode.php` | the dark token layer, and panel ink against the panel in both schemes |
| `tests/front-end-css-contrast.php` | **every ink/surface pair in every stylesheet, per palette**, plus 3 : 1 for focus rings and state marks |
| `tests/front-end-css-inverts.php` | no stylesheet paints a hardcoded colour |
| `tests/forced-colors.php` | forced-colors / Windows High Contrast behaviour |

## What none of them can see

- **Which element sits inside which.** Anything not declaring its own background
  is measured against the page ground. A nested surface must be declared in
  `front-end-css-contrast.php`'s `$on_surface`, and an undeclared one is
  measured against the wrong thing silently.
- **Authored content.** A link inside a pull-quote is HTML, not CSS.
- **Opacity, blend modes, background images.**
- **Reachability** — dead CSS is checked and passes.

Non-text contrast, opacity and reachability were listed as out of scope here
until 2026-08-20; the first is now covered for focus rings and state marks, the
other two are not.
