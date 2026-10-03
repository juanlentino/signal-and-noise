# A second page: design (2026-10-02)

**Status: partly superseded the same day.** The header order (1) stands. The three page-ending lines (2 to 4, and /provenance's, added later) were removed in theme 14.9.0 and plugin 20.6.0: the header stays on screen while scrolling, so the lines repeated links already visible. The direction taken instead (theme 14.9.0): links where the reader already is. Each pillar essay names the next one, a note's pillar link goes to the first essay, and "More on this" skips notes the page already offers. Each link carries a goal (`next_essay`, `note_pillar`) so the re-read around 2026-10-30 can measure it.

## The problem

Over the last 30 days (Sep 3 to Oct 2, human traffic) the site averaged 1.03 pages per
visitor-day. Three top landing pages were dead ends: /resume ended on the skills list
(only a Download PDF link), /music ended on the last cover (no links at all), and the
home hero carries two links. The header had seven items and no route to the research
except through About or a note.

## Who it is for

All three audiences equally (owner): hiring managers and recruiters, music clients,
and research peers. The design hands each audience's page to the other two.

## What ships (option A, owner-approved)

1. **Header order:** Home, About, Resume, Music, Services, Notes, Contact. Still seven
   items. Provenance stays out of the header: /notes already opens on the three pillar
   essays and "First time? Start here", so a /notes band (first proposed) was dropped
   as a repeat.
2. **/resume ends with:** "Beyond the record: the research · the music"
   (the research to /provenance, beacon goal `next_research`; the music to /music,
   `next_music`). Plugin 20.5.0, `sn_resume_next_blocks()`; lands on the next resume sync.
3. **/music ends with:** "Beyond the catalog: the record · get in touch"
   (the record to /resume, `next_record`; get in touch to /contact, `next_contact`).
   Theme 14.8.0, `inc/discography-render.php`.
4. **Style:** `.sn-page-next`, the catalog-meta voice with a hairline above and links in
   blood; hover thickening inside `@media (hover: hover)`.

Home stays as it is: the split hero is frozen by the owner's design rule.

## Rejected

- A dropdown nav grouped by audience (an extra tap; about half the traffic is mobile).
- A "for hiring / for artists / for research" block on every page (reads as sales copy).
- One "Research" or "Writing" header item (buries Notes, or files the non-provenance
  notes under research).

## How we will know

Baseline, last 30 days: exits from /resume 9 and /music 5; pages per visitor-day 1.03;
228 visitor-days; the four goals at zero. Read again around 2026-10-30
(`sn-metrics` journeys, summary and analytics_events). At 15 to 21 views a month per
page, read direction, not percentages.
