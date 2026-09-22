# JL mark: spec

Drawn to Bebas Neue. Stroke is 15.6% of cap height, which is what Bebas's own
stems measure at the same cap height (measured, not estimated: DM Mono 12.8%,
Bebas Neue 15.6%, Anton 15.8%, Archivo 800 25.0%).

## Geometry

```
viewBox      0 0 146 128
stroke-width 20
fill         none
caps/joins   butt / miter

<path d="M54 10V96A22 22 0 0 1 10 96"/>   J
<path d="M94 10V118H136"/>                L
```

| | |
|---|---|
| Cap height | 128 u |
| Stroke | 20 u — 15.6 % |
| Counter, J to L | 20 u — one stroke |
| Hook radius | 22 u |
| Terminal rise | 22 u |
| Bounding ratio | 146 : 128 |
| Grid module | 2 u |
| Clear space | one stroke (20 u) on all four sides |
| Min. size, screen | 16 px tall |
| Min. size, print | 5 mm tall |
| Min. full lockup | 150 px wide |

## Containers

- **Mark** — default off the page: favicon, OG image, letterhead, credits.
  The site header carries the wordmark instead (see Wordmark).
- **Stamp** — `rect x=6 y=6 w=188 h=188` stroke 12, mark inside at
  `translate(45 51.8) scale(0.753)`. Attribution, rights notices, credits.
- **Tile** — full-bleed 200×200 rect, mark inside at
  `translate(38 45.6) scale(0.85)`. Favicon, avatars, anywhere a platform
  forces a square or circle.

## Colour

Uses the theme palette, no additions. In `theme.json` `void` is the GROUND
(#ffffff) and `bone` the INK (#000000); the dark layer in `critical.css`
flips both (`void` #0a0a0a, `bone` #ffffff). So the mark is always drawn in
`bone` on `void`: dark on white in light, white on dark in dark, and one
`color: var(--wp--preset--color--bone)` covers both schemes. Never add a
second dark-mode rule for it. `blood` (#e00404) and `signal` (#ff4c47) are
accents for rules and labels; the mark is never drawn in either.

In the theme: `assets/brand/` holds every file below (favicon, touch icon,
OG image, the mark in each ink). The page itself carries the wordmark, not
the mark; `tests/brand-mark.php` pins both.

## Typography

Wordmark: Bebas Neue, uppercase, one line. Set beside the mark in a lockup
it tracks `wide` (0.15em).

**The site header carries the wordmark alone** (owner, 2026-09-22, 13.10.0):
the name is the brand on the page and the monogram stays the favicon and OG
image, so the brand still appears once per page. Header wordmark:
`.sn-wordmark` in `parts/header.html`, live text in `bone`, tracking 0.04em,
size `clamp(2rem, 10vw, 2.875rem)` (32px at 320 to 46px). The tighter
tracking is deliberate: at 0.15em the name cannot hold one header row on a
phone at any readable size. It sits in a box with the old mark's heights
(64 / 48 / 36px), so the header height and `--sn-chrome-top` did not move.

Descriptor: DM Mono 500, widely tracked. Off the page only (the `ultra`
token it used was removed in 13.8.0). Never substitute either face.

## Don't

Stretch one axis. Rotate. Fatten the stroke. Round the tile. Gradient it.
Outline or shadow it. Reset the name in another face. Crowd the clear space.
