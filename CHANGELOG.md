# Changelog

All notable changes to Signal & Noise are documented here.

## [4.4.2] — 2026-03-31

### QA pass — CSS sync audit
- Fixed film grain opacity mismatch: custom.css had 0.025, critical.css had 0.035 (synced to 0.035)
- Fixed footer border conflict: critical.css was overriding custom.css border-top with `none !important`. Synced both to show the 1px concrete border
- Fixed header transition: custom.css was missing `background-color 0.3s ease` from transition, causing the frosted-glass opacity to snap instead of fade on scroll
- Fixed header scrolled state: custom.css was missing `background-color: rgba(255,255,255,0.85)` and glass morphism properties. Both CSS files now match
- Removed dead `#trp-floater-ls` CSS (TranslatePress repositioned via plugin settings)

## [4.4.1] — 2026-03-31

- TranslatePress floating switcher: repositioned above fixed footer (bottom: 70px) so it doesn't overlap copyright/SDG text

## [4.4.0] — 2026-03-31

- Hero alignment: golden-ratio offset (15vw on wide screens) instead of flush container edge
- Footer redesign: socials left, copyright + Soli Deo Gloria right (space-between flex layout)
- Footer layout changed from centered constrained to full-width flex

## [4.3.3] — 2026-03-31

- Fixed hero left-alignment positioning: content was flush to viewport edge (only offset by section padding). Added dynamic padding-left using max() to position content where a centered 1100px container would start on wide screens, falling back to theme spacing on narrow screens

## [4.3.2] — 2026-03-31

- Fixed hero left-alignment: WP constrained layout applies margin-left:auto as inline styles on children, so :where() selector (v4.3.0) couldn't override them. Switched to scoped !important on .sn-hero.is-layout-constrained > *

## [4.3.1] — 2026-03-31

- Version bump to trigger self-updater (4.3.0 was already on server)

## [4.3.0] — 2026-03-31

### Sizing & Polish Pass
- Logo: 56→80px desktop, 44→56px tablet, 38→44px mobile; scrolled states scaled proportionally
- Nav text: 0.9rem→1rem
- Hero subtitle: 1.1→1.15rem (1.2rem on 1440px+)
- Hero left-alignment: CSS-only fix using `:where()` selector to override WP constrained layout auto-margins without breaking alignwide/alignfull; subtitle capped at 640px
- XL desktop breakpoint (1440px+): body text 1.05rem, button padding scaled, hero subtitle 1.2rem
- CF7 submit button: styles duplicated into critical.css as Breeze minification insurance
- Body padding-top and hero min-height synced across all breakpoints (desktop/tablet/mobile) in both custom.css and critical.css
- Header HTML `width`/`height` attributes updated to match CSS values

## [4.2.0] — 2026-03-31

- Fixed Breeze CSS minification: moved custom.css to `wp_enqueue_style` so `breeze_exclude_css` filter works
- Breeze was ignoring the filter because custom.css was echoed as raw HTML, not enqueued

## [4.1.0] — 2026-03-31

- Consolidated dashboard: 4 widgets instead of 6
- Visitor Trend: 30-day red bar chart with total count
- Top Stats: single tabbed widget (Pages/Sources/Countries/Devices/Browsers)
- Visitor Map: world choropleth via jsvectormap, colored by traffic

## [4.0.0] — 2026-03-31

- 6 native Plausible dashboard widgets pulling from Stats API
- Visitors Today, Top Pages, Top Sources, Top Countries, Devices, Browsers

## [3.14.3] — 2026-03-31

- Restored default WP dashboard widgets, Plausible full-width on top

## [3.14.2] — 2026-03-31

- Clean dashboard: removed default widgets (reverted in 3.14.3)

## [3.14.1] — 2026-03-31

- Plausible analytics widget on WP Dashboard

## [3.14.0] — 2026-03-31

- Tabbed admin page: Dashboard, Analytics, Links tabs

## [3.13.0] — 2026-03-31

- Plausible CE tracking script on frontend (defer, ~1 KiB, cookie-free)
- Plausible dashboard embedded in admin page

## [3.12.1] — 2026-03-31

- Removed WP Statistics cleanup code (plugin uninstalled)

## [3.12.0] — 2026-03-31

- Signal & Noise admin page: status panel, actions (Full Reset, Clear Overrides, Purge Caches, Check Updates), links

## [3.11.1] — 2026-03-31

- Test release for self-updater verification

## [3.11.0] — 2026-03-31

- GitHub self-updater: checks releases, one-click update from WP admin
- `upgrader_source_selection` filter fixes the `-1` folder rename problem

## [3.10.4] — 2026-03-31

- Fixed contact form radio label: `.wpcf7-form p.form-label` styled to match other labels

## [3.10.3] — 2026-03-31

- Red accent line (60px) between hero subtitle and CTA buttons with fadeInUp animation

## [3.10.2] — 2026-03-31

- Removed default nav underline (`text-decoration: none`)
- Thickened red accent from 1px to 2px

## [3.10.1] — 2026-03-31

- Auto-clear template parts + `wp_navigation` on theme update
- Version-change detector triggers full override clear

## [3.10.0] — 2026-03-31

- Logo switched to media library images (persistent across theme uploads)

## [3.9.9] — 2026-03-31

- Fixed 60-min Cal.com tab: lazy init on first click (can't render into hidden container)
- Removed prices from tab labels

## [3.9.8] — 2026-03-31

- Replaced Cal.com shortcodes with JS inline embeds (shortcodes don't render in block theme templates)

## [3.9.7] — 2026-03-31

- Added Work With Me to header navigation (hardcoded in parts/header.html)

## [3.9.6] — 2026-03-31

- Full revert of `front-page.html` to pre-session state (v3.8.1)
- Restored original subtitle paragraph (simple `1.1rem`, no clamp, no max-width)

## [3.9.5] — 2026-03-31

- Reverted hero CSS to pre-session state; removed `margin-left: 0` that pushed content to page edge

## [3.9.4] — 2026-03-31

- Fixed hero left-alignment: overrode WP constrained layout `margin-left: auto` on hero children
- WP's `is-layout-constrained` was centering content despite CSS flexbox `align-items: flex-start`

## [3.9.3] — 2026-03-31

- Excluded theme CSS from Breeze minification (`breeze_exclude_css` filter)
- Breeze was stripping the `onload` handler from deferred custom.css, leaving styles on `media=print`
- Removed Cloudflare "Cache Everything" page rules (were caching admin pages, API responses, theme/plugin thumbnails)
- Cloudflare default behavior (cache static assets) + Varnish handles frontend performance

## [3.9.2] — 2026-03-31

- Fixed hero layout: removed `justifyContent: left` which pushed container to page edge
- Reverted to original `constrained` with `contentSize: 1100px`
- Text left-aligns naturally within centered container via CSS `.sn-hero` flex align-items

## [3.9.1] — 2026-03-31

- Fixed hero layout: reverted from `default` to `constrained` with `justifyContent: left`
- Content stays within 800px container, left-aligned, matching Contact page pattern

## [3.9.0] — 2026-03-31

- Work With Me consulting page: tabbed 30/60-minute session booking via Cal.com
- Tab switching JS with theme-matched styling (Bebas Neue tabs, red active indicator)
- `hideEventTypeDetails` on Cal.com embeds (page provides its own description/price)
- Registered `page-work-with-me` template in theme.json

## [3.8.5] — 2026-03-31

- Auto-flush theme cache on deploy: detects version mismatch on first admin page load after CI/CD deploy
- Clears theme transients, object cache, and WP theme cache automatically
- Zero cost on subsequent loads; only fires when the deployed version changes

## [3.8.4] — 2026-03-31

- Left-aligned hero section: changed layout from constrained (centered) to default (flow)
- Added max-width on subtitle paragraph
- GitHub Actions CI/CD: push to `main` auto-deploys to Cloudways via rsync + SSH
- Deploy pipeline flushes WP object cache, transients, and Breeze minification cache
- Cloudflare edge caching enabled (31-day TTL, TTFB ~100ms cached)
- Homepage cache warmup after deploy
- Updated README and readme.txt documentation

## [3.8.3] — 2026-03-31

- Dequeued Contact Form 7 JS on non-contact pages
- Removed WP Statistics frontend CSS and tracker JS
- Deferred TranslatePress language switcher CSS via print/onload pattern
- Output buffer stripping for Breeze-bundled WP Statistics stylesheets

## [3.8.2] — 2026-03-31

- Added `composer.json` + `composer.lock` for Aikido supply chain scanning

## [3.8.1]

- Larger logo across all breakpoints: desktop 56→64px (scrolled 38→44px), tablet 44→52px, mobile 38→44px
- Body padding and hero calc adjusted to match

## [3.8.0]

- Bumped film grain opacity from 0.025 to 0.035 for more tactile texture
- Frosted glass header via `backdrop-filter: blur(12px)` with semi-transparent white background (72% default, 85% on scroll)
- Pill-shaped buttons site-wide (`border-radius: 999px`)

## [3.7.0]

- Removed Quoter from WordPress theme; moved to standalone Nginx-served app with basic auth
- Removed page-quoter template, quoter.js, auth gate, and jsPDF enqueue from functions.php

## [3.6.1]

- Quoter fix: robust script loading for block themes
- Added `get_page_template_slug` fallback in `wp_enqueue_scripts`
- Rebuilt quoter.js with DOM-ready fallback, createElement for deliverable rows, error handling for jsPDF

## [3.6.0]

- Private Quoter tool: admin-only page template for generating branded project quotes
- Hybrid pricing model (session days + deliverables) with live calculation
- Configurable revision policy, payment terms, and one-click PDF export via jsPDF

## [3.5.1]

- Removed book/chapter/verse references from Scripture quotes across six templates

## [3.5.0]

- Services page overhaul: consolidated Business & Strategy from 4 text blocks to 2 image cards
- Added "How It Works" process strip (Scope Call → Custom Quote → Production) on smoke background
- Updated credibility strip: swapped "Full Sail Valedictorian" for "GRAMMY Voting Member"
- Process strip CSS with vertical dividers desktop, horizontal dividers tablet

## [3.4.8]

- Fixed Services page images: updated upload paths from 2023/10 to 2026/02

## [3.4.7]

- Rewrote Resume page intro with site-native voice

## [3.4.6]

- Updated Resume template summary to match new resume content and positioning

## [3.4.5]

- Removed CF7 block wrapper border on contact page

## [3.4.4]

- Root cause fix: removed `justifyContent:right` and overlay colors from nav block; desktop right-align via CSS
- Removed header/footer separator lines for cleaner layout

## [3.4.3]

- Fixed mobile nav: inlined overlay styles at priority 99 to beat WP core CSS
- Moved nav overlay styles into inlined critical CSS to bypass cache

## [3.4.2]

- Mobile nav overlay: centered links, removed right-align override

## [3.4.1]

- Theme-level favicon fallback (32px + 180px apple-touch-icon)

## [3.4.0]

- Split critical/deferred CSS (8 KB inline vs 20 KB deferred)
- Delayed gtag.js until first user interaction (eliminates 147 KiB from initial load)

## [3.3.4]

- Preloaded DM Mono 300 font (breaks 434ms network chain)
- Properly sized logo (56px + 112px retina, saves 2.5 KiB)

## [3.3.3]

- Stripped Cloudflare Turnstile script on non-contact pages (17 KiB render-blocker)

## [3.3.2]

- Deferred wp-block-library CSS
- Fast hero animations on mobile
- `fetchpriority=low` on Interactivity API script modules

## [3.3.1]

- Fixed NO_LCP: changed animation keyframes from `opacity: 0` to `0.01` (Chromium bug)

## [3.3.0]

- Inlined custom.css to eliminate last render-blocking external CSS request
- Zero external render-blocking resources
- Dequeued CF7 CSS on non-contact pages; deferred on contact page

## [3.2.2]

- Inlined critical Bebas Neue `@font-face` in head to fix NO_LCP

## [3.2.1]

- SEO: added meta description tag for front page and singular posts with excerpts

## [3.2.0]

- Self-hosted fonts: Bebas Neue + DM Mono (300/400/500) woff2 files served locally
- Eliminated render-blocking Google Fonts CSS request

## [3.1.2]

- Removed all Breeze lazy-load workarounds; Breeze lazy images disabled at plugin level

## [3.1.1]

- Contact Form 7 styling: inputs, textarea, labels, submit button, focus states, validation, response messages

## [3.1.0]

- Replaced Site Kit with direct gtag.js snippet (GT-NMC3GVL)
- Removed all GSI script workarounds

## [3.0.3]

- Removed Optimization Detective workaround code (plugin deleted)

## [3.0.2]

- Output buffer reverses Breeze lazy-loading on logo img tag
- Restores fetchpriority stripped by Optimization Detective plugin

## [3.0.1]

- Logo switched from CSS background-image to real `<img>` with `loading="eager"` and `fetchpriority="high"`
- Fixes LCP detection (Lighthouse NO_LCP error)

## [3.0.0]

- PageSpeed Insights optimization pass
- Font preloading: preconnect hints + preload for Bebas Neue/DM Mono woff2 files
- Google Sign-In (GSI) script removed: dequeue + output buffer strip (93KB blocker)
- Generator meta tag stripping consolidated into single `template_redirect` ob_start
- Footer social icons: normal size + 44x44px min touch target (WCAG compliance)

## [2.9.3]

- Added Instagram to Contact page social copy

## [2.9.2]

- Fixed Contact page copy: removed claim of being "most active on Spotify"

## [2.9.1]

- Sticky footer: fixed to bottom viewport, compacted (~75px)
- Hero calc and body padding adjusted for both fixed header and footer

## [2.9.0]

- Sticky shrinking header: fixed position, compacts on scroll
- JS uses requestAnimationFrame + passive scroll for performance
- Breeze exclusion added for sticky-header.js

## [2.8.5]

- Fixed hero height: `calc(100vh - header)` so homepage fills exactly one screen
- Uses `100dvh` fallback for mobile address bar handling

## [2.8.4]

- Redesigned Muso.AI credits section: two-column layout with bordered card CTA

## [2.8.3]

- Expanded Spotify embed height: 600px desktop, 500px tablet, 400px mobile

## [2.8.2]

- Changed About page red label from "About" to "Who I Am"

## [2.8.1]

- Swapped About page portrait to B&W studio photo

## [2.8.0]

- Added Panacea studio link on About page

## [2.7.0]

- Audit fixes: service card CSS retargeted to actual HTML structure
- About page updated with business/strategy positioning
- Generator meta tags stripped
- Skip-to-content link added for accessibility

## [2.0.0]

- Full palette inversion to match nin.com
- White backgrounds, black text, red accents
- Inverted film grain and scanline overlays (multiply blend)
- Buttons now black with red hover
- Logo auto-inverts via CSS filter

## [1.5.0]

- NIN aesthetic shift: replaced warm palette with cold/clinical colors
- Removed Ember color, Portfolio CPT, Genre taxonomy, unused gradients

## [1.4.1]

- Fixed `&amp;` encoding in theme name
- Standardized fontFamily on Music page; unified container widths
- Rewrote Contact page with cleaner form layout

## [1.4.0]

- Merged Muso.AI page into Music page as a dedicated section
- Removed standalone Muso.AI template and nav link

## [1.3.0]

- Rewrote all four Services descriptions with personality-driven copy
- Rewrote About page bio with narrative voice

## [1.2.2]

- Completed theme metadata: author name, URIs, expanded description, additional tags

## [1.2.1]

- Fixed footer year rendering; replaced shortcode block with render_block filter

## [1.2.0]

- Dynamic footer year via `[current_year]` shortcode
- Baked full optimized resume content into Resume template with PDF download

## [1.1.1]

- Wired in existing site images from media library
- Added hero, portrait, and service card CSS effects

## [1.1.0]

- Rebuilt all templates to match juanlentino.com layout
- Added Services, Music, Resume, Muso.AI, and Contact page templates

## [1.0.0]

- Initial theme scaffold with QOTSA/NIN aesthetic
- Core templates, Portfolio CPT, Bebas Neue + DM Mono typography
- Film grain/scanline overlays, industrial color palette
