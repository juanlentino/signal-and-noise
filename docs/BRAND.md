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

- **Mark** — default. Header, letterhead, credits.
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

In the theme: `parts/header.html` inlines the mark (`.jl-mark`,
`stroke="currentColor"`), `assets/brand/` holds every file below, and
`tests/brand-mark.php` pins the geometry, the ink token and the 16px floor.

## Typography

Wordmark: Bebas Neue, letterSpacing `wide` (0.15em).
Descriptor: DM Mono 500, letterSpacing `ultra` (0.3em).
Both already exist in `theme.json`. Never substitute.

## Don't

Stretch one axis. Rotate. Fatten the stroke. Round the tile. Gradient it.
Outline or shadow it. Reset the name in another face. Crowd the clear space.
