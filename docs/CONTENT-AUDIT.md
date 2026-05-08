# Signal & Noise — Editorial Content Audit

Generated: 2026-05-08
Auditor: Claude (R3 research pass — content)
Scope: every `.html` template + part with inline prose (14 templates + 2 parts)

---

## Executive summary

The voice on this site is one of its strongest assets — when it lands, it lands hard ("more hours behind the console than I can count," "I respond to everything that isn't spam," "Knowing when to push, when to pull back, and when to let the silence do the work"). The brutalist-direct register is consistent across About, Contact, 404, and Notes. It breaks down in two predictable places: the front-page hero subtitle, and the Services page intro/closing CTA, where the prose drifts into generic consultant cadence ("systems that hold them together," "deliberate, thorough, and built to last," "tell me what you're working on").

The factual layer is mostly clean but inconsistent in form. The "20+ years / 50+ collaborations / GRAMMY voting member / MBA Applied AI" credibility quartet appears in five distinct phrasings across four pages, with no canonical version. Year counts swing between "20+ years," "Over 20 years," and "Twenty years" — three different registers in three different places. Studio name (Panacea), education (Full Sail BS, Westcliff MBA), and locations (Buenos Aires / Orlando / U.S. and Latin America) are all consistent.

Counts: **6 voice-drift findings**, **8 factual/consistency findings**, **5 redundancy clusters**, **3 IA/labelling issues**, **9 prose cleanups** (mostly punctuation consistency).

**Top three changes worth doing this week:**
1. Rewrite the front-page hero subtitle (one line; the whole site bottlenecks here).
2. Pick one canonical phrasing of the credibility line and use it everywhere it appears.
3. Decide what `/services` opens with — the current intro and closing CTA are the weakest prose on the site.

---

## Voice fingerprint

The voice is **first-person, declarative, slightly hard-bitten, occasionally lyrical when the subject earns it**. It is not motivational. It is not modular consultant-speak. Sentences are short. When a long one arrives, it is doing real work. Specifics (Buenos Aires, Panacea, 2015, Valedictorian, "behind the glass") are preferred over abstractions. The voice trusts the reader and refuses to over-explain. It is comfortable saying *no* (404: "got eaten by feedback, or was never real to begin with") and comfortable saying *yes* to a craft frame ("the craft deserves to be taken seriously at every level").

**Reference quotes — calibrate rewrites against these:**
- "I grew up in Buenos Aires surrounded by sound — the kind of city where music bleeds through every wall and every conversation has a rhythm to it." — `templates/page-about.html:40`
- "Knowing when to push, when to pull back, and when to let the silence do the work." — `templates/page-about.html:48`
- "The frequency you're looking for doesn't exist. It either moved, got eaten by feedback, or was never real to begin with." — `templates/404.html:19`
- "I respond to everything that isn't spam." — `templates/page-contact.html:19`
- "Theory gets you in the door — but session management, client communication, knowing when a take is the one before the artist does — that's all earned behind the glass." — `templates/page-about.html:95`
- "Two decades of work, distilled into sound. […] across genres, across borders, and across a whole lot of late nights. Hit play." — `templates/page-music.html:19`
- "Working notes on music, AI, and the infrastructure underneath. Written when there's something worth writing." — `templates/home.html:17` / `templates/page-notes.html:9`

These all share a structure: concrete noun → specific qualifier → either a hard stop or an em-dashed turn. Rewrites should pass the **"could a generic LinkedIn consultant have written this?"** test. If yes, throw it out.

---

## Findings

### A. Voice drift — where the prose stops sounding like Juan

| # | Where | Current line | Why it doesn't fit | Suggested direction |
|---|---|---|---|---|
| A1 | `templates/front-page.html:19` | "Music production, creative strategy, and the systems that hold them together." | "Systems that hold them together" is the most consultant-coded phrase on the site. It's a noun-phrase, not an assertion — the rest of the voice is assertive. | Drafts in §G1. Keep first-person, name a thing. |
| A2 | `templates/page-services.html:19` | "Twenty years of building studios, producing records, and running creative businesses — across Buenos Aires, Orlando, and everywhere in between. Whether you need someone behind the console or behind the strategy, the approach is the same: deliberate, thorough, and built to last." | "Deliberate, thorough, and built to last" is three abstract adjectives strung together. Nothing in this clause carries information. The geography-and-trade-list sentence is fine; the second sentence is filler. | Cut second sentence, or replace with a specific. Drafts in §G2. |
| A3 | `templates/page-services.html:260` | "Whether it's a record, a business problem, or a workflow that needs fixing — I'd rather hear about it than guess. Tell me what you're working on." | The first sentence works. "Tell me what you're working on" is fine but slightly limp as a closer. The whole CTA section heading + body together feel like a generic landing-page module. | Either trust the first sentence alone, or harden the closer. Drafts in §G3. |
| A4 | `templates/page-services.html:104` | "From the first idea to the final master, I build the entire sonic architecture of your project. Arrangement, instrumentation, vocal direction, sound selection — every decision made with intention." | "Made with intention" is the closest thing on the site to a *Medium-essay* tic. The list before it is good. | "Made with intention" → "made on purpose" / "made to serve the song" / "and nothing left to default." |
| A5 | `templates/page-services.html:220` | "Sustainable business models, streamlined operations, and AI-assisted workflows that actually work. I help studios, labels, and creative companies build systems that scale — grounded in a decade of running my own studio and an MBA in Applied AI." | "Build systems that scale" is the second-most consultant-coded phrase on the site. The "actually work" qualifier is good and on-voice; the rest fights it. | Replace the abstract framing with the specific outcome. See §G4. |
| A6 | `templates/page-services.html:240` | "Long-term roadmaps that connect creative identity to commercial opportunity. Brand positioning, release strategy, sonic direction, and one-on-one mentorship for artists and producers ready to turn talent into a career." | The list works. "Connect creative identity to commercial opportunity" is two abstractions kissing. | Replace first sentence: "Long-term roadmaps for artists ready to turn talent into a career — without losing the thread of what made them worth listening to." |

Pages that **don't** drift and should be left alone editorially: `templates/page-about.html` (all bio paragraphs), `templates/page-contact.html`, `templates/404.html`, `templates/page-music.html` intro, `templates/home.html` / `templates/page-notes.html` dek, `templates/single.html` footer links.

### B. Factual inconsistency

| Claim | Phrasing 1 | Phrasing 2 | Phrasing 3 | Phrasing 4 |
|---|---|---|---|---|
| **Years experience** | "20+ years" — `page-resume.html:19`, `page-music.html:55` | "20+ Years" — `page-resume.html:23`, `page-services.html:35` (cred strip) | "20+ years" — `page-work-with-me.html` (was; current line uses "Twenty years") | "Over 20 years" — `page-about.html:48` / "Twenty years" — `page-services.html:19`, `page-work-with-me.html:19` |
| **Collaborations / artists** | "50+ Collaborations" — `page-resume.html:23` | "50+ Artists & Labels" — `page-services.html:43` | "50+ collaborations" (lowercase) — `page-about.html:48` | — |
| **GRAMMY membership** | "GRAMMY Voting Member" — `page-services.html:51`, `page-resume.html:23` | "voting member of both The Recording Academy and The Latin Recording Academy" — `page-about.html:48` | "GRAMMY and Latin GRAMMY voting member since 2025" — `page-resume.html:19` | — |
| **MBA** | "MBA, Applied AI" — `page-services.html:59` (cred strip) | "an MBA in Applied AI" — `page-services.html:220` | "an MBA in Applied AI in Business at Westcliff University" — `page-about.html:44` | — |
| **Studio** | "Panacea, my recording studio, in 2015" — `page-about.html:40` | linked to `panaceastud.io` — `page-about.html:40` | (only mentioned once across templates; no conflict) | — |
| **Geography** | "Buenos Aires, Orlando, and everywhere in between" — `page-services.html:19` | "across the U.S. and Latin America" — `page-resume.html:19`, `page-work-with-me.html:19` | "across the Americas" — `page-about.html:44` | — |
| **Education** | "Bachelor of Science in Music Production from Full Sail University, where I graduated as Valedictorian with the Advanced Achiever award" — `page-about.html:40` | (only mentioned once; no conflict, but consider whether the Resume page should call this out too) | — | — |

**Recommended canonical forms (pick once, propagate):**
- Years: **"20+ years"** in body prose, **"20+ Years"** in eyebrow/credibility strips. (Drop "Twenty years" and "Over 20 years.")
- Collaborations: **"50+ collaborations"** in prose, **"50+ Collaborations"** in cred strips. (Drop "50+ Artists & Labels" — it's the odd one out and changes the noun.)
- GRAMMY: **"GRAMMY and Latin GRAMMY voting member since 2025"** is the strongest line because it's specific and dated. Use it once on the page that owns the claim (Resume), and on every other page use the shorter **"GRAMMY voting member"** form.
- MBA: **"MBA in Applied AI"** is the working short form. Reserve the full "in Business at Westcliff University" only for About/Resume.
- Geography: **"the U.S. and Latin America"** — most reusable. Buenos Aires/Orlando is a stronger phrase; keep it on Services where the trade-list framing is doing real work.

### C. Redundancy map

#### C1. The credibility quartet
Appears in five places, three structurally different forms:

| File | Line | Form |
|---|---|---|
| `templates/page-services.html` | 35–59 | 4-column credibility strip: "20+ Years Experience · 50+ Artists & Labels · GRAMMY Voting Member · MBA, Applied AI" |
| `templates/page-resume.html` | 23 | Inline meta: "20+ Years · 50+ Collaborations · GRAMMY Voting Member" (no MBA) |
| `templates/page-resume.html` | 19 | Prose: "20+ years building studios… GRAMMY and Latin GRAMMY voting member since 2025" |
| `templates/page-about.html` | 48 | Prose: "Over 20 years, 50+ collaborations… voting member of both The Recording Academy and The Latin Recording Academy" |
| `templates/page-music.html` | 55 | Prose: "every credit, every collaboration, every role I've held across 20+ years" |

**Recommendation:** Services gets the visual cred strip (it's a sales page, the strip belongs there). Resume gets the prose form *with the "since 2025" date*, because Resume owns time-stamped facts. About keeps the prose form but trims to one mention only — currently the bio pulls all four claims into one paragraph, which makes it read like a CV bullet wrapped in an em-dash. Music drops the "20+ years" reference (the catalog itself is the proof).

#### C2. "Behind the console / behind the strategy"
- `templates/page-services.html:19` — "Whether you need someone behind the console or behind the strategy, the approach is the same"
- `templates/page-services.html:104` — "From the first idea to the final master" (same idea, different framing)
- `templates/page-about.html:95` — "all earned behind the glass" (related image, different sentence)

The "console / strategy" pair is one of the cleaner pieces of structural framing on the site. Keep it on Services. Don't repeat it elsewhere.

#### C3. "Working notes on music, AI, and the infrastructure underneath"
- `templates/home.html:17`
- `templates/page-notes.html:9`
- These two render the same surface (`/` and `/notes`) and are intentionally identical — the comment in `home.html:6–11` explains why. **Not a problem.** Just verify the strings stay in sync if either is edited.

#### C4. "Provenance Over Detection" / "Provenance as Substrate" pillar cards
- `templates/home.html:31–51` — surfaces *Provenance Over Detection* with dek "A short read on why the industry needs to prove what's human, not chase what isn't."
- `templates/page-notes.html:34–54` — surfaces same essay with dek "Detection chases what isn't. Provenance proves what is."
- `templates/page-notes.html:56–76` — surfaces *Provenance as Substrate* with dek "Music files need fingerprints, not name tags."

The two deks for *Provenance Over Detection* (home.html vs page-notes.html) are different. The page-notes version ("Detection chases what isn't. Provenance proves what is.") is much sharper. Reuse it on home.html. The home.html dek reads as a meta-description, not a hook.

#### C5. RSS footer
- `templates/home.html:107–108` and `templates/page-notes.html:136–138` — same RSS line, intentionally duplicated. **Not a problem.**

### D. IA / labelling

#### D1. Eyebrow inventory

| Eyebrow | File | Line | Pattern |
|---|---|---|---|
| `Dossier · Who I Am` | page-about.html | 11 | "Dossier · X" |
| `Dossier · Background` | page-resume.html | 11 | "Dossier · X" |
| `Catalog · Discography · 2005 → 2026` | page-music.html | 11 | "Catalog · X · range" |
| `Services · 06 Offerings · 02 Sections` | page-services.html | 11 | "Section · count · count" |
| `Get In Touch` | page-contact.html | 11 | bare phrase |
| `Consulting` | page-work-with-me.html | 11 | bare word |
| `Error · 404 · No Signal` | 404.html | 7 | "Section · code · phrase" |
| `Pillar Essay` / `Pillar Essay · March 2026 · [reading time]` | page-notes.html, home.html | 35, 38, 60 | "Type [· date · reading]" |

There are three patterns competing:
1. **Catalog/dossier system** with `·` separators (About, Resume, Music, Services, 404)
2. **Bare phrase eyebrows** (Contact, Work With Me)
3. **Pillar Essay** family (Notes, Home)

**Recommendation:** Pick the dossier/catalog system as canonical. Bring Contact and Work With Me into the same family:
- `page-contact.html` → `Dossier · Get In Touch` or `Contact · 01 Channel · 03 Profiles`
- `page-work-with-me.html` → `Consulting · Paid Consults` or `Booking · 02 Formats`

The current bare-phrase eyebrows on Contact and Work With Me feel like leftover defaults. Either commit to the dossier system across all top-level pages, or commit to bare phrases everywhere. Don't mix.

#### D2. CTA inventory

| File | Label | Destination | Verdict |
|---|---|---|---|
| `parts/header.html:15` | Home | `/` | OK |
| `parts/header.html:16` | About | `/about` | OK |
| `parts/header.html:17` | Services | `/services` | OK |
| `parts/header.html:18` | Music | `/music` | OK |
| `parts/header.html:19` | Resume | `/resume` | OK |
| `parts/header.html:20` | Notes | `/notes` | OK |
| `parts/header.html:21` | Contact | `/contact` | OK |
| `parts/header.html:22` | **Book a Call** | `/work-with-me` | **OK — already fixed.** Header label is now "Book a Call," which correctly telegraphs that `/work-with-me` is a paid booking widget. (The earlier known issue is resolved at the part level. The page H1 is still "WORK WITH ME" — see D2-note.) |
| `templates/front-page.html:29` | Services | `/services` | OK |
| `templates/front-page.html:32` | About Me | `/about` | OK — minor nit: "About Me" vs nav's "About". Pick one. |
| `templates/page-about.html:58` | View My Resume | `/resume` | OK |
| `templates/page-services.html:266` | Get In Touch → | `/work-with-me` | **MISMATCH.** "Get In Touch" reads as a contact form. `/work-with-me` is the paid Cal.com booking widget. Either change label to "Book a Call →" / "Book a Strategy Session →" to match the header, or change the destination to `/contact`. |
| `templates/page-music.html:78` | View All Credits → | external Muso.AI | OK |
| `templates/page-resume.html:29` | View Below ↓ | `#resume-viewer` | OK |
| `templates/404.html:29` | Back to Base | `/` | OK |
| `templates/page-notes.html:50` | Read essay → | `/provenance/over-detection/` | OK |
| `templates/page-notes.html:72` | Read essay → | `/provenance/as-substrate/` | OK |
| `templates/home.html:47` | Read essay → | `/provenance` | OK |
| `templates/single.html:42` | Start with the pillar → | `/provenance` | OK |
| `templates/single.html:46` | ← All Notes | `/notes` | OK |

**D2-note (page H1):** `templates/page-work-with-me.html:15` still has H1 "WORK WITH ME." If the canonical surface label is "Book a Call" (per header), consider whether the H1 should match. Counter-argument: "Work With Me" is a bigger umbrella that covers consults *and* future productized offerings; "Book a Call" is a function. Defensible either way — flag for a decision.

#### D3. Heading-tone audit (h2)

| File | Line | h2 | Tone fit |
|---|---|---|---|
| `templates/page-about.html` | 81 | WHAT I KNOW, I PASS ON. | **Yes** — exemplary, declarative, on-voice |
| `templates/page-services.html` | 256 | LET'S TALK ABOUT YOUR PROJECT | **Marginal** — generic. Compare to "WHAT I KNOW, I PASS ON." |
| `templates/page-music.html` | 51 | VERIFIED CREDITS | **Yes** — does the job, plain and credible |
| `templates/page-work-with-me.html` | 30 | How It Works (eyebrow-style) | **Yes** — small caps, low key, not trying too hard |
| `templates/404.html` | 15 | SIGNAL LOST | **Yes** — perfect for the brand |

The single weak h2 on the site is the Services closing CTA. See §G3 for drafts.

### E. Per-page editorial notes

#### `templates/front-page.html`
**Working:** Title `I BUILD THINGS THAT SOUND RIGHT.` is one of the strongest assertions on the site — first-person, declarative, ends with a value claim. "Sound right" is double-meaning (audio + alignment). Don't touch.
**Weak:** Subtitle `Music production, creative strategy, and the systems that hold them together.` (line 19). "Systems that hold them together" is the line that the prior audit already flagged — confirmed. The construction is also formally weak: list-of-three with the third item being the abstract glue, which is a very common consultant-page pattern.
**Recommended action:** Rewrite the subtitle. Drafts in §G1.

#### `templates/page-about.html`
**Working:** All three bio paragraphs are strong. Lines 40, 44, 48 are the spine of the site's voice. The image alt-text "Juan Lentino at Panacea recording studio" is correct. The Education & Mentorship section's two-column prose (lines 95, 105) is also on-voice.
**Weak:** Line 48 packs every credibility claim ("Over 20 years," "50+ collaborations," "more hours behind the console than I can count," voting member of both academies) into a single em-dashed sentence. It works, but it's doing the job of three sentences and the reader can feel the compression. Also: "Over 20 years" doesn't match the rest of the site's "20+ years" form.
**Recommended action:** None to the personal paragraphs (per audit constraint — Juan's voice). One light cleanup: change "Over 20 years" to "20+ years" on line 48 for canonical-form consistency. Optional: split line 48 into two sentences if it ever feels heavy on a re-read.

#### `templates/page-services.html`
**Working:** The 06-offerings catalog itself (lines 96–241) is well-structured. The "№ 01 / № 02 / №…" numbering and the Music & Production / Business & Strategy section split are exemplary. PRODUCTION (line 100), MIXING (line 124), MASTERING (line 175) all have on-voice descriptions.
**Weak:**
- Intro paragraph (line 19) — see A2.
- OPERATIONS & AI STRATEGY description (line 220) — see A5.
- ARTIST & PRODUCER DEVELOPMENT description (line 240) — see A6.
- Closing CTA (lines 256–266) — see A3 + D2 + G3.
- Cred strip uses "50+ Artists & Labels" — should be "50+ Collaborations" for parity with Resume/About (B-table).
**Recommended action:** Structural rewrite of intro + closing. Light edits to Operations/Artist-Development blurbs. Cred-strip wording fix.

#### `templates/page-music.html`
**Working:** Eyebrow `Catalog · Discography · 2005 → 2026` is the strongest eyebrow on the site — it asserts a 21-year span with a typographic flourish. Intro (line 19) is on-voice ("a whole lot of late nights. Hit play."). Verified Credits section is functional; the Muso.AI panel is well-built.
**Weak:** Line 55 repeats "20+ years" which by this point in the user journey is the third or fourth time they've seen it. Consider trimming to "every credit, every collaboration, every role I've held — verified on Muso.AI." The years-claim has earned its keep elsewhere.
**Recommended action:** Light edit (drop the "20+ years" repetition).

#### `templates/page-work-with-me.html`
**Working:** Process strip (Steps 01–03, lines 38–85) is concise and on-voice. "Pick a time below. Clear milestones, no surprises." (line 65) is excellent. "Transparent pricing. You see every line item." (line 82) — same. The 30/60-minute panel descriptions (lines 114, 137) are good.
**Weak:**
- Subtitle (line 19): "Paid 30- or 60-minute consults for music businesses, artists, and producers. Twenty years of studio operations and creative strategy, on the clock." — the second sentence is good ("on the clock" is on-voice). The first sentence over-explains. Also: "Twenty years" should be "20+ years" for canonical-form consistency.
- Eyebrow `Consulting` is bare — see D1.
**Recommended action:** Light edits to subtitle + eyebrow.

#### `templates/page-contact.html`
**Working:** Line 19 — `Got a project in mind, a question about my work, or just want to talk sound? Fill out the form below and I'll get back to you. I respond to everything that isn't spam.` — this is the cleanest piece of CTA copy on the site. The "Or Find Me Here" eyebrow (line 39) is fine. Everything works.
**Weak:** Eyebrow `Get In Touch` is bare — see D1.
**Recommended action:** None to prose. Eyebrow alignment if D1 is adopted.

#### `templates/404.html`
**Working:** Eyebrow `Error · 404 · No Signal`, headline `SIGNAL LOST`, body `The frequency you're looking for doesn't exist. It either moved, got eaten by feedback, or was never real to begin with.`, and CTA `Back to Base` — every line earns its place. This is the best-written page on the site for its size.
**Recommended action:** None.

#### `templates/page-resume.html`
**Working:** Eyebrow + H1 are clean. Line 19 (intro) is on-voice with the strongest GRAMMY-since-2025 phrasing on the site.
**Weak:** Cred-strip line 23 (`20+ Years · 50+ Collaborations · GRAMMY Voting Member`) and line 19 prose duplicate the same facts back-to-back, three lines apart. The reader gets the cred quartet twice in 15 lines.
**Recommended action:** Drop one of the two. The prose paragraph is richer; the cred-strip is more scannable. If keeping both, change the cred-strip to add information the prose doesn't (e.g. drop "20+ Years · 50+ Collaborations" since the prose already says "20+ years building studios… developing artists… scaling creative businesses," and make the strip something like `Music Production · Strategy · Mentorship` instead — three distinct disciplines).

#### `templates/page-notes.html` and `templates/home.html`
**Working:** The dek (`Working notes on music, AI, and the infrastructure underneath. Written when there's something worth writing.`) is one of the strongest single lines on the site. The pillar-essay cards and RSS footer (`No subscription form, no schedule. Notes available via RSS.`) are perfectly on-voice.
**Weak:** As noted in C4, the *Provenance Over Detection* dek differs between home.html (`A short read on why the industry needs to prove what's human, not chase what isn't.`) and page-notes.html (`Detection chases what isn't. Provenance proves what is.`). The latter is much stronger.
**Recommended action:** Use the page-notes.html dek (line 46) on home.html (replace line 43).

### F. Prose cleanups

| # | File | Line | Issue | Proposed fix |
|---|---|---|---|---|
| F1 | `templates/page-about.html` | 48 | "Over 20 years" — non-canonical form | "20+ years" |
| F2 | `templates/page-services.html` | 19 | "Twenty years" — non-canonical form | "20+ years" |
| F3 | `templates/page-services.html` | 43 | "50+ Artists & Labels" — non-canonical noun | "50+ Collaborations" |
| F4 | `templates/page-work-with-me.html` | 19 | "Twenty years" — non-canonical form | "20+ years" |
| F5 | `templates/front-page.html` | 32 | "About Me" button label vs nav label "About" | Pick one; "About" matches nav |
| F6 | `templates/home.html` | 43 | Dek diverges from canonical version on `/notes` | Replace with "Detection chases what isn't. Provenance proves what is." |
| F7 | site-wide | — | Em-dash usage is **consistent** (`—` everywhere checked); smart quotes (`'` `'`) used consistently. **No fix needed.** Worth flagging as a positive: the typography is clean. | — |
| F8 | `templates/page-music.html` | 55 | "every credit, every collaboration, every role I've held across 20+ years" — third use of "20+ years" by this stage | "every credit, every collaboration, every role I've held" |
| F9 | `parts/footer.html` | 13 | `[current_year]` shortcode — verify this resolves correctly in PHP-rendered output. (Not a prose issue per se, but worth a smoke check on the live site.) | Verification only |

**Punctuation note:** A `&middot;` HTML entity is used in cred strips (`page-resume.html:23`, `page-music.html:46`) where most other places use the literal `·` character. Both render the same; this is a code-style consistency issue, not a content one. Optional cleanup.

### G. Suggested rewrites — drafts only

These are **drafts for the maintainer to edit**, not final copy. Each draft is calibrated against the voice fingerprint above. Pick one, edit it, or ignore them all.

#### G1. Front-page hero subtitle (replaces `front-page.html:19`)

> Current: *"Music production, creative strategy, and the systems that hold them together."*

- **Draft A (declarative, narrowest):** "Records, mixes, and the businesses that put them out."
- **Draft B (declarative, slightly wider):** "I produce records, mix sessions, and help studios and labels operate without breaking."
- **Draft C (closer to a manifesto):** "Twenty years on the production side. Now also on the business side. Same ear, different console."

Notes for picking: Draft A is shortest and most assertive — closest in shape to the H1. Draft B is the most descriptive of the actual work mix. Draft C does the most narrative work but is the longest.

#### G2. Services page intro (replaces second sentence of `page-services.html:19`)

> Current: *"Twenty years of building studios, producing records, and running creative businesses — across Buenos Aires, Orlando, and everywhere in between. Whether you need someone behind the console or behind the strategy, the approach is the same: deliberate, thorough, and built to last."*

- **Draft A (cut the abstract close):** "20+ years of building studios, producing records, and running creative businesses — across Buenos Aires, Orlando, and everywhere in between. Whether you need someone behind the console or behind the strategy, the work is the same: figure out what the project actually is, then deliver it."
- **Draft B (replace the close with a specific):** "20+ years of building studios, producing records, and running creative businesses — across Buenos Aires, Orlando, and everywhere in between. Some clients hire me for the ear. Some hire me for the operations. The good ones hire me for both."
- **Draft C (trim hard):** "20+ years building studios, producing records, and running creative businesses — across Buenos Aires, Orlando, and everywhere in between. Whether you need someone behind the console or behind the strategy, you get the same person, with the same standards."

#### G3. Services closing CTA (replaces h2 + body + button at `page-services.html:256–266`)

> Current: h2 *"LET'S TALK ABOUT YOUR PROJECT"* + body *"Whether it's a record, a business problem, or a workflow that needs fixing — I'd rather hear about it than guess. Tell me what you're working on."* + button *"Get In Touch →" → `/work-with-me`*

- **Draft A (keep first sentence, harden close, fix button):**
  - h2: `START THE CONVERSATION` or `BRING IT IN`
  - body: "Whether it's a record, a business problem, or a workflow that needs fixing — I'd rather hear about it than guess. The first call is yours to scope."
  - button: `Book a Call →` → `/work-with-me` *(or keep "Get In Touch →" but redirect to `/contact`)*

- **Draft B (lean into the dual-track frame):**
  - h2: `TWO DOORS`
  - body: "If it's paid consulting work, the booking page is below. If it's anything else — a question, a project to discuss, a referral — the contact form is the right surface."
  - buttons: `Book a Call →` (`/work-with-me`) + `Contact →` (`/contact`)

- **Draft C (minimal):**
  - h2: `LET'S TALK`
  - body: "Tell me what you're working on. I respond to everything that isn't spam." *(borrow from `page-contact.html:19` — but only do this if the maintainer wants the line to live in two places.)*
  - button: `Contact →` (`/contact`)

The button mismatch (D2) **must** be resolved regardless of which draft is picked.

#### G4. Operations & AI Strategy blurb (replaces `page-services.html:220`)

> Current: *"Sustainable business models, streamlined operations, and AI-assisted workflows that actually work. I help studios, labels, and creative companies build systems that scale — grounded in a decade of running my own studio and an MBA in Applied AI."*

- **Draft A:** "Pricing models that hold up, operations that don't bottleneck on you, and AI workflows that actually save time instead of pretending to. Grounded in a decade running my own studio and an MBA in Applied AI."
- **Draft B:** "I help studios, labels, and creative companies fix the parts of their business that the creative work usually gets blamed for. Pricing, operations, AI integrations that earn their keep. A decade running my own studio and an MBA in Applied AI behind every recommendation."

---

## Top 5 prioritized actions

1. **Resolve the Services CTA button mismatch.** `templates/page-services.html:266` "Get In Touch →" points at `/work-with-me` (paid Cal.com). Either rename to "Book a Call →" or repoint at `/contact`. **Ship-ready edit, no drafting needed.**
2. **Pick canonical forms for the credibility quartet and propagate.** "20+ years" / "50+ collaborations" / "GRAMMY voting member" / "MBA in Applied AI" — current spread is 3+ phrasings each. **Mostly ship-ready: F1–F4 in §F can be applied directly.** Optional restructuring of Resume's double-cred-strip needs maintainer input.
3. **Rewrite the front-page hero subtitle.** Single line on the most-trafficked page. Drafts in §G1 — **needs maintainer voice to land.**
4. **Rewrite the Services intro and closing CTA.** Two passages; both currently the weakest prose on the site. Drafts in §G2 + §G3 — **needs maintainer voice to land.**
5. **Bring Contact and Work With Me eyebrows into the Dossier/Catalog system, and replace the home-page pillar dek with the stronger one from `/notes`.** Small consistency wins. **Ship-ready: F6 + D1 alignment.**

**Maintainer-voice items (drafts only):** 3, 4, parts of 2.
**Edits the auditor can ship without further input:** 1, F1–F4, F6, F8, the cred-strip noun fix in F3.

---

## Out of scope

The following templates render `<!-- wp:post-content /-->` for the body of the page and pull prose from the WordPress database, not the theme files. The prose-level audit cannot be done from the repo:

- `templates/page-contact.html` — form area at line 29 is post-content (Fluent Forms shortcode lives in the WP page record). Title and connect-section prose **are** in the file and were audited.
- `templates/page-music.html` — Spotify embeds at line 29 are post-content (the embed-block markup lives in the WP page record). Eyebrow, intro, and Verified Credits section **are** in the file and were audited.
- `templates/page-resume.html` — PDF viewer at line 41 is post-content. Title, intro, and meta strip **are** in the file and were audited.
- `templates/page-provenance.html` — entire body is post-content. Pillar essay copy lives in the WP page record. (Body templates exist as seeds in `inc/seed-content/{provenance,over-detection,as-substrate}-body.html` but are out of scope for this template-level audit.)
- `templates/single.html` — body is post-content (per-Note prose lives on each post). Footer pillar/back links **are** in the file and were audited.
- `templates/page.html` — bare wrapper around post-content. Used by any custom Page that doesn't have a more specific template. Nothing in the file to audit.
- `templates/index.html` — query loop with post-titles/excerpts; no inline prose.
- `templates/home.html` and `templates/page-notes.html` — most of the body is `wp:query` over Notes; the inline pieces (dek, RSS line, pillar cards) **were** audited.

For these, prose-level QA happens in the WP admin (page editor + post editor), not in this repo.
