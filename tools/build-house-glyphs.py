#!/usr/bin/env python3
"""Build the house glyph fonts: arrows (and the numero sign) for DM Mono and Bebas.

Neither shipped font has U+2190-2199, and DM Mono has no U+2116, so every arrow
and every numero sign on the site fell back to Courier New or a system face
(measured 2026-10-17 with Chrome's platform-fonts report). This script draws
them in each face's own terms and writes one small WOFF per face. theme.json
declares each file as an extra @font-face of its family with a unicode-range,
so the browser takes only these code points from it, wherever they appear:
theme templates, plugin output, page content and CSS `content` alike. No markup
changes, so nothing inside a signed region moves.

  - Arrows: a shaft and a two-stroke head, stroked at the face's own minus-sign
    thickness and centered on its minus-sign axis, so they sit where the face's
    own rules sit. Diagonals are the right arrow rotated about the glyph center.
  - Numero (DM Mono only): drawn as "No." from that weight's own N, o and
    period outlines, three cells wide like the text it reads as.

WOFF, not WOFF2: zlib per table, so tests/house-glyphs.php can open the cmap
with PHP alone. Each file is a few hundred bytes to a couple of KB.

Run (needs fonttools and brotli, build-time only):
    python3 -m venv /tmp/fv && /tmp/fv/bin/pip install fonttools brotli
    /tmp/fv/bin/python tools/build-house-glyphs.py
"""

import math
import os

from fontTools.fontBuilder import FontBuilder
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.ttGlyphPen import TTGlyphPen
from fontTools.ttLib import TTFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FONTS = os.path.join(ROOT, 'assets', 'fonts')

# Right arrow is drawn; the rest are rotations of it (degrees, counterclockwise).
ARROWS = {
    0x2192: ('arrowright', 0),
    0x2197: ('arrowupright', 45),
    0x2191: ('arrowup', 90),
    0x2196: ('arrowupleft', 135),
    0x2190: ('arrowleft', 180),
    0x2199: ('arrowdownleft', 225),
    0x2193: ('arrowdown', 270),
    0x2198: ('arrowdownright', 315),
}

# Bebas ships its own up and down arrows; only the others are added for it.
FACES = [
    # (source woff2, output woff, family, include numero, skip code points)
    ('dm-mono-300-latin.woff2', 'sn-glyphs-dm-mono-300.woff', 'SN Glyphs DM Mono Light', True, ()),
    ('dm-mono-400-latin.woff2', 'sn-glyphs-dm-mono-400.woff', 'SN Glyphs DM Mono', True, ()),
    ('dm-mono-500-latin.woff2', 'sn-glyphs-dm-mono-500.woff', 'SN Glyphs DM Mono Medium', True, ()),
    ('bebas-neue-latin.woff2', 'sn-glyphs-bebas.woff', 'SN Glyphs Bebas', False, (0x2191, 0x2193)),
]


def bounds(glyphset, name):
    from fontTools.pens.boundsPen import BoundsPen
    pen = BoundsPen(glyphset)
    glyphset[name].draw(pen)
    return pen.bounds


def rect(pen, x0, y0, x1, y1):
    """A clockwise rectangle (TrueType outer contours run clockwise)."""
    pen.moveTo((x0, y0))
    pen.lineTo((x0, y1))
    pen.lineTo((x1, y1))
    pen.lineTo((x1, y0))
    pen.closePath()


def bar(pen, ax, ay, bx, by, t):
    """A stroke of thickness t from (ax, ay) to (bx, by), square ends."""
    dx, dy = bx - ax, by - ay
    length = math.hypot(dx, dy)
    nx, ny = -dy / length * t / 2, dx / length * t / 2
    pts = [(ax + nx, ay + ny), (bx + nx, by + ny), (bx - nx, by - ny), (ax - nx, ay - ny)]
    # Order clockwise whatever the direction of travel.
    area = sum(p[0] * q[1] - q[0] * p[1] for p, q in zip(pts, pts[1:] + pts[:1]))
    if area > 0:
        pts.reverse()
    pen.moveTo(tuple(round(v) for v in pts[0]))
    for p in pts[1:]:
        pen.lineTo(tuple(round(v) for v in p))
    pen.closePath()


def draw_arrow(pen, advance, cy, t, angle):
    """Right arrow centered on (advance/2, cy), rotated by angle about that point."""
    cx = advance / 2
    half = advance * 0.40            # shaft half-length: 80% of the arrow's own advance
    head = advance * 0.30            # head stroke length
    if angle % 90:
        # A diagonal spans more of the box than a flat arrow of the same
        # length; the approved mockup drew it at about 0.6em.
        half *= 0.72
        head *= 0.85
    rot = math.radians(angle)
    tp = TransformPen(pen, (math.cos(rot), math.sin(rot), -math.sin(rot), math.cos(rot),
                            cx - cx * math.cos(rot) + cy * math.sin(rot),
                            cy - cx * math.sin(rot) - cy * math.cos(rot)))
    tip = (cx + half, cy)
    rect(tp, cx - half, cy - t / 2, cx + half - t * 0.3, cy + t / 2)
    for sign in (1, -1):
        hx = tip[0] - head * math.cos(math.radians(40))
        hy = tip[1] + sign * head * math.sin(math.radians(40))
        bar(tp, tip[0], tip[1], hx, hy, t)


def build(src, out, family, numero, skip):
    source = TTFont(os.path.join(FONTS, src))
    cmap = source.getBestCmap()
    gs = source.getGlyphSet()
    upm = source['head'].unitsPerEm
    minus = bounds(gs, cmap[ord('-')] if 0x2212 not in cmap else cmap[0x2212])
    t = minus[3] - minus[1]
    cy = (minus[1] + minus[3]) / 2
    advance = source['hmtx'][cmap[ord('0')]][0]
    # Arrows get their own advance, wider than a DM Mono cell: the approved
    # mockup drew them about 0.85em long, which a 0.6em cell cannot hold.
    # Bebas is a condensed face, so its arrows are a little narrower.
    arrow_advance = upm if advance >= 0.55 * upm else round(upm * 0.8)

    order = ['.notdef']
    glyphs = {}
    metrics = {}
    new_cmap = {}

    pen = TTGlyphPen(None)
    glyphs['.notdef'] = pen.glyph()
    metrics['.notdef'] = (advance, 0)

    for cp, (name, angle) in ARROWS.items():
        if cp in skip:
            continue
        pen = TTGlyphPen(None)
        draw_arrow(pen, arrow_advance, cy, t, angle)
        glyphs[name] = pen.glyph()
        metrics[name] = (arrow_advance, 0)
        order.append(name)
        new_cmap[cp] = name

    if numero:
        pen = TTGlyphPen(None)
        x = 0
        for ch in ('N', 'o', '.'):
            gname = cmap[ord(ch)]
            gs[gname].draw(TransformPen(pen, (1, 0, 0, 1, x, 0)))
            x += source['hmtx'][gname][0]
        glyphs['numero'] = pen.glyph()
        metrics['numero'] = (x, 0)
        order.append('numero')
        new_cmap[0x2116] = 'numero'

    fb = FontBuilder(upm, isTTF=True)
    fb.setupGlyphOrder(order)
    fb.setupCharacterMap(new_cmap)
    fb.setupGlyf(glyphs)
    for name in order:
        g = glyphs[name]
        g.recalcBounds(fb.font['glyf'])
        metrics[name] = (metrics[name][0], getattr(g, 'xMin', 0))
    fb.setupHorizontalMetrics(metrics)
    hhea = source['hhea']
    fb.setupHorizontalHeader(ascent=hhea.ascent, descent=hhea.descent)
    fb.setupNameTable({'familyName': family, 'styleName': 'Regular'})
    os2 = source['OS/2']
    fb.setupOS2(sTypoAscender=os2.sTypoAscender, sTypoDescender=os2.sTypoDescender,
                usWinAscent=os2.usWinAscent, usWinDescent=os2.usWinDescent,
                sCapHeight=os2.sCapHeight, sxHeight=os2.sxHeight)
    fb.setupPost()
    fb.font.flavor = 'woff'
    fb.save(os.path.join(FONTS, out))
    return out, sorted(new_cmap), t, cy


if __name__ == '__main__':
    for face in FACES:
        out, cps, t, cy = build(*face)
        size = os.path.getsize(os.path.join(FONTS, out))
        print(f"{out}: {len(cps)} glyphs, stroke {t:.0f}, axis {cy:.0f}, {size} bytes")
