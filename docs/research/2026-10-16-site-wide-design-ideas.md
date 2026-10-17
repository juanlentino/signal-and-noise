# Site-wide design ideas in the transmission register (research, 2026-10-16)

**Ask:** ideas for the whole site, not only notes, that are innovative or fun as
*visual design*, in keeping with the brutalist, clinical, white-first,
void/bone/blood system and the "signal vs noise" motif. Research and proposals
only: nothing here is built, and the owner picks before anything is.

**Out of scope on purpose:** the FM tuning dial (being built in another session,
retiring the RAF progress script in `assets/js/article-toc.js`), and the two other
note-level concepts already shown (title tuning in from static, waveform section
rules).

**House constraints every idea below honors:** cookieless and nothing stored on
the device; no front-end JS for decoration (CSS first); `prefers-reduced-motion`
and `forced-colors` handled; 11px type floor; American English, no em dashes.
Two owner rules shape the table: *tell me before any site-wide change*, and *the
home page (Page 383) is off-limits without a said yes, even inside an approved
site-wide change*. Every idea carries a **bucket**:

- **A** ships without touching the home page or the shared chrome.
- **B** is site chrome (header, nav, links, type, transitions): needs a yes for
  the site-wide change AND a separate yes for the home page, or a scope that
  excludes `body.home`.

## What the theme already does (so nothing below re-proposes it)

Inventory of `assets/css/*.css` (16 sheets, ~7,650 lines), `styles/`, `templates/`,
`parts/`, and the 2026-09-11 capabilities survey.

| Already shipped | Where |
|---|---|
| Palette as a token layer, light + dark inversion, reader toggle, contrast-gated in CI | `critical.css`, `tests/dark-mode.php` |
| Two textures: SVG film grain (`body::before`, 0.035, blend flips multiply/screen) and a 2px scanline (`body::after`) | `critical.css`, `base.css` |
| `::selection` in blood | `critical.css`, `base.css` |
| Nav hover: blood 2px rule grows from the left (`a::after`, width transition) | `critical.css` ~406 |
| `aria-current="page"` on header links (front page deliberately skipped) | `inc/nav-current.php`; **no CSS consumes it yet** |
| Cross-document view transitions, shell named (`sn-header`, `sn-footer`, `sn-main`), per-note morph names, reduced-motion off | `article.css`, `tests/view-transition*.php` |
| Entrance fades: hero cascade, every `.wp-block-group` fades up, `/notes` staggered rise, blinking block cursor | `critical.css`, `layout.css`, `base.css`, `notes.css` |
| Keyframes `glitchFlicker` and `pulseGlow` defined with **no consumer anywhere** | `base.css` 170-187 |
| Catalog vocabulary: blood mono eyebrow, hairline section heads with counts, catalog numbers | `components.css` 660+ |
| `text-wrap` balance on display heads, `hanging-punctuation`, tabular numerals, drop caps on notes | `base.css`, `article.css` |
| Global reduced-motion kill (`*, ::before, ::after { animation/transition: none !important }`) | `base.css` 38-50 |
| `forced-colors` pins the focus ring to `Highlight` | `base.css` 222+ |
| Block styles: signal quote, hairline, eyebrow, epigraph, references, caption | `styles/blocks/` |

Not used anywhere yet: `animation-timeline` (the tuning dial will be the first),
anchor positioning, `text-box`, `::target-text`, `@starting-style`, `@property`.

Settled and not re-proposed (memory `index_policy`, the 2026-09-11 "not proposed"
list, owner rulings): hero negative space; a second brand mark anywhere (footer,
404, palette); duotone, gradients, lightbox, cover video; style variations as
reader choice; anything stored per reader; bigger type, cropped photos,
left-pinned pages.

## References that set the bar

- **Broadcast test cards** (BBC Test Card F, SMPTE color bars and PLUGE): the
  literal "no signal" image, built from a fixed set of calibrated swatches. The
  theme's palette is already a seven-swatch calibration set.
- **Ryoji Ikeda, *test pattern* / *datamatics*** (raster-noton, 2008): data
  rendered as black/white bars and binary, clinical and loud at once. The model
  for decoration that is also a measurement.
- **Teenage Engineering, Braun (Dieter Rams) product graphics:** instrument
  panels where every mark is a label, a scale or a state indicator, never
  ornament. The model for nav and focus states.
- **NASA Graphics Standards Manual (1975, Danne & Blackburn), Müller-Brockmann
  grid systems:** type sitting exactly on rules; optical alignment as a
  discipline. The model for the type-trim idea.
- **Print production marks** (crop and registration marks): the clinical way to
  say "this is the boundary of the thing".
- **brutalistwebsites.com** as a corpus: the good ones expose structure
  (rules, numbers, states) instead of hiding it; the bad ones add noise for its
  own sake. Every idea below exposes a state the site already has.

## Ranked proposals

Ranked by brand fit × reach, over cost + risk, preferring mechanisms the theme
does not use yet.

| # | Idea | Bucket | Mechanism | Cost | Tests to touch |
|---|---|---|---|---|---|
| 1 | **Tuned channel:** the current page carries the nav's blood rule permanently; on capable browsers one indicator slides to the hovered link | B, home already excluded by `nav-current.php` | `a[aria-current="page"]::after`; anchor positioning for the slide | XS static, S slide | `nav-current.php`, `critical-deferred-parity.php`, `front-end-css-contrast.php` |
| 2 | **Test card 404:** the house palette as a calibration bar strip behind "404", one flicker on arrival | A | CSS gradients on `.sn-404-digits`; reuses the orphaned `glitchFlicker` | XS | `contrast-baseline.php` (decorative, `aria-hidden` already) |
| 3 | **Channel change:** page navigation becomes a stepped raster wipe of `main`, header and footer hold still | B | swap the root crossfade for `::view-transition-new(sn-main)` with `clip-path` + `steps()` | XS | `view-transitions.php`, `motion-pairing.php` |
| 4 | **Hairline-true display type:** Bebas heads trimmed to cap height so they sit exactly on rules and eyebrows | B (or A scoped off `body.home`) | `text-box: trim-both cap alphabetic` | S (spacing re-tune) | visual baselines, `layout-width-system.php` |
| 5 | **Signal acquired:** arriving at a `#fragment` or a search engine's text fragment lights the target in house red, not UA yellow | A | `:target` + `::target-text` + `@starting-style` | XS | `motion-pairing.php` |
| 6 | **Header gate:** a 1px hairline appears under the fixed header once the page leaves the top | B | root `scroll()` timeline on a box-shadow | XS | `critical-deferred-parity.php` |
| 7 | **Noise floor:** the film grain recedes as the reader scrolls; signal rises out of noise | B | root `scroll()` timeline on `body::before` opacity | XS | `dark-mode.php` (grain blend) |
| 8 | **Crop-mark focus:** buttons and cards show four registration ticks outside the existing focus outline | B | `::before` corner gradients where the pseudo is free | M | `keyboard-nav.php`, `reduced-motion-important.php` |
| 9 | **Signal lock links:** prose links rest on a dotted underline (noise) and lock to solid on hover/focus | B | `text-decoration-style` + thickness | XS | `front-end-css-contrast.php` |

### 1. Tuned channel (current page in the nav)

**What it looks like.** On /about, the ABOUT label in the header carries the same
2px blood rule that today only appears on hover, at full width, all the time.
Hovering CONTACT slides that one rule across to CONTACT (it does not draw a second
one); leaving the nav slides it back to ABOUT. A single indicator on a dial, not
seven underlines.

**Where.** Header nav on every page except the front page (which
`inc/nav-current.php` already leaves unmarked, per the owner's rule). Mobile
overlay menu gets the static rule only.

**Mechanism.** Static layer (all browsers):
`.wp-block-navigation a[aria-current="page"]::after { width: 100%; }`. This closes
a real gap: the 2026.10 audit made the current page *announce* itself to screen
readers, but sighted readers still get no mark. Slide layer, behind
`@supports (anchor-name: --a)`: give the current link and `:hover`/`:focus-visible`
links `anchor-name: --sn-tuned` (the hovered one wins by order), hide the per-link
`::after`, and draw one `ul::before` with `position-anchor: --sn-tuned;
left: anchor(left); width: anchor-size(width); transition: left, width 0.3s`.
Anchor positioning is Baseline since January 2026 (Firefox 147, Safari 26,
Chrome 125).

**Cost.** XS for the static rule (two copies: `critical.css` and the deferred
sheet, pinned by `critical-deferred-parity.php`). S for the slide.

**Accessibility.** The rule is redundant with `aria-current`, never the only
signal. Reduced motion: the global kill removes the transition, so the rule
jumps instead of sliding. Forced colors: the blood background is remapped away;
add `@media (forced-colors: active) { ...::after { background: CanvasText; } }`.

**Why it fits.** It is the existing hover rule turned into a state indicator:
the instrument-panel idea (a mark that means "this channel is tuned"), no new
surface.

### 2. Test card 404

**What it looks like.** Behind the oversized concrete "404" sits a strip of
seven vertical bars, the house palette in order (void, asphalt, concrete, rust,
bone, blood, signal), with a thin PLUGE-style row of near-black steps under it and
the existing eyebrow "Error · 404 · No Signal" above. On arrival the digits
flicker once (the `glitchFlicker` keyframes that already exist and nothing uses),
then hold. In dark mode the bars follow the inverted tokens, so the card
recalibrates itself.

**Where.** `templates/404.html` only.

**Mechanism.** `background: linear-gradient(90deg, var(--wp--preset--color--void)
0 14.28%, ...)` on `.sn-404-digits` (already `aria-hidden`, decorative), plus a
one-shot `animation: glitchFlicker 0.6s steps(6) 1` under
`prefers-reduced-motion: no-preference`. No markup change, no new tokens.

**Cost.** XS, one file, no home or chrome exposure.

**Accessibility.** Decorative and hidden from assistive tech already; the h1
"SIGNAL LOST" and its contrast are untouched. Forced colors drop backgrounds,
which leaves today's 404. Reduced motion: no flicker.

**Why it fits.** The 404 already says "No Signal"; a test card is the image a
receiver shows when it has none. It also makes the palette visible as what it
is: a calibration set.

### 3. Channel change (page transitions)

**What it looks like.** Clicking from /music to /services: the header and the
fixed utility bar stay put (they already do), and the content area changes like a
CRT refresh, the new page revealed top to bottom in 8 hard steps over ~240ms,
with no crossfade softness.

**Where.** Every navigation in a browser with cross-document view transitions
(Chromium, Safari 18.2+); others navigate as today.

**Mechanism.** The opt-in and the `sn-main` name exist in `article.css`; this only
replaces the default animation:
`::view-transition-old(sn-main) { animation: none; }`
`::view-transition-new(sn-main) { animation: sn-raster 240ms steps(8) both; }`
with `@keyframes sn-raster { from { clip-path: inset(0 0 100% 0); } }`. The
per-note morph names keep working: named elements transition on their own.

**Cost.** XS, but it touches every page including home, so bucket B.

**Accessibility.** `article.css` already sets `navigation: none` under
`prefers-reduced-motion: reduce`. `steps()` avoids a smooth sweep, which is the
part vestibular guidance worries about; 240ms keeps it under the threshold for a
perceptible delay.

**Why it fits.** A channel change, literally; and it extends the existing
transition instead of adding a second one.

### 4. Hairline-true display type

**What it looks like.** Bebas Neue has tall built-in line boxes, so every display
head floats a few pixels off the hairline or eyebrow above it, and the gap differs
by size. Trimmed, each head's cap height sits exactly on its rule: eyebrow, 1px
hairline, then the cap line, measured. It reads like a specification sheet.

**Where.** Display heads on page templates, notes and 404 (`.sn-catalog-section`
heads, note hero, page titles). Scope with `body:not(.home)` to stay in bucket A
for the home hero; the hero is settled design.

**Mechanism.** `text-box: trim-both cap alphabetic` on the display selectors.
Baseline since August 2026 (Firefox 154, Safari 18.2, Chrome 133). Unsupported
browsers keep today's spacing exactly. No size change (owner rule: never make type
bigger).

**Cost.** S. Trimming removes space, so margins that were tuned against the
untrimmed box need a pass, page by page, with screenshots. Visual baselines move.

**Accessibility.** None at risk; spacing only. Zoom and reflow unaffected.

**Why it fits.** Clinical precision you feel more than see; the Müller-Brockmann
and NASA-manual discipline of type sitting on the rule.

### 5. Signal acquired (`:target` and text fragments)

**What it looks like.** Following a link to `/resume#experience`, or a search
result that deep-links with `#:~:text=`, the target heading gets a 2px blood rule
at its left that draws in once, and the highlighted passage is blood-tinted with
bone ink instead of the browser's yellow.

**Where.** Any page with anchors: notes (TOC, footnotes, references), /resume,
/uses, /provenance. Home has no in-page anchors, so it is unaffected in practice.

**Mechanism.** `::target-text { background: color-mix(in srgb,
var(--wp--preset--color--blood) 22%, transparent); color: inherit; }` and
`:target` headings with a left rule that starts at `scale: 1 0` via
`@starting-style`. (`::target-text` support: verify against MDN before build;
unsupported browsers keep the UA highlight.)

**Cost.** XS.

**Accessibility.** The tint must clear 4.5:1 with ink on top in both palettes;
the contrast suite will say. Reduced motion: the rule appears without drawing.
Forced colors: `::target-text` maps to `Mark`/`MarkText` automatically.

**Why it fits.** Arriving at a fragment is acquiring a signal; the house red
replaces a foreign color, the way the theme already owns `::selection`.

### 6. Header gate

**What it looks like.** At the top of any page the white header sits flush on the
white page, as now. Once the page scrolls past ~64px, a 1px concrete hairline
fades in under the header, the gate the content passes beneath.

**Where.** The fixed header, every page (bucket B).

**Mechanism.** `animation: sn-gate linear both; animation-timeline: scroll(root);
animation-range: 0 64px;` on `.sn-header`, animating `box-shadow: 0 1px 0
var(--wp--preset--color--concrete)` from transparent. Height and
`--sn-chrome-top` never change. `scroll-state(stuck)` does not apply (the header
is fixed, not sticky). Firefox still keeps `animation-timeline` behind a flag as
of 152, so Firefox shows no hairline, which is today.

**Cost.** XS, in both `critical.css` and `layout.css`. Coordinate with the
tuning-dial session, which introduces the theme's first scroll timeline.

**Accessibility.** The global reduced-motion kill makes the hairline absent, the
current look. Pure decoration.

**Why it fits.** The hairline is the theme's primary mark; this places one where
the page structure actually changes.

### 7. Noise floor

**What it looks like.** The grain is at its current strength at the top of every
page and settles to roughly half by one viewport down: the longer you read, the
cleaner the signal.

**Where.** Every page (bucket B). Mechanism: root scroll timeline on
`body::before` opacity from 0.035 to ~0.018. Cost XS. Reduced motion: static grain
(the global kill). Firefox: static grain. Fits the motif directly, but the effect
is subtle at 0.035, so it earns its place only if the owner wants the textures
to mean something.

### 8. Crop-mark focus

**What it looks like.** A focused button or card shows four small L-shaped blood
ticks just outside its corners, like print registration marks, in addition to the
existing outline.

**Where.** Buttons, the cmd-K trigger, catalog and disco cards. Not plain links:
their `::after` is already the nav rule. Mechanism: four `linear-gradient` corner
layers on a `::before` with `inset: -6px`, shown on `:focus-visible`. Cost M (each
component's pseudo-elements need an audit first). Accessibility: the outline
stays; ticks are additive. Forced colors keep the `Highlight` outline and drop
the ticks. Fits the print-production register, but it is the most code for the
least reach.

### 9. Signal lock links

Prose links rest on a 1px dotted underline and lock to a 2px solid one on hover
and focus. Bucket B, XS. Ranked last: a dotted underline conventionally means
`<abbr>` and slightly weakens link affordance in long notes, which works against
the accessibility rule. Listed so it is not re-proposed without that caveat.

## Not proposed, and why

- **Custom cursor, magnetic or glitch-on-hover text:** JS or constant motion for
  decoration; the reduced-motion and no-JS rules exclude them.
- **A "now on air" ticker or live frequency readout:** needs data or storage, and
  the tuning dial already owns the readout idea.
- **Phosphor or amber style variation:** style variations are not reader-facing
  here and were parked in the 2026-09-11 survey.
- **Halftone or scanline treatment on photos:** the photo rules (never crop,
  image-light) and the declined duotone.
- **Any brand mark on the test card or in the footer:** the brand appears once.

## Recommendation

**Build 1 and 2 first.** The tuned-channel nav finishes something the theme
already started (the current page is announced but not shown) using the rule it
already draws, with the home page already excluded by `nav-current.php`; it still
needs the owner's site-wide yes. The test card 404 is the fun one, touches one
template, needs no home or chrome approval, and finally gives the orphaned
`glitchFlicker` keyframes a consumer. Each is under a day with its tests.

Then **3 (channel change)** if the owner wants the motif in motion; it is the
highest-visibility change for the least code, and it needs both yeses.

Live mockups of 1, 2 and 4 (light and dark, reduced-motion aware, private
link): https://claude.ai/artifact/CK7NjCcR2QgMNpH7mT7EyP

## Sources

[web.dev, new to the web platform in August 2026](https://web.dev/blog/web-platform-08-2026) (text-box in Firefox 154) ·
[Chrome, CSS text-box-trim](https://developer.chrome.com/blog/css-text-box-trim) ·
[OddBird, anchor position area update](https://www.oddbird.net/2025/10/13/anchor-position-area-update/) ·
[Chrome, CSS scroll-state queries](https://developer.chrome.com/blog/css-scroll-state-queries) ·
[Mozilla Connect, scroll-driven animations status](https://connect.mozilla.org/t5/ideas/implement-css-scroll-driven-animations-animation-timeline/idc-p/119840) ·
[YCAM, Ryoji Ikeda *test pattern*](https://special.ycam.jp/datamatics/en/testpattern.html) ·
this repo: `inc/nav-current.php`, `assets/css/article.css` (view transitions),
`assets/css/base.css` (keyframes, reduced-motion kill, forced colors),
`docs/research/2026-09-11-core-capabilities-the-theme-does-not-use.md`.
