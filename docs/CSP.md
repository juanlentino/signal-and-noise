# A strict script-src (plan, not enforceable yet)

**Status, 2026-10-17: NOT ENFORCEABLE YET.** This is the plan for dropping
`'unsafe-inline'` from `script-src`. Nothing here is live. The policy is set at
Cloudflare, not in this repo, and it ships as `Content-Security-Policy-Report-Only`
first. `/contact/` blocks enforcement (see below).

## Where the policy lives

The live CSP is a Cloudflare **Transform Rule** (Rules → Transform Rules →
Modify Response Header), with the other security headers. The theme sends no
CSP. Today's `script-src`:

```
script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com https://static.cloudflareinsights.com
```

Keep the rule's hostname condition. A rule without one once attached the
WordPress CSP to Cloudflare's own `/cdn-cgi/access/*` pages.

## What an inline-free page still runs inline

Measured on the live site 2026-10-17 (`/`, `/about/`, `/notes/`, a note,
`/provenance/`, `/index/`, `/contact/`): every page carries the same three
executable inline scripts, plus speculation rules. JSON islands
(`type="application/json"`, `application/ld+json`) do not execute, so CSP
ignores them; the beacon and command palette read theirs since 2026-10-16.
No page had an inline event handler (`onload=` and the like).

| Inline script | Source | Hash (sha256, base64) | Changes when |
|---|---|---|---|
| `<script id="sn-theme-init">` | theme, `inc/dark-mode.php` | `sha256-+aDIk0AQYDEK+tK47t/wNITT4nprLRPhL+LH2Hb8THs=` | the theme changes the script or `SN_THEME_STORAGE_KEY` |
| `<script id="wp-importmap" type="importmap">` | core | `sha256-Jf2lCBPqQiy5BB3Wn7wYsahOkxB6fFTQrCph9O3wswc=` | every core update (the map carries module `ver=` hashes) |
| `<script type="module">` (emoji loader) | core | `sha256-iplf8gaE6Q7wxi4LmVk6F7Cwwuk4JEp+qC/FWJul78o=` | core changes the loader |
| `<script type="speculationrules">` | core + `inc/speculation.php` | none needed | allowed by `'inline-speculation-rules'` |

The theme-init hash is pinned in code as `SN_THEME_INIT_SHA256`, and
`tests/theme-init-csp-hash.php` fails if the script, the constant and the
comment disagree. The two core hashes are pinned nowhere: they come from the
live page. The emoji loader stays (owner decision 2026-09-29), so its hash
stays in the policy.

Each hash is over exactly the bytes between the opening tag's `>` and
`</script>`, leading and trailing newline included. Sizes on 2026-10-17:
theme-init 156 bytes, import map 155, emoji module 3055.

## The proposed script-src

```
script-src 'self'
  'sha256-+aDIk0AQYDEK+tK47t/wNITT4nprLRPhL+LH2Hb8THs='
  'sha256-Jf2lCBPqQiy5BB3Wn7wYsahOkxB6fFTQrCph9O3wswc='
  'sha256-iplf8gaE6Q7wxi4LmVk6F7Cwwuk4JEp+qC/FWJul78o='
  'inline-speculation-rules'
  https://challenges.cloudflare.com
  https://static.cloudflareinsights.com
```

(One line in the header; split here to read.) Every other directive stays as it
is. Turnstile loads from `challenges.cloudflare.com`, Web Analytics from
`static.cloudflareinsights.com`; the theme scripts, `web-vitals.iife.js`, the
navigation view module and `/webmcp/bridge.js` are `'self'`.

## Where it does not apply

- **`/contact/`** has an inline `<script id="allterrain-forms-config-js-before">`
  from the AllTerrain Forms plugin (third party). It assigns
  `window.allTerrainForms` with per-request values, so it cannot be hashed and
  the theme does not own it. Until it moves to a data island or a file, keep
  `'unsafe-inline'` on `/contact/`, or the form breaks.
- **`/wp-admin/*`, `/wp-login.php`, `/sn-login*`** keep the existing policy.
  The editor and the login screen run inline scripts by design.

The strict rule should match the public site minus those paths; the existing
rule keeps serving the rest.

## Rollout

1. Add the strict policy as `Content-Security-Policy-Report-Only` beside the
   live one. No report endpoint exists today; read violations in the DevTools
   console on the pages in the checklist.
2. **Purge the cache before switching**, and again before enforcing. An
   edge-cached page can predate the data islands or a core update, and its
   inline bytes would not match the hashes.
3. Run the checklist with Report-Only on. Zero violations outside `/contact/`
   and the admin paths.
4. Only then swap the header name to `Content-Security-Policy`. Keep the old
   value at hand for a one-step rollback.

## After a core update: recompute

The import map hash changes with every core update, and the emoji module hash
whenever core touches the loader. A stale hash blocks the import map, and every
script module (navigation, the dark-mode toggle) stops. Recompute after each
core update, after a cache purge so the page is fresh:

```bash
curl -s https://juanlentino.com/ -o /tmp/home.html
python3 -I - /tmp/home.html <<'EOF'
import sys, re, hashlib, base64
h = open(sys.argv[1], 'rb').read()
for name, pat in [('theme-init', rb'<script id="sn-theme-init">(.*?)</script>'),
                  ('importmap', rb'<script id="wp-importmap" type="importmap">(.*?)</script>'),
                  ('emoji-module', rb'<script type="module">(.*?)</script>')]:
    for b in re.findall(pat, h, re.S):
        print(name, len(b), 'sha256-' + base64.b64encode(hashlib.sha256(b).digest()).decode())
EOF
```

Compare with the table, update the Transform Rule, and update this doc. Run it
against a few pages, not just the home page: a page that enqueues another
script module gets a different import map.

**WordPress 7.2 might change this** (unverified): any core release can add,
drop or reshape an inline script. Check
the script list and the hashes on the first 7.2 page before trusting this table.

## Checklist (Report-Only, then enforced)

- [ ] **Dark-mode flash:** set dark with the toggle, reload; no light flash
  (theme-init ran).
- [ ] **Toggle and nav:** the dark-mode toggle switches; the mobile navigation
  opens and closes (import map plus script modules ran).
- [ ] **Command palette:** Cmd/Ctrl+K opens it and lists recent notes.
- [ ] **Beacon:** a pageview POST to the analytics endpoint in the Network tab;
  `window.SN_BEACON.event` is a function.
- [ ] **Speculation rules:** hovering a link starts a prefetch or prerender
  (DevTools → Application → Speculative loads).
- [ ] **Emoji loader:** no violation for the inline module.
- [ ] **`/contact/`:** the form loads and submits (needs the exception above).
- [ ] **Console:** no CSP violation on `/`, `/notes/`, a note, `/provenance/`,
  `/index/`.
