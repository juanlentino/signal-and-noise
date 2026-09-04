# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [12.18.4] - 2026-09-04 — the correction notice joins the house vocabulary

v12.18.3 shipped `.sn-correction` as an inset panel with a 3px left rail. Every
colour was right and every token guard passed it. The SHAPE was foreign.

### What the token guards structurally could not see

An asphalt fill inside `.wp-block-post-content` always escapes the measure in
this theme and carries hard rules top and bottom. Derived from the corpus, not
declared:

| block | full-bleed | rules |
|---|---|---|
| `.sn-pull-quote` | yes | 3px bone, top + bottom |
| `.sn-pattern-compare-columns` | yes | 1px concrete, top + bottom |
| `.sn-pattern-steps-enumerated` | yes | none |
| `.sn-correction` (v12.18.3) | **no** | **3px concrete, LEFT** |

It was the only element in the corpus shaped that way. A rail-and-inset callout
is the docs-admonition idiom, imported from generic web design. The other
non-bleed asphalt blocks — `.sn-provenance-panel`, `.sn-music-featured`,
`.sn-article-progress` — are page chrome and never sit inside post content.

The contrast, inverts, dark-mode and design-token suites all passed v12.18.3,
because each checks what a rule is PAINTED with. Nothing checked what it is
SHAPED like, and that is where a house style actually lives.

### The fix

Full-bleed, `1px solid concrete` top and bottom, no rail. The escape
(`left: -1rem; width: calc(100% + 2rem)`) is byte-identical to its three
siblings rather than re-derived, so its assumption — that the content column
carries at least 1rem of side padding — is shared with them: if it is ever
wrong it is wrong for all four at once, which is one fix instead of four.

1px concrete rather than the pull-quote's 3px bone: a correction is a
structural part of the record, not an interruption. `signal` and `blood` stay
unused, as before.

Padding is `1.5rem` rather than the siblings' `2rem 1.5rem` — a correction is
one to three sentences, and 2rem around two lines reads as a display block.

Scope moved from `.single-post` to `.wp-block-post-content`, because the escape
math is about that container. The three siblings scope the same way.

### The guard this was missing

`tests/prose-slab-idiom.php` (new): every asphalt-filled rule scoped inside
`.wp-block-post-content` must break the measure and carry no left rail. Chrome
panels are excluded by SELECTOR rather than by an allowlist that would need
maintaining.

Negative-controlled against the real defect: the fixture is a correctly-COLOURED,
wrongly-SHAPED inset panel — exactly what shipped — because a colour-wrong
control would prove nothing about a shape rule. The scan also asserts it finds
at least four slabs, since a zero-match sweep passes vacuously.

### Files

- `assets/css/article.css` — the reshaped rule
- `tests/prose-slab-idiom.php` — new, 14 assertions

Suite: 115 suites, 3055 assertions, 0 failed.
