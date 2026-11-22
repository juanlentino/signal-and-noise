# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Security
- **A password-protected note's title and text stay on its own page.** A link to a protected note no longer gets a hover preview (title, opening, date) on another note: the slug lookup in `inc/note-link-previews.php` leaves notes with a password out, and a row that arrives anyway is refused by its `post_password` field, which is the gate because the page is cached for every reader. The Cited-by list (`inc/cited-by.php`), the 404 suggestions (`inc/404-recovery.php`) and the home page's latest line (`inc/home-latest-line.php`) leave protected notes out too, so none of them prints a "Protected: ..." title. With no protected note published, all four render the same bytes as before. Tests: `tests/note-link-previews.php` (+6), `tests/cited-by.php` (+1), `tests/404-recovery.php` (+1), `tests/home-latest-line.php` (+2).

### Fixed
- **The arrival bar shows after a contents click.** The contents card replaces the URL with `history.replaceState`, which does not move `:target`, so the 2px rule beside a jumped-to heading only drew for a typed or shared `#fragment`. `assets/js/article-toc.js` now marks the heading with `data-sn-arrived`, one heading at a time, and `assets/css/base.css` lists the mark beside `:target` in every rule: the same bar, the same fade under no-preference, no fade under reduce, CanvasText in forced colors. A heading the page loaded at stands its bar down while another carries the mark, and a real fragment jump clears the mark. Still `replaceState`, so no history entry per click. Tests: `tests/js/article-toc.test.cjs` (+4), `tests/transmission-motion.php` (+4), `tests/resume-one.php` (+1).
- **The command palette is Spanish on the Spanish pages.** The trigger said "Buscar" and the dialog it opened was English. On a page whose `<html lang>` starts with `es`, the dialog's name, the field's name and placeholder, the list's name and every row the script writes are Argentine Spanish; note and page titles are not translated. English is the default and is unchanged. Test: `tests/js/command-palette-lang.test.cjs` (10).
- **A cover played from the keyboard hands focus to the player.** On `/music`, Enter or Space on a cover replaced it with the Spotify frame and left focus on `<body>`. Focus now lands on the frame once it is mounted. A click moves no focus, as before. Test: `tests/js/discography.test.cjs` (+1).
- **Escape puts a footnote popover away.** `assets/js/footnotes-popover.js` had no dismissal that kept the pointer and the focus where they were (WCAG 1.4.13). Escape now removes the popover; focus stays where it is, and focus inside the popover goes back to the reference that opened it. The keydown listener exists only while a popover is open. Test: `tests/js/footnotes-popover-escape.test.cjs` (5).

## [2026.11.14] - 2026-11-18 — security.txt says there is no bug bounty

### Changed
- **security.txt says there is no bug bounty.** Three comment lines at the top of `/.well-known/security.txt`: no bug bounty program and no payment for reports; a report with the affected URL, steps to reproduce and impact is welcome through the contact form. Comments, so the RFC 9116 fields are unchanged. Test: `tests/security-txt.php` (+1).

