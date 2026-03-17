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
│       └── sticky-header.js      # Shrinking fixed header on scroll
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
│   ├── page.html                 # Default page
│   ├── single.html               # Single post
│   ├── index.html                # Blog index
│   └── 404.html                  # Not found
├── functions.php                 # Theme functions, performance optimizations
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
| 404 | `404` | Public | Custom not-found page |

## Performance

The theme was optimized through a multi-version PageSpeed Insights pass (v3.0.0–v3.4.0):

- **Zero external render-blocking resources.** All CSS is inline (critical path) or deferred.
- **Self-hosted fonts.** Bebas Neue + DM Mono served as local woff2. No Google Fonts requests.
- **Deferred analytics.** gtag.js loads on first user interaction, not on page load.
- **Conditional script loading.** CF7 CSS/JS only on contact page. Cloudflare Turnstile only on contact page.
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

See [Releases](https://github.com/juanlentino/signal-and-noise/releases) for the full version history.

The canonical changelog is also maintained in `style.css` header comments.

## License

GNU General Public License v2 or later.
