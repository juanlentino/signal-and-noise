# Resume PDF

The goal: the resume PDF can never go stale again, because it is produced from the same data as
`/resume`. Two phases.

| Phase | Where | Status |
|---|---|---|
| 1. Print stylesheet: `Cmd+P` on `/resume` gives a two-page Letter document | theme, `assets/css/print.css` | shipped |
| 2. Server-side generator: "Generate PDF" in S&N → Content writes the file behind the Download link | plugin (`signal-and-noise-tools`) | planned |

## Where the data lives

`/resume` is not a theme template with data. `templates/page-resume.html` renders the page's
`post_content`, and the plugin writes that content: `inc/resume-sync-engine.php` re-generates the
page body from one option on every save of S&N → Content → Resume.

| Section | Source (option `sn_resume_doc`, normalized in the plugin's `inc/resume-page.php`) |
|---|---|
| Summary | `hero.summary` |
| Credential chips | `hero.chips[]` |
| Contact line | `hero.contact_line`, `hero.linkedin` |
| Download link | `hero.pdf_url`, `hero.pdf_label` (a manual URL today) |
| Stats band | `stats[]{n, label}` |
| Experience | `experience[]{org, dates, location, roles[]{title, bullets[]}}` |
| Earlier career (the fold) | `earlier{label, entries[]{org, roles[]}}` |
| Education | `education[]{title, lines[]}` |
| Affiliations | `affiliations[]{title, lines[]}` |
| Publications | `publications[]{title, meta, url}` |
| Skills | `skills[]{category, items[]}` |

The seed is `inc/seed-content/resume-data.json` in the plugin, used when the option is empty.

## Phase 1: printing /resume

`assets/css/print.css` already loads with `media="print"` on singular pages
(`sn_enqueue_print_styles()`, `inc/assets-frontend.php`). The `/resume on paper` block at its end
turns the web layout into a one-column Letter document. Measured with headless Chrome
print-to-PDF on the live page: **7 pages before, 2 after**. A Note printed identical text with and
without the block.

What each part of it is for, all measured:

- **A named page** (`@page resume`, `body:has(.sn-resume-hero-split) { page: resume }`). `@page`
  cannot be scoped by a selector, so an unnamed rule would re-size every printed page on the site.
- **One scope for everything else**: `body:has(.sn-resume-hero-split)`, the hook the plugin emits
  only on `/resume`.
- **The early career prints.** Chrome keeps a closed `<details>` in `::details-content`, which the
  older `details > *` rule never reached, so the fold was silently missing from every print.
- **No multicol on lists.** `columns: 1` is still a multicol container, and Chrome fragments one
  badly (a page holding a single bullet). Lists are `columns: auto`.
- **No blank trailing page.** `main` reserves 140px for the fixed footer (`layout.css`,
  `!important`); paper has no footer to clear.
- **A clean text layer.** Ligatures dropped a glyph from the PDF text ("workflow" read as
  "workow") and wide tracking split words ("VOT ING"). Both matter to applicant tracking systems.
- Hidden on paper: nav and toggles (the header prints only its wordmark, as the name), the footer,
  the stats band, the Download button, the credential chips (they repeat Credentials below).
  Links print their URL in full. A publication or a skills row never splits across pages.
- Face: Helvetica Neue / Arial on paper; no font family is added to `theme.json`.

Pinned by `tests/resume-print.php` (17), with `tests/print-styles.php` unchanged and green.

To check a change: print `/resume` with headless Chrome and count pages.

```bash
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --no-pdf-header-footer --print-to-pdf=/tmp/resume.pdf https://juanlentino.com/resume/
```

## Phase 2: the generator (planned, plugin)

Decided with the owner, 2026-09-22:

- **Design:** the navy and gold rebrand (headline, tagline, contact line with phone, stats band,
  core competencies, experience, research, education, affiliations and certifications, technical
  toolkit). Colors live in the PDF template only; the browser print stays black on white and no
  color enters the theme palette.
- **Renderer: Dompdf.** Pure PHP, runs on Cloudways PHP 8.4 with no binary, real selectable text
  for ATS. Headless Chrome is not guaranteed on the host and would be new infrastructure. Dompdf is
  the plugin's first runtime dependency, so the release zip ships `vendor/`.
- **Font:** Lato, embedded in the PDF only.
- **New fields** in S&N → Content → Resume, never hard-coded: `hero.headline`, `hero.tagline`,
  `hero.phone`, `hero.email`, `hero.location`, `competencies[]`, `certifications[]`, and a toolkit
  line. The phone appears in the public PDF (owner's design includes it) and not on the web page.
- **One template** renders both the generated PDF and, once it exists, the browser print view, so
  the two cannot diverge.
- The Download link will read a stable path, `uploads/resume/JuanLentino_Resume.pdf`, plus
  `?v=<hash prefix>`, replacing the hand-set `hero.pdf_url` (today the seed still points at a
  `2026/07` file and the live value at a `2026/09` one).

## Known limits

- The browser print reuses the web markup, so its section order follows the page, not the design.
  Phase 2's shared template fixes that.
- Credential entries use `<br>` inside one paragraph; CSS cannot turn a line break into a
  separator, so each entry keeps three short lines on paper.
- Measured in Chrome only. Safari and Firefox print paths are unverified, in particular whether
  the closed fold prints: `::details-content` support varies by browser.
