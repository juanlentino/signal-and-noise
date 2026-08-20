# Signal & Noise

A white-first, brutalist **WordPress Full Site Editing block theme** built for [juanlentino.com](https://juanlentino.com), inspired by [nin.com](https://nin.com). Black text on white, generous whitespace, blood-red accents, and a Bebas Neue + DM Mono pairing, with a full dark inversion since v12.0.0. Buildless by design: vanilla CSS and JS, self-hosted fonts, zero npm/webpack.

![Signal & Noise](screenshot.png)

## Design DNA

- **Palette** — `void` #ffffff · `asphalt` #f5f5f5 · `concrete` #d9d9d9 · `rust` #666 · `bone` #000 · `blood` #e00404 · `signal` #ff4c47 (all driven by `theme.json`)
- **Three palettes, not one** — the site can present `root` (theme.json), the shipped `high-contrast` variation, and `dark`. `get-design-tokens` reports all three plus which one is served. Any color reasoning has to hold in all of them: `blood` #e00404 clears AA on white at 5.01:1 and fails on the dark ground at 3.95:1, which is why dark re-points it to #ff4c47 (6.01:1)
- **Dark** — a token layer, not a second stylesheet. `:root[data-theme="dark"]` and a `prefers-color-scheme` block redefine the same `--wp--preset--color--*` properties every component already consumes, so nothing can be half-converted. Ground #0a0a0a, `bone` inverts to white, the film grain flips `multiply` to `screen` so the texture survives, and the logo inverts through a token. It writes nothing to the database
- **Semantic tokens beyond the palette** — `--sn-panel*` for surfaces that deliberately contrast with the page (the command palette, the keyboard modal, the skip link: a *raised dark* surface in dark, never a white card), and `--sn-embed-backdrop` for third-party chrome, identical in both schemes because it matches somebody else's card
- **Type** — Bebas Neue (display) + DM Mono (editorial), self-hosted woff2, no Google Fonts
- **Aesthetic** — high-contrast industrial minimalism: film-grain overlay, grayscale image filters, no rounded corners, no gradients. Dark is an inversion rather than a softening: no elevation ramp, no gray "surfaces", hairlines stay hairlines
- **Long-form** — frontmatter spec card, drop caps, footnotes, sidenotes, justified text with hyphenation and hanging punctuation

## Stack

- WordPress 7.0+ FSE block theme · PHP 8.3+
- Vanilla CSS + JS — no build step, no framework, no jQuery
- Inlined critical CSS + one combined, minified stylesheet the theme builds itself (`inc/asset-combine.php`, fail-open to the per-file enqueues); View Transitions for soft navigation
- Hosted on Cloudways, edge-cached via Cloudflare

## Pages & templates

Block templates for the homepage, long-form **notes**, and the standing pages — **About**, **Services**, **Music** (role-filtered discography grid + featured player, Muso.AI verified credits), **Resume**, **Contact**, **Now**, **Uses**, **Accessibility**, and the **Provenance** pillar. **Colophon** is CMS-owned (rendered via a Site Editor `wp_template` override of the plugin's `[sn_colophon]` shortcode, since plugin v10.13.0) rather than a theme template file. Plus server-rendered virtual routes with no editor entry: **`/notes`** (notes dossier), **`/index`** (whole-site index), **`/humans.txt`**, and the machine-readable set — **`/llms.txt`** + **`/llms-full.txt`**, **`/opensearch.xml`**, **`/.well-known/agents.json`**, **`/.well-known/security.txt`**, **`/.well-known/gpc.json`**, and a **`.json`** content twin of every Note. All design tokens are editable in the Site Editor under **Styles**.

## Front-end

- **Command palette** (`⌘`/`Ctrl-K` or `/`) — accessible, notes-scoped search and jump-to
- **Keyboard nav** — `j`/`k` previous/next note, `?` cheat-sheet (progressive enhancement; links work without JS)
- **Theme toggle** — follows the OS by default and persists an explicit choice. It renders in whichever bar is *persistent* at that width: the footer utility strip at ≥782px, the header at ≤781px, keyed to the same 781px boundary that makes the footer static on a phone. Ships `hidden` and is revealed only by the script that makes it work, because a toggle that cannot persist a choice is worse than none
- **Touch-correct hover** — every `:hover` rule sits behind `@media (hover: hover)`, with `:focus-visible` deliberately outside it. On iOS a tap applies `:hover` and keeps it until the next tap elsewhere, so an unguarded hover state stops reading as feedback and becomes the control's apparent resting style. A `(hover: none)` block also neutralizes the unguarded hovers WordPress generates from `theme.json` into `global-styles-inline-css`, which the theme cannot reach any other way
- **Reading aids** — article TOC with a sticky progress bar, shared-tag related notes, a frontmatter spec card, and reading time
- **Provenance** — each Note carries a byline verification chip and an expandable record panel showing it's Ed25519-signed and Bitcoin-anchored, with links to the on-chain block and the public ledger. The plugin owns the markup (`sn_prov_render_chip` / `sn_prov_render_panel`); the theme places them in the byline + closing parts (see `inc/provenance-surface.php`)
- **Pillar essays** — the curated cornerstone essays render through the owner-placeable `signal-noise/pillar-essays` block, numbered with the owner's editorial designations (№ 1.01); a flagged essay's own page carries that designation as an eyebrow (`inc/pillar-title-eyebrow.php`)
- **Feeds** — JSON Feed 1.1, WebSub (PubSubHubbub) advertisement, and Media-RSS enrichment; feed items for verifiable Notes republish the provenance uid (a `_signal_noise` JSON Feed extension and an RSS `<sn:noteUid>` element), so a subscriber can reach the verification docket without a second fetch
- **IndieWeb** — `rel=me` identity links, `humans.txt`, and a live colophon build line (`[sn_build]`: theme + plugin version, git SHA, deploy time)
- **Analytics** — a cookieless first-party beacon (Cloudflare Worker endpoint; respects DNT/GPC)
- **WordPress 7.0 Abilities API** — the theme registers read + generative-AI capabilities for agents

Every plugin-backed feature is guarded: the theme runs standalone and degrades gracefully when the companion plugin is absent.

## Companion plugin

Operational tooling — SEO, login hardening, admin surfaces, analytics, and AI-assisted health checks — lives in the companion plugin, [**Signal & Noise Tools**](https://github.com/juanlentino/signal-and-noise-tools), to keep this repo focused on presentation.

## Install

Distributed via GitHub releases (not the WordPress.org directory). Install/update through **wp-admin → Dashboard → Updates → Update theme**, powered by the theme's self-updater (`inc/wp-update-integration.php`). The companion plugin is recommended for the full feature set, but the theme runs standalone.

## License

[GNU General Public License v2 or later](LICENSE).

---

<sub>Built for [juanlentino.com](https://juanlentino.com). Full release history in [CHANGELOG.md](CHANGELOG.md).</sub>
