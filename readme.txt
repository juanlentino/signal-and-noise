=== Signal & Noise ===
Contributors: Juan Lentino
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 3.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Signal & Noise is a white, clinical, brutalist WordPress block theme designed for music producers — inspired by nin.com. Black text, white space, red accents.

Features:
* Full Site Editing (FSE) support
* Dark industrial design with film grain and scanline overlays
* Custom page templates for About, Services, Music, Resume, and Contact
* Bebas Neue + DM Mono typography pairing
* Subtle animations, glitch effects, and hover states
* Responsive and mobile-first

== Installation ==

1. Download the theme .zip file
2. Go to WordPress Admin → Appearance → Themes → Add New → Upload Theme
3. Upload the .zip file and click "Install Now"
4. Activate the theme
5. Go to Appearance → Editor to customize your site with Full Site Editing

== Setting Up Pages ==

After activating the theme, create the following pages and assign the custom templates:

1. **Home** — Set as your static front page (Settings → Reading → "A static page")
2. **About** — Create a page, assign template "About"
3. **Services** — Create a page, assign template "Services"
4. **Music** — Create a page, assign template "Music". Add Spotify embed blocks in the page content. The Muso.AI credits section is built into the template.
5. **Resume** — Create a page, assign template "Resume / CV". Upload the PDF to Media Library and update the download button URL.
6. **Contact** — Create a page, assign template "Contact". Install a form plugin (WPForms or Contact Form 7) and paste the shortcode in the page content.

== Color Palette ==

All design tokens are controlled through theme.json:
- White (#ffffff) — primary background
- Smoke (#f5f5f5) — secondary background
- Concrete (#d9d9d9) — borders and subtle elements
- Steel (#666666) — secondary text
- Black (#000000) — primary text
- Red (#e00404) — primary accent
- Signal (#ff4c47) — hover/active accent

Modify in the Site Editor under Styles → Colors.

== Recommended Plugins ==

* WPForms Lite or Contact Form 7 — for the contact form
* Yoast SEO — for search engine optimization

== Changelog ==

= 2.0.0 =
* Full palette inversion to match nin.com — white backgrounds, black text, red accents
* Inverted film grain and scanline overlays with multiply blend mode
* Hero gradient now fades to white
* Buttons now black fill with red hover
* Logo auto-inverts via CSS filter (remove rule once dark logo is uploaded)
* Header gains bottom border for clean separation
* Scrollbar and selection colors updated for light theme
* Image filters softened for white background context
* Updated social icon color values across all templates

= 1.5.0 =
* NIN aesthetic shift: replaced warm palette with cold, clinical colors
* Void now pure black (#000000), White replaces Bone (#ffffff), Red replaces Blood (#e00404)
* Removed Ember color entirely
* Removed Portfolio custom post type and Genre taxonomy (unused)
* Removed unused pattern category registration
* Stripped placeholder/dev text from Music and Contact templates
* Updated all hardcoded CSS colors to new palette
* Increased grayscale and contrast on image filters for colder look
* Updated social icon color values in footer and contact templates
* Cleaned up theme.json: removed unused gradients and lineHeight values
* Updated readme to reflect current theme state

= 1.4.1 =
* Fixed &amp; encoding in theme name for WP admin display
* Standardized fontFamily on all Music page paragraphs
* Unified container widths across Music page sections
* Rewrote Contact page with intro copy and cleaner form layout

= 1.4.0 =
* Merged Muso.AI into Music page as "Full Discography" section
* Removed standalone Muso.AI template and navigation link
* Rewrote Music page intro copy

= 1.3.0 =
* Rewrote Services page descriptions with personality-driven copy
* Rewrote About page bio with narrative voice

= 1.2.2 =
* Completed theme metadata and version tracking

= 1.2.1 =
* Fixed footer year inline rendering via render_block filter

= 1.2.0 =
* Dynamic footer year via [current_year] shortcode
* Optimized resume content baked into Resume template with PDF download

= 1.1.1 =
* Wired existing site images and added CSS hover/filter effects

= 1.1.0 =
* Rebuilt templates to match juanlentino.com layout structure
* Added Services, Music, Resume, and Contact page templates

= 1.0.0 =
* Initial theme scaffold
