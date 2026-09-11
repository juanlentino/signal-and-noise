#!/usr/bin/env python3
"""Re-runnable baseline capture: extracts the four furniture regions
(front matter, closing part, related notes, cited-by) from one signed
note's live HTML, as balanced elements (opening tag through its
matching close, counting same-tag nesting depth).

Usage: python3 docs/research/baselines/capture.py URL > out.html
"""
import sys
import re
import urllib.request
from datetime import datetime, timezone

URL = sys.argv[1] if len(sys.argv) > 1 else "https://juanlentino.com/notes/falsifiability-is-the-line/"

# (region label, container tag, class to match)
REGIONS = [
    ("sn-post-frontmatter", "div", "sn-post-frontmatter"),
    ("sn-post-closing", "footer", "sn-post-closing"),
    ("sn-related-notes", "footer", "sn-related-notes"),
    ("sn-cited-by", "footer", "sn-cited-by"),
]


def fetch(url):
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (baseline)"})
    with urllib.request.urlopen(req, timeout=30) as r:
        return r.read().decode("utf-8", errors="replace")


def extract_balanced(html, tag, cls):
    """Find <tag ... class="...cls..." ...> and return it through its
    matching closing tag, counting nested same-tag opens/closes (not just
    the first terminator)."""
    m = re.search(
        r'<' + tag + r'\b[^>]*class="[^"]*\b' + re.escape(cls) + r'\b[^"]*"[^>]*>',
        html,
    )
    if not m:
        return None
    start = m.start()
    open_re = re.compile(r'<' + tag + r'\b[^>]*>')
    close_re = re.compile(r'</' + tag + r'>')
    depth = 0
    pos = m.start()
    end = None
    while pos < len(html):
        o = open_re.match(html, pos)
        c = close_re.match(html, pos)
        if o:
            depth += 1
            pos = o.end()
            continue
        if c:
            depth -= 1
            pos = c.end()
            if depth == 0:
                end = pos
                break
            continue
        pos += 1
    if end is None:
        return None
    return html[start:end]


def theme_version(html):
    m = re.search(r"signal-and-noise/style\.css\?ver=([0-9][0-9.]*)", html)
    if m:
        return m.group(1)
    # Block themes often don't enqueue style.css with a cache-busting ?ver
    # on the front end — read the theme header directly instead.
    m2 = re.search(r"https?://[^/]+", URL)
    if m2:
        try:
            style = fetch(m2.group(0) + "/wp-content/themes/signal-and-noise/style.css")
            v = re.search(r"^Version:\s*([0-9][0-9.]*)", style, re.M)
            if v:
                return v.group(1)
        except Exception:
            pass
    return "unknown"


def main():
    html = fetch(URL)
    ver = theme_version(html)
    ts = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    print(f"<!-- source: {URL} captured {ts} theme {ver} -->")
    for label, tag, cls in REGIONS:
        region = extract_balanced(html, tag, cls)
        print(f"<!-- region: {label} -->")
        if region is None:
            print(f"<!-- region: {label} — absent on this note -->")
        else:
            print(region)


if __name__ == "__main__":
    main()
