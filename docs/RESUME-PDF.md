# Resume PDF

The goal: the resume PDF can never go stale again, because it is produced from the same data as
`/resume`. Two phases.

| Phase | Where | Status |
|---|---|---|
| 1. Print stylesheet: `Cmd+P` on `/resume` gives a two-page Letter document | theme, `assets/css/print.css` | shipped |
| 2. Server-side generator: "Generate PDF" in S&N → Content writes the file behind the Download link | plugin (`signal-and-noise-tools`) | shipped in 17.7.0, fixes through 17.7.2; operational detail in that repo's `docs/RESUME-PDF.md` |

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

## Phase 2: the generator (plugin)

Built in the plugin; its own `docs/RESUME-PDF.md` has the regeneration steps, data map and tests.
Decided with the owner, 2026-09-22:

- **Design:** the navy and gold rebrand. Colors live in the PDF template only; the browser print
  stays black on white and no color enters the theme palette.
- **Renderer: Dompdf**, committed in the plugin's `lib/pdf/vendor` (the self-updater installs the
  tag archive). Pure PHP, no binary, real selectable text.
- **Font:** Lato, embedded in the PDF only.
- **New fields** under a top-level `pdf` key: headline, tagline, location, phone, email, website,
  competencies, toolkit, and the checkbox **Include the phone in the public PDF**. Certifications
  needed no field: they already live in "Affiliations & Certifications". The sync engine never
  reads `pdf`, so /resume is unchanged; the only change on the page is the Download link's URL once
  a PDF exists (stable path plus `?v=<hash prefix>`).
- **The contact line reuses what the resume knows** (plugin 17.7.1): location from the PDF field,
  else the web contact line ("Orlando, FL"); the Website field, else the site's home URL.
- **The phone is the owner's choice and off the public PDF by default** (17.7.0, 17.7.2): the web
  page never shows it; Generate PDF includes it only when the checkbox is on; **Download private
  copy (with phone)** streams the same PDF to the requesting admin and never stores it. The control
  is a checkbox, never an `os-switch`: os-form reads only checkboxes as booleans, so a switch posts
  `1` in both positions and would publish the phone on every save (fixed in 17.7.2).
- **Times are the site's**: the generated time is stored in UTC and shown in the site timezone.
- **Browser print vs the PDF:** they share the DATA, not the markup. The brief asked for one partial
  rendering both; rendering the PDF partial inside /resume would add markup to the page, which the
  owner ruled out ("There shouldn't be a change of how the resume is displayed in the page"). So
  `Cmd+P` prints the page (black on white, this repo) and the Download link serves the generated
  PDF (the design). The Download link is the canonical file.

## Known limits

- The browser print reuses the web markup, so its section order follows the page, not the design.
  Phase 2's shared template fixes that.
- Credential entries use `<br>` inside one paragraph; CSS cannot turn a line break into a
  separator, so each entry keeps three short lines on paper.
- Measured in Chrome only. Safari and Firefox print paths are unverified, in particular whether
  the closed fold prints: `::details-content` support varies by browser.
