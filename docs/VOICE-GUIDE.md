# Signal & Noise — Voice Guide

The canonical voice reference for everything written on `juanlentino.com` — front-page copy, page subtitles, button labels, error messages, admin notices, marketing prose. This document supersedes the voice-fingerprint section of [docs/CONTENT-AUDIT.md](CONTENT-AUDIT.md), which anchored on the wrong reference voice and graded canonical Apple-register phrases as drift.

The guide is descriptive, not aspirational — it codifies the voice the site already has when it's working, with examples drawn from passages that landed correctly. It exists to prevent two failure modes:

1. **Future audits or rewrites measuring against the wrong reference.** The R3 audit assumed the brutalist passages were canonical and graded the rest as drift. Wrong anchor.
2. **Drift toward generic consultant / Medium-essay register.** When the voice fails, it usually fails toward "vague abstraction with a confident tone" — not toward the brutalist excerpts.

Refresh this guide when the voice intentionally evolves. Do not edit it to match a one-off rewrite that happens to read well — voice changes are deliberate, not retroactive.

---

## The anchor

**Apple-coded.**

Not "inspired by Apple" — actually in the same voice family. Confident, declarative, abstract enough to gesture at value, concrete enough to name the actual thing being asserted, and adapted by context (hero copy reads differently from procedural copy reads differently from personal-narrative copy — but they're all moves within the same palette).

The site is for a music producer / engineer / creative-business consultant. The brand sits where Apple sits: premium, considered, technical-but-not-jargony, comfortable with abstraction in the right places.

It is **not**:

- Brutalist agency copy ("WE BUILD BRANDS THAT MATTER.")
- LinkedIn consultant ("I help studios leverage AI to scale their workflows")
- Indie producer blog ("just dropped a new mix, hope you dig it")
- Medium essayist ("In a world where every artist is also a brand…")

Each of those has its tells, and they come up when the voice slips.

---

## Register palette — three modes within one voice

Apple doesn't have one tone. It has a palette that adapts to surface. The site's voice works the same way.

### Mode 1 — Hero / abstract / value-prop

For: front-page hero subtitle, top-of-page eyebrows and section openers, page-level subtitles that need to gesture at the whole page.

**Characteristics:**
- List-of-three constructions, often with the third item as thematic glue
- Abstract nouns as subjects ("systems", "intention", "craft")
- Verbless or implied-verb subtitles
- No first-person — the H1 already established it
- Hedge-free declaratives

**On-site exemplars:**
- *"Music production, creative strategy, and the systems that hold them together."* — `templates/front-page.html:19`
- *"I BUILD THINGS THAT SOUND RIGHT."* — `templates/front-page.html:15` (H1 — note the double meaning of "sound right": audio + alignment. Apple loves this kind of double meaning.)
- *"Twenty years of building studios, producing records, and running creative businesses — across Buenos Aires, Orlando, and everywhere in between. Whether you need someone behind the console or behind the strategy, the approach is the same: deliberate, thorough, and built to last."* — `templates/page-services.html:19`. The closing three-adjective stack ("deliberate, thorough, and built to last") is exactly Apple's signature ("Beautiful. Powerful. Fast.").
- *"every decision made with intention."* — `templates/page-services.html:104`. *"Made with intention"* is in the same family as *"engineered for"*, *"crafted to"*, *"designed for"*.

**Apple references for calibration** (study these alongside the site):
- iPhone product pages: hero subtitles like *"Forged in titanium."* — list-of-one with a verb-and-object that gestures at value.
- Apple Watch: *"A healthier, more active, more connected life. Right on your wrist."* — list-of-three with thematic glue, plus a punchy one-line closer.
- Mac product pages: *"Mighty. Mini. Magic."* — three-adjective stack.

### Mode 2 — Procedural / specific / operational

For: instructions, fine print, booking flows, error messages, admin notices, anything where ambiguity costs the user something.

**Characteristics:**
- Short imperatives or short declaratives
- Specific concrete nouns over abstractions
- Periods at fragment ends — "Tap. Hold. Drag." energy
- No marketing language; just say what happens

**On-site exemplars:**
- *"Pick a time below. Clear milestones, no surprises."* — `templates/page-work-with-me.html:65`
- *"Transparent pricing. You see every line item."* — `templates/page-work-with-me.html:82`
- *"Paid at booking · Non-refundable"* — `templates/page-work-with-me.html:118`
- *"I respond to everything that isn't spam."* — `templates/page-contact.html:19`

**Apple reference for calibration:**
- Apple Pay flows: *"Hold near reader. Done."* — two beats, second is the result.
- Time Machine setup: *"Plug in your drive. Choose it. Go."* — three imperatives, no padding.
- Apple support docs: *"On your Mac, open Finder. Click Applications."* — completely literal, completely unmarketed.

### Mode 3 — Personal narrative / lyrical-specific

For: the About bio, anywhere the topic earns specificity — provenance, craft origin stories, mentorship framing, the 404 page (it's branded but not procedural).

**Characteristics:**
- First person, personal claims with concrete biographical anchors (Buenos Aires, 2015, "behind the glass")
- Em-dashed asides that elevate, never asides that hedge
- Occasional lyrical turn when the subject earns it
- Specific verbs over abstract ones
- Comfortable with longer sentences here; the rhythm matters

**On-site exemplars:**
- *"I grew up in Buenos Aires surrounded by sound — the kind of city where music bleeds through every wall and every conversation has a rhythm to it."* — `templates/page-about.html:40`
- *"Knowing when to push, when to pull back, and when to let the silence do the work."* — `templates/page-about.html:48`
- *"Theory gets you in the door — but session management, client communication, knowing when a take is the one before the artist does — that's all earned behind the glass."* — `templates/page-about.html:95`
- *"The frequency you're looking for doesn't exist. It either moved, got eaten by feedback, or was never real to begin with."* — `templates/404.html:19`

**Apple reference for calibration:**
- Apple's *"The complete history of Apple."* tone — biographical, lyrical-when-earned, uses specific years and place names.
- Apple's environmental responsibility section: *"We're transforming our supply chain."* paired with very specific footnotes — abstract claim + concrete proof.

### These three modes coexist

The site is not "three voices." It's **one voice with context-appropriate modes**. The modes share:

- Hedge-free assertion ("does", "is", "creates" — never "may", "can help", "tries to")
- Concrete nouns when the claim is concrete; abstract nouns when the claim is positional
- Em-dashes for elevation, not for qualification
- Short closers — every section's last line lands with weight
- No motivational language
- No qualifying weasel words ("essentially", "basically", "kind of", "I think")

---

## The "could a generic LinkedIn consultant have written this?" test

If yes, throw it out. This catches Mode 1 drift (where it fails most often).

**Fails the test:**
- *"Let me leverage two decades of experience to deliver scalable solutions for your business."*
- *"Modern music production demands strategic thinking and integrated workflows."*
- *"Connect creative identity to commercial opportunity."*
- *"Build systems that scale."*

**Passes the test:**
- *"Music production, creative strategy, and the systems that hold them together."*
- *"From the first idea to the final master, I build the entire sonic architecture of your project."*
- *"Same ear, different console."*

The difference: passing copy makes a specific claim that only this person/brand could make. Failing copy is interchangeable across thousands of consultant LinkedIn pages.

---

## Concrete do / don't pairs

| Do | Don't |
|---|---|
| *"every decision made with intention"* | *"every decision is data-driven"* |
| *"behind the console or behind the strategy"* | *"across both creative and operational domains"* |
| *"I respond to everything that isn't spam"* | *"I aim to respond to all legitimate inquiries"* |
| *"Pick a time below. Clear milestones, no surprises."* | *"Schedule a consultation to discuss your needs."* |
| *"Twenty years of building studios"* | *"With over two decades of industry experience"* |
| *"Tell me what you're working on."* | *"Share your project requirements with us today."* |

The right column pattern-matches to consultant SaaS landing pages. The left column could only have been written for this site.

---

## What about the canonical-form rules?

The mechanical normalizations from v7.5.2 are register-neutral and stay enforced:

- **"20+ years"** in body prose, **"20+ Years"** in visual cred-strips. Never "Twenty years" or "Over 20 years."
- **"50+ collaborations"** in prose, **"50+ Collaborations"** in cred-strips. Never "50+ Artists & Labels."
- **"GRAMMY voting member"** as the short form for cross-page reference. The full **"GRAMMY and Latin GRAMMY voting member since 2025"** stays on the Resume page only (canonical owner).
- **"MBA in Applied AI"** as the short form. Full *"in Business at Westcliff University"* only on About / Resume.
- **Geography**: *"the U.S. and Latin America"* is the most reusable; *"Buenos Aires, Orlando, and everywhere in between"* stays on Services where the trade-list is doing real work.

These are spelling-level decisions, not voice decisions. The voice is what's said; canonical forms are how specific facts are spelled.

---

## Eyebrow system

Canonical pattern: **`Section · Specifier`** with the middle dot (`·`) as separator.

- `Dossier · Who I Am` (About)
- `Dossier · Background` (Resume)
- `Catalog · Discography · 2005 → 2026` (Music)
- `Services · 06 Offerings · 02 Sections` (Services — the count-based variant)
- `Error · 404 · No Signal` (404)
- `Dossier · Get In Touch` (Contact, since v7.5.2)
- `Consulting · Strategy Sessions` (Work With Me / Book a Call, since v7.5.2)
- `Pillar Essay` family on Notes / Home — reserved for editorial content

Don't introduce new bare-phrase eyebrows. If a new page needs an eyebrow, fit it into the dossier/catalog/section system.

---

## When to use which mode

Quick lookup for new copy:

| Surface | Mode |
|---|---|
| Front-page hero subtitle | Hero (Mode 1) |
| Page H1s | Hero (Mode 1) — confident declarative, often double-meaning |
| Page subtitles under H1 | Hero (Mode 1) |
| Section openers (eyebrow + intro paragraph) | Hero (Mode 1) |
| Service descriptions (PRODUCTION, MIXING, etc.) | Hero (Mode 1) — abstract verb-phrases like "made with intention" land here |
| Pricing / booking page bullets | Procedural (Mode 2) |
| Form labels, error messages, admin notices | Procedural (Mode 2) |
| Contact page subtitle | Procedural-leaning Hero — Apple's "we want to hear from you, here's how" register |
| 404 page body | Personal narrative (Mode 3) — branded-error tone |
| About bio paragraphs | Personal narrative (Mode 3) |
| Notes / blog dek | Hero (Mode 1) — short, gestures at scope |
| Notes post bodies | Personal narrative (Mode 3) — these are essays, longer rhythm allowed |
| Resume page meta strips | Procedural (Mode 2) for the strip itself; Hero for the prose paragraph above it |
| CTA button labels | Procedural (Mode 2) — "Book a Call →", "Tell me about your project →" |
| Closing CTA body copy on pages | Hero (Mode 1) — list-of-three with em-dashed aside is the canonical move |

---

## Editing protocol

When proposing a voice-affecting edit:

1. **Identify the mode** the surface uses (Hero / Procedural / Personal narrative).
2. **Diagnose the failure** in the current copy — is it failing the LinkedIn-consultant test? Is it in the wrong mode for the surface? Does it use a hedge word? Does it have a generic three-noun stack with no thematic glue?
3. **Draft replacements in the same mode** — don't shift Hero copy to Procedural just because the current Hero copy is weak.
4. **Pass the LinkedIn-consultant test** on every draft. If a draft passes the test, ship it; if it fails, iterate.
5. **Bring drafts to the maintainer** for any voice-affecting change. Mechanical normalizations (canonical forms, eyebrow alignment, redundancy dedup) can ship without per-item review; voice-shape changes cannot.

Lessons from the v7.5.3 / v7.5.5 round-trip:

- The audit's brutalist anchor produced drafts that pulled toward Mode 3 register on Mode 1 surfaces. That's a category error, not a copy choice.
- "Apple-like" doesn't mean "abstract everywhere." Mode 2 procedural copy is fully Apple-coded. The two work together.
- When in doubt, the maintainer's authorial voice wins. The voice guide describes the working voice; it doesn't override editorial judgment.

---

## Refresh cadence

Refresh this guide when:

- The site's voice deliberately evolves (new product surface, new audience, brand re-position).
- A new mode emerges that doesn't fit the three above.
- The canonical-form rules need updating (e.g., the year claim needs to advance, the discipline framing changes).

Don't refresh this guide:

- To match a one-off rewrite that happens to land well — that's an instance, not a shift.
- After the next content audit; audits should measure against this guide, not rewrite it.

---

## Sources

This guide was assembled from:
- The on-site exemplars cited above.
- Apple's product pages, support docs, and environmental-responsibility section as register references.
- The voice-fingerprint section of [docs/CONTENT-AUDIT.md](CONTENT-AUDIT.md), corrected for the brutalist-anchor error.
- The v7.5.3 / v7.5.5 round-trip on the front-page hero subtitle and the Services blurb closer, which surfaced the dual-register failure mode.
