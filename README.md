# Signal & Noise

A white-first, brutalist **WordPress Full Site Editing block theme** built for [juanlentino.com](https://juanlentino.com) — inspired by [nin.com](https://nin.com). Black text on white, generous whitespace, blood-red accents, and a Bebas Neue + DM Mono pairing. Buildless by design: vanilla CSS and JS, self-hosted fonts, zero npm/webpack.

![Signal & Noise](screenshot.png)

## Design DNA

- **Palette** — `void` #ffffff · `asphalt` #f5f5f5 · `concrete` #d9d9d9 · `rust` #666 · `bone` #000 · `blood` #e00404 · `signal` #ff4c47 (all driven by `theme.json`)
- **Type** — Bebas Neue (display) + DM Mono (editorial), self-hosted woff2, no Google Fonts
- **Aesthetic** — high-contrast industrial minimalism: film-grain overlay, grayscale image filters, no rounded corners, no gradients
- **Long-form** — frontmatter spec card, drop caps, footnotes, sidenotes, justified text with hyphenation and hanging punctuation

## Stack

- WordPress 7.0+ FSE block theme · PHP 8.0+
- Vanilla CSS + JS — no build step, no framework, no jQuery
- Inlined critical CSS + deferred stylesheets; View Transitions for soft navigation
- Hosted on Cloudways, edge-cached via Cloudflare

## Templates

Block templates for the homepage, long-form notes, and the site's standing pages (Services, Music with Muso.AI credits, Resume, Contact, Work-With-Me). All design tokens are editable in the Site Editor under **Styles**.

## Companion plugin

Operational tooling — SEO, login hardening, admin surfaces, analytics, and AI-assisted health checks — lives in the companion plugin, [**Signal & Noise Tools**](https://github.com/juanlentino/signal-and-noise-tools), to keep this repo focused on presentation.

## Install

Distributed via GitHub releases (not the WordPress.org directory). Install/update through **wp-admin → Dashboard → Updates → Update theme**, powered by the theme's self-updater (`inc/wp-update-integration.php`). Requires the companion plugin.

## License

[GNU General Public License v2 or later](LICENSE).

---

<sub>Built for [juanlentino.com](https://juanlentino.com). Full release history in [CHANGELOG.md](CHANGELOG.md).</sub>
