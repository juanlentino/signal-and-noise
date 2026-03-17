# Signal & Noise

Custom WordPress block theme for [juanlentino.com](https://juanlentino.com).

White, clinical, brutalist aesthetic inspired by [nin.com](https://nin.com). Black text, white space, red accents. Built for a music producer and creative business consultant.

## About

Signal & Noise is a Full Site Editing (FSE) block theme designed and developed for Juan Lentino's personal site. It serves as the digital home for freelance music production, artist development, and business strategy consulting services.

The design philosophy is deliberate restraint: no stock photography carousels, no parallax effects, no JavaScript frameworks. Content-first, typography-driven, fast. The kind of site that loads instantly and says exactly what it needs to.

### Design DNA

- **Palette:** Pure white (#ffffff), pure black (#000000), signal red (#e00404), steel gray (#666666), smoke (#f5f5f5), concrete (#d9d9d9)
- **Typography:** Bebas Neue (headings) + DM Mono (body) — self-hosted, no external font requests
- **Aesthetic:** High-contrast, industrial, minimal. Film grain overlay, grayscale image filters, no rounded corners, no gradients
- **Inspiration:** nin.com's stripped-back brutalism meets Swiss typography

## Stack

- WordPress 6.9+ Full Site Editing block theme
- PHP 8.0+
- Vanilla CSS + JS (zero build tools, zero npm, zero webpack)
- Self-hosted fonts (woff2)
- Hosted on Cloudways (DigitalOcean) with Breeze caching + Cloudflare
- Nginx (Lightning Stack)

## Architecture

```
signal-and-noise/
├── assets/
│   ├── css/
│   │   ├── critical.css          # Inlined in <head> — above-the-fold styles
│   │   └── custom.css            # Deferred — full theme styles
│   ├── fonts/                    # Self-hosted Bebas Neue + DM Mono (woff2)
│   ├── images/                   # Favicon, logo (56px + 112px retina)
│   └── js/
│       ├── sticky-header.js      # Shrinking fixed header on scroll
│       └── quoter.js             # Private quote generator (PDF via jsPDF)
├── parts/
│   ├── header.html               # Site header (logo, nav, sticky behavior)
│   └── footer.html               # Site footer (social links, copyright)
├── templates/
│   ├── front-page.html           # Homepage — hero, tagline
│   ├── page-about.html           # Bio, portrait, studio background
│   ├── page-services.html        # Music & Production + Business & Strategy + How It Works
│   ├── page-music.html           # Spotify embeds + Muso.AI verified credits
│   ├── page-resume.html          # Professional resume with PDF download
│   ├── page-contact.html         # Contact Form 7 integration
│   ├── page-quoter.html          # Private: admin-only project quote generator
│   ├── page.html                 # Default page
│   ├── single.html               # Single post
│   ├── index.html                # Blog index
│   └── 404.html                  # Not found
├── functions.php                 # Theme functions, auth gates, performance optimizations
├── style.css                     # Theme metadata + full changelog
├── theme.json                    # Colors, typography, spacing, templates, block config
├── readme.txt                    # WordPress.org-style readme
└── screenshot.png                # Theme preview for WP admin
```

## Page Templates

| Template | Slug | Access | Description |
|----------|------|--------|-------------|
| Homepage | `front-page` | Public | Hero with tagline, single viewport |
| About | `page-about` | Public | Bio, studio portrait, Panacea link |
| Services | `page-services` | Public | 4 production cards + 2 strategy cards + How It Works process strip |
| Music | `page-music` | Public | Spotify embeds + Muso.AI verified credits section |
| Resume | `page-resume` | Public | Professional summary with PDF download |
| Contact | `page-contact` | Public | Contact Form 7 with theme-matched styling |
| Quoter | `page-quoter` | Admin only | Hybrid pricing calculator with branded PDF export |
| 404 | `404` | Public | Custom not-found page |

## Private Quoter

The Quoter is an admin-only tool for generating branded client quotes based on a hybrid pricing model (session days + deliverables).

**Access control:** `functions.php` checks `is_user_logged_in()` and `current_user_can('manage_options')` via `template_redirect`. Non-admins are redirected to the WordPress login page. The page is not linked in navigation.

**Features:**
- Client info (name, email, project)
- Variable component: session/consulting days × day rate
- Fixed component: mix, master, production, songwriting, consulting deliverables with quantities
- Configurable revision cap and overage pricing per round
- Payment terms (50/50, 100% upfront, thirds, custom)
- Live calculation with variable/fixed split ratio
- One-click PDF export via jsPDF — branded with name, contact info, red accents, itemized breakdown, terms

**Setup:** Create a WordPress page, assign the "Quoter (Private)" template. No navigation link needed.

## Performance

The theme was optimized through a multi-version PageSpeed Insights pass (v3.0.0–v3.4.0):

- **Zero external render-blocking resources.** All CSS is inline (critical path) or deferred.
- **Self-hosted fonts.** Bebas Neue + DM Mono served as local woff2. No Google Fonts requests.
- **Deferred analytics.** gtag.js loads on first user interaction, not on page load.
- **Conditional script loading.** CF7 CSS/JS only on contact page. Cloudflare Turnstile only on contact page. jsPDF only on quoter page.
- **No jQuery.** Zero framework dependencies.

## Deployment

### Manual (current)

```bash
cd signal-and-noise
zip -r signal-and-noise.zip . -x ".*" "__MACOSX/*" "*.zip" "README.md"
```

Upload via WordPress → Appearance → Themes → Add New → Upload. Activate. Purge Breeze + Varnish.

### GitHub → Cloudways (in progress)

Git deployment from this repo to Cloudways server. Theme files deploy directly to `wp-content/themes/signal-and-noise/`.

### Template Override Handling

WordPress stores template customizations in the database, which can override theme file changes. Signal & Noise handles this automatically:

- **On theme activation:** `after_switch_theme` hook clears all `wp_template` and `wp_template_part` database overrides
- **On theme update:** `upgrader_process_complete` hook does the same
- **Manual reset:** Appearance → Reset Templates in WP admin (one-click nuke)

If the site shows stale templates after a deploy, hit the Reset Templates button and purge caches.

## Changelog

### 3.6.0
Private Quoter tool: admin-only page template for generating branded project quotes. Hybrid pricing model (session days + deliverables) with live calculation, configurable revision policy, payment terms, and one-click PDF export via jsPDF. Auth gate in `functions.php` redirects non-admins to WP login. Registered in `theme.json` as "Quoter (Private)".

### 3.5.1
Removed book/chapter/verse references from all Scripture quotes across six templates (front-page, about, services, contact, music, 404). Verses remain.

### 3.5.0
Services page overhaul: consolidated Business & Strategy from 4 text blocks to 2 image cards (Operations & AI Strategy, Artist & Producer Development) with full visual parity to Music & Production section. Added "How It Works" process strip (Scope Call → Custom Quote → Production) on smoke background with step numbers. Updated credibility strip: swapped "Full Sail Valedictorian" for "GRAMMY Voting Member", changed "50+ Artists" to "50+ Artists & Labels". Process strip CSS with vertical dividers desktop, horizontal dividers tablet. CTA copy tightened.

### 3.4.8
Fixed Services page images: updated upload paths from 2023/10 to 2026/02.

### 3.4.7
Rewrote Resume page intro with site-native voice (shorter, punchier, matches theme tone).

### 3.4.6
Updated Resume template summary to match new resume content and positioning.

### 3.4.4
Root cause fix: removed `justifyContent:right` and overlay colors from nav block; desktop right-align via CSS.

### 3.4.3
Fixed mobile nav: inlined overlay styles at priority 99 to beat WP core CSS.

### 3.4.2
Mobile nav overlay: centered links, removed right-align override.

### 3.4.1
Theme-level favicon fallback (32px + 180px apple-touch-icon).

### 3.4.0
Split critical/deferred CSS (8 KB inline vs 20 KB deferred). Delayed gtag.js until first user interaction (eliminates 147 KiB from initial load).

### 3.3.4
Preloaded DM Mono 300 font (breaks 434ms network chain). Properly sized logo (56px + 112px retina, saves 2.5 KiB).

### 3.3.3
Stripped Cloudflare Turnstile script on non-contact pages (17 KiB render-blocker).

### 3.3.2
Performance: Deferred wp-block-library CSS, fast hero animations on mobile, fetchpriority=low on Interactivity API script modules.

### 3.3.1
Fixed NO_LCP: Changed animation keyframes from opacity: 0 to 0.01 (Chromium bug).

### 3.3.0
Inlined custom.css to eliminate last render-blocking external CSS request. Zero external render-blocking resources. All CSS is inline, heading paints immediately. Removed broken @font-face from custom.css (relative paths break when inlined). Dequeued CF7 CSS on non-contact pages. Deferred CF7 CSS on contact page.

### 3.2.2
Inlined critical Bebas Neue @font-face in head to fix NO_LCP. Browser can now use the preloaded heading font instantly without waiting for external stylesheet.

### 3.2.1
SEO: Added meta description tag for front page and singular posts with excerpts.

### 3.2.0
Self-hosted fonts: Bebas Neue + DM Mono (300/400/500) woff2 files served locally. Eliminated render-blocking Google Fonts CSS request. Removed preconnect hints and external font preloads. Only Bebas Neue Latin preloaded (LCP heading font).

### 3.1.2
Removed all Breeze lazy-load workarounds (output buffer logo fix, class exclusion filter, data-no-lazy attribute). Breeze lazy images disabled at plugin level.

### 3.1.1
Contact Form 7 styling: inputs, textarea, labels, submit button, focus states, validation, and response messages styled to match theme aesthetic.

### 3.1.0
Replaced Site Kit with direct gtag.js snippet (GT-NMC3GVL). Removed all GSI script workarounds (dequeue + output buffer regex). Output buffer now only handles generator meta tag stripping and Breeze logo lazy-load reversal.

### 3.0.3
Removed Optimization Detective workaround code (plugin deleted). Cleaned up output buffer.

### 3.0.2
Output buffer now reverses Breeze lazy-loading on the logo img tag and restores fetchpriority stripped by Optimization Detective plugin. Fixes mobile NO_LCP error.

### 3.0.1
Logo switched from CSS background-image to real `<img>` with `loading="eager"` and `fetchpriority="high"`. Fixes LCP detection (Lighthouse NO_LCP error).

### 3.0.0
PageSpeed Insights optimization pass: font preloading, GSI script removal (93KB blocker), generator meta tag stripping, footer social icons with 44×44px min touch targets (WCAG compliance), font 404 fix.

### 2.9.3
Added Instagram to Contact page social copy.

### 2.9.2
Fixed Contact page copy: removed claim of being "most active on Spotify".

### 2.9.1
Sticky footer: fixed to bottom viewport, compacted (removed separator/spacers, smaller icons, tighter padding ~75px). Hero calc and body padding adjusted for both fixed header and footer.

### 2.9.0
Sticky shrinking header: fixed position, compacts on scroll (smaller logo, tighter padding, subtle shadow). JS uses requestAnimationFrame + passive scroll for performance. Breeze exclusion added for sticky-header.js.

### 2.8.5
Fixed hero height: `calc(100vh - header)` so homepage fills exactly one screen without footer peeking. Uses 100dvh fallback for mobile address bar handling.

### 2.8.4
Redesigned Muso.AI credits section: two-column layout with bordered card CTA, heading changed to "VERIFIED CREDITS", stronger copy, border-top separator.

### 2.8.3
Expanded Spotify embed height: 600px desktop, 500px tablet, 400px mobile.

### 2.8.2
Changed About page red label from "About" to "Who I Am" to avoid redundancy with H1.

### 2.8.1
Swapped About page portrait to B&W studio photo, updated alt text.

### 2.8.0
Added Panacea studio link on About page (panaceastud.io, opens in new tab).

### 2.7.0
Audit fixes: service card CSS retargeted to actual HTML structure (wp-block-column), About page updated with business/strategy positioning, generator meta tags stripped, skip-to-content link added for accessibility, tested up to WP 6.9. Added automatic template override clearing on activation/update and manual Reset Templates admin page.

### 2.0.0
Full palette inversion to match nin.com. White backgrounds, black text, red accents. Inverted film grain and scanline overlays (multiply blend). Hero gradient fades to white. Buttons now black with red hover. Logo auto-inverts via CSS filter. Header gains bottom border. Scrollbar and selection updated for light theme. Image filters softened for white context.

### 1.5.0
NIN aesthetic shift. Replaced warm palette with cold/clinical colors (pure black, white, #e00404 red). Removed Ember color, Portfolio CPT, Genre taxonomy, unused gradients, pattern category, and placeholder dev text. Full housekeeping pass.

### 1.4.1
Fixed & encoding in theme name for WP admin. Standardized fontFamily on Music page. Rewrote Contact page with intro paragraph, cleaner form layout, social links section.

### 1.4.0
Merged Muso.AI page into Music page as a dedicated section. Removed standalone Muso.AI template and nav link. Rewrote Music page intro. Applied proper semantic versioning.

### 1.3.0
Rewrote all four Services descriptions with personality-driven copy. Added Services page intro paragraph. Rewrote About page bio with narrative voice.

### 1.2.2
Completed theme metadata: author name, URIs, expanded description, additional tags. Added version tracking across style.css, readme.txt, and functions.php.

### 1.2.1
Fixed footer year rendering. Replaced shortcode block with inline shortcode processed via render_block filter.

### 1.2.0
Dynamic footer year via `[current_year]` shortcode. Baked full resume content into Resume template with PDF download button.

### 1.1.1
Wired in existing site images (hero, portrait, service photos). Added hero, portrait, and service card CSS effects.

### 1.1.0
Rebuilt all templates to match juanlentino.com layout. Added Services, Music, Resume, Muso.AI, and Contact page templates.

### 1.0.0
Initial theme scaffold with QOTSA/NIN aesthetic, core templates, Portfolio CPT, Bebas Neue + DM Mono typography, film grain/scanline overlays, and industrial color palette.

## License

GNU General Public License v2 or later.
