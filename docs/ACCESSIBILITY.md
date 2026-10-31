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
one authored pull-quote containing a link changes that, and **no source-level
test can catch it**, because whether one element sits inside another is a fact
about the HTML, not the stylesheet. The live check (`contrast.yml`) catches it
on a page it samples; on any other page it stays invisible.

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
| `tests/links-in-dim-text.php` | links inside dimmed text (gray paragraphs, sidenotes, the resume rail) are underlined at rest, not marked by color alone (WCAG 1.4.1) |
| `assets/css/base.css` reduced-motion reset | under `prefers-reduced-motion: reduce`, every animation and transition is off, pseudo-elements included |
| `.github/workflows/contrast.yml` | **the rendered site**: on every push to main, daily at 06:41 UTC and on demand, `tools/contrast-ci.mjs` reads the sitemap's pages, its two newest notes and one tag archive, loads `tools/contrast-computed.js` into each with Playwright, and fails on text below AA or on a link in running text that is neither underlined nor 3:1 against its surrounding text (WCAG 1.4.1). It skips what it cannot measure: text over background images or gradients, pseudo-element, canvas and SVG text, single-character text, `aria-hidden` subtrees, and pages behind a login (the header of `tools/contrast-computed.js` lists them). A run that reaches measurement ends with a `contrast-summary` notice, which the plugin's Health report reads |

## What the source checks cannot see

`contrast.yml` measures computed colors on the rendered page, so on the pages it
samples it does see nesting, opacity chains and authored content, within the
exclusions above. The list below
is what the source-level tests above cannot see, and what a page outside the
sample can still hide.

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

## Keyboard, names and targets (2026-10)

- **Single-key shortcuts can be turned off (WCAG 2.1.4).** `/` opens the search palette on every page (`assets/js/command-palette.js`). `j`, `k` and `?` work on single notes only (`assets/js/keyboard-nav.js`). Two controls turn all four off:
  - a checkbox in the `?` cheat-sheet;
  - a "Turn off / Turn on single-key shortcuts" row in the palette, reachable on every page with Cmd/Ctrl-K or the Search button.

  The choice is one localStorage key, `sn-single-key-shortcuts` = `off`. There is no cookie and no identifier. Every handler reads the key at keydown time. Shortcuts are on by default, and storage that throws fails safe to on. Pinned by `tests/js/single-key-shortcuts.test.cjs`.
- **The theme toggle's name starts with its visible word (WCAG 2.5.3).** The visible text is the state ("Light"/"Dark"). The name is that word, then the action: "Light: switch to dark theme". Pinned by `tests/theme-toggle-directives.php`.
- **Standalone targets are at least 24px (WCAG 2.5.8).** The /notes/ row tag links use `min-height: 24px` (`tests/target-size.php`). Links inside prose are exempt.
- **The header nav marks the current page** with `aria-current="page"` (`inc/nav-current.php`, `tests/nav-current.php`).
- **The 11px floor is scanned statically** across every theme stylesheet (`tests/type-floor.php`). em and % sizes cannot be resolved statically, so the em-sized footnote marker is pinned by name.

## Reading aids, motion and decoration (2026-10)

What theme 2026.10.11 added, and how each piece stays usable.

**Semantics**

- **Timeline and checklist** are core lists. The timeline's dates are `<strong>` text and its markers are decoration drawn in CSS. The checklist's boxes are CSS borders, not inputs: nothing is ticked on screen and nothing is stored. Both keep native list semantics, so a screen reader counts the items.
- **Key term** (`signal-noise/term`) renders a real `<dl>` with one `<dt>` and one `<dd>`. Both are plain text through `esc_html`. A definition without a term renders as a paragraph, never an orphan `<dd>`. Pinned by `tests/term-block.php`.
- **Comparison table** (`is-style-sn-table`) has a header row. `inc/table-header-scope.php` adds `scope="col"` to its header cells on render, since the block editor never writes it. On a phone the table scrolls sideways inside its figure. Safari does not make a scroll box focusable on its own, so the same file gives the figure `tabindex="0"` and a name (the caption, or "Comparison table"). It sets no `role="region"`: a figure with a caption may not take that role (axe `aria-allowed-role`), and the tabindex is what makes it reachable; and `article.css` gives it the house focus ring (WCAG 2.1.1). Pinned by `tests/table-header-scope.php`.
- **Current page** in the header nav is `aria-current="page"` and a drawn underline, so it is never color alone. HOME is marked on the home page too (`tests/nav-current.php`).
- **Decoration stays out of the accessibility tree.** The tuning dial is `aria-hidden`. The home pulse and the 404 test card are pseudo-elements with no text, and the 404 digits are `aria-hidden`. The print colophon is hidden on screen and labeled "Printed record" on paper.

**Motion.** Every new animation runs only under `prefers-reduced-motion: no-preference`, and the `base.css` reset turns all animation off under `reduce`. Pinned by `tests/motion-pairing.php`.

| piece | motion | under reduce |
|---|---|---|
| channel change (page to page) | the new page wipes in top to bottom in eight 30ms steps, the old page out in lockstep | no view transition at all |
| target highlight | a `#heading` target's 2px rule fades in once | the rule is shown, no fade |
| timeline | each marker fills as its item scrolls into view | markers are drawn filled |
| tuning dial | the needle tracks scroll position | no dial |
| home pulse | the waveform draws in once, in steps | drawn at rest |
| 404 test card | the digits flicker once (two shallow opacity dips, under the three-flash threshold) | no flicker |

The blanket load-in fades (`fadeInUp` on groups, columns, images and covers, `fadeIn` on the header) were removed in 2026.10.11. A page reached directly or reloaded now appears at once. The home hero keeps its own entrance.

**Forced colors.** The timeline markers and the current-page underline take system colors, and so does the tuning dial's needle, so reading position still shows (`tests/forced-colors.php`). The home pulse and the 404 bars drop out, which leaves the content they decorate unchanged.

**Contrast.** Text in all four reading aids is `currentColor` or a palette token, so it inherits the palette's measured pairs above. The blood accent is non-text (the timeline markers, the term's rule, the nav underline): 5.0 : 1 on the light ground and 6.0 : 1 on the dark, against a 3 : 1 floor. A text-fragment highlight reuses the selection pair.


## Text spacing (WCAG 1.4.12), audited 2026-10-31

Passed on nine live pages (home, the notes index, a note, /stats, /resume, /music, /contact, /services, /about) at 1280px and 375px, with the criterion's overrides applied to every element: line height 1.5, letter spacing 0.12em, word spacing 0.16em, paragraph spacing 2em. No text was clipped by an `overflow: hidden` or `clip` box, none was cut to an ellipsis, and no page scrolled sideways. The pages were served as local copies (the site's own fonts and stylesheet, the analytics beacon removed so the audit counted no visits), and the detector was seen to fire on a deliberately clipped control box before its zero was trusted. Not covered: text overlapping text without clipping (checked by eye on /stats, the densest page, only), Safari Reader and Chrome Reading Mode.
