# Notes MCP drafting harness

How the `sn` (read) and `sn-write` (write) MCP tools behave when drafting and editing
Notes. Every statement below was verified against the live tool descriptions and, where
behavior was in question, against a live call, on 8 August 2026 (plugin v10.66.x).

This file exists because several of these facts were previously carried in session
memory rather than written down, and the remembered versions had drifted. Correct a
claim here rather than re-deriving it in a session.

## Corpus size

As of 8 August 2026 the archive is **30 published and 11 scheduled** Notes (41 total,
`list-posts` with `status: any`, category Notes). Scheduled posts carry status `future`.

Do not quote a corpus figure from memory. Re-read `list-posts` and count.

## Drafting a Note

### `create_draft` is revision-only

`mode: "publish"` refuses. The tool never makes a draft live. Under `mode: "revision"` a
real draft post is created (there is no no-op staging for a post that does not exist
yet). Publish and schedule dates are set by hand in wp-admin.

Target is `{new_post: true}`, with no id. Payload is
`{title, content (Gutenberg block markup), excerpt?, tags? (existing vocabulary only)}`.

### `delete_draft` exists, and a created draft *can* be rolled back

`delete_draft` (v10.58.0) makes `create_draft`'s advertised rollback real. It is:

- **Revision-only**, the mirror of `create_draft`.
- **Trash-only**: `wp_trash_post`, never a hard delete. Recoverable from the wp-admin
  Trash until WordPress's own purge.
- **Draft-only**: any other status refuses at gate 2 and again inside the write.

`change.fingerprint` is **required** and binds to the draft's current `content_hash`.
Two ways to get it: `create_draft`'s rollback object carries it directly, or read
`gates.fingerprint.observed` from a `dry_run: true` call. Missing is a 422, stale is a 409.

The response's `rollback.method` is `"manual_untrash"`. Restoring from Trash is a
wp-admin action, deliberately not an MCP method.

## Editing links

### `link_reshape` (v10.58.0)

Moves an existing `<a>`'s boundaries within one text node. It is the **only** path to
change where an anchor starts and stops, because `sentence_replace` is plain prose and
would delete the link.

`new_anchor` must be a contiguous substring of `current_anchor`, occurring exactly once
within it. That constraint is what makes the operation pure tag movement with
byte-identical rendered prose, and it is asserted server-side after the splice.
`current_anchor` is the `<a>`'s exact inner text, byte-exact from stored content;
`context_snippet` disambiguates identical anchors.

`href` and every other attribute carry over from the existing tag. They are not
parameters. **Retargeting is not this tool.**

Because the provenance ledger signs normalized prose with markup stripped, a reshape
coalesces to no new commit.

### `unlink` (v10.59.0)

Removes the `<a>` wrapper and keeps the text. Attributes are discarded with it. Same
fingerprint binding, same locator, same server-side prose-identity assertion as
`link_reshape`. (`link_reshape` with `new_anchor: ""` refuses; use `unlink`.)

## Batching prose edits: `change.payload.edits`

`change.payload.edits` (v10.66.0) applies N prose splices to one post in **one write**.
Available for `sentence_replace`, `emdash_replace` and `drift_replace` only.

**Use it whenever a scan returns more than one candidate for the same post.** Without
it, each candidate is its own call and its own `wp_update_post()`, and for a Note that
means one anchored provenance ledger version *per candidate*. A single logical edit (an
em-dash pair becoming parentheses) then permanently records an intermediate,
half-converted state nobody intended to publish.

- All-or-nothing. Any edit that fails to validate, locate or match refuses the whole
  batch, naming the 1-based edit index. Two edits claiming overlapping byte ranges are
  a 422.
- Maximum 50 edits per call.
- Every edit is located and fingerprint-checked against the **original** content, and
  splices apply in descending position order, so edits inside each other's 80-char
  fingerprint window are fine and no offset needs re-resolving.
- The drift family carries a per-edit fingerprint (its fingerprints are minted per
  candidate). `sentence_replace` does not, because `change.fingerprint` (the whole-post
  `content_hash`) already binds the entire batch.
- **Not available** for `link_insert`, `link_reshape` or `unlink`. Those rewrite markup
  rather than prose and can interact through tag structure in ways a byte-range overlap
  check cannot see.

When `payload.edits` is present, top-level `payload.phrase` and `payload.replacement`
are unused, and `diff.edits_applied` reports the count.

## The `links` validation surface is host-relative

`sn-validate` supports a drafting harness: `post_id` is required, but the proposed
content does not have to belong to that post. Pass any existing corpus `post_id` with
`compare_against: "none"` and the `body`, `tags`, `excerpt` and meta checks evaluate the
proposed content entirely on its own terms.

**`links` is the exception, and `compare_against: "none"` does not detach it.** Two
distinct couplings, both verified live against host post 1661:

1. **`target_exists` errors on anything not `publish`.** A scheduled (future-dated)
   target is reported as "does not exist or is not published". Verified: a link to post
   2213 (status `future`) returned a `target_exists` ERROR.
2. **When `proposed.body` is omitted, `not_already_linked` and `anchor_present` resolve
   against the host post's body.** Verified: `links` alone, with no `proposed.body`,
   returned a false `anchor_present` error because the anchor was checked against 1661's
   content. Supplying `proposed.body` fixes both checks; the same call with a body
   passed returned a clean pass.

`not_self` also always compares `target_post_id` against the host `post_id`, so a draft
that legitimately links to the post you borrowed as a host will falsely error.

**Practical rule:** validate `body`, `tags`, `excerpt` and meta with
`compare_against: "none"`, omit `links`, then re-validate against the real `post_id`
after `create_draft`.

### A Note body must not link to a scheduled post

The target 404s until that post publishes. Publish order therefore constrains the
schedule date, and this is a real editorial constraint, not only a validator artifact.

## Verified as NOT true

- **`checks: "all"` is accepted.** The bare string works, including in the drafting
  harness alongside `proposed` and `compare_against: "none"` (verified live 8 August
  2026; `inc/abilities-sn-validate.php:216` accepts it, and v10.41.1 added transport
  tolerance for a stringified array). If a call is rejected, read the actual error
  rather than reaching for an array as a superstition.
