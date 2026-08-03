# Media UX Redesign — Design Principles (archived)

Archived 2026-08-03 by the dashboard route-plan orchestrator session, closing
the `unarchived-binding` sighting #2 in
`docs/research/defect-cause-patterns.md`: these principles governed the media
UX redesign that the media operations UX3 handoffs build on, but their text
lived only in operator-local PDFs. Source of record:
`podtext-media-ux-redesign.pdf` (2026-07-24, six principles) and
`podtext-media-ux-redesign-v2.pdf` (2026-07-24, adds P7–P8); text extracted
verbatim, Hebrew UI fragments restored from the RTL extraction reversal.

Naming note: this list's "P" numbering predates the 2026-08-03 naming
convention (bare letter+digit families are no longer minted). It is kept
as-published here because the media docs and PDFs cite it; qualify as
"media principle P4" when citing outside this file.

## The mental model the principles serve

Three lenses over one permanent library, each with its own verbs:
**Browse** (הספרייה, steward the collection at `/admin/media`) ·
**Choose** (בחירה לתוכן, give a podcast/episode/settings slot its picture,
committed with the owner) · **Care** (הרשומה, one record's identity, usage,
health, and — fenced off — its file surgery).

## The principles

**P1 · Answer before ask.** Every surface states its context ("what is
showing now, and why") before offering any action.

**P2 · Two verbs, two temperatures.** "Change what this podcast shows" is
amber and routine. "Change this stored file" is fenced, red-bordered, and
always shows who's affected first.

**P3 · Usage is the second fact.** After the picture itself, the most
important thing about a media record is who uses it. It appears on every
card, always.

**P4 · The reason is the message.** Never "needs repair" alone. The chip
names the problem; clicking it opens the cohort of records sharing it.

**P5 · One green thread.** Each flow has exactly one pending→commit path.
Staged choices wear a "נבחר — יוחל בשמירה" chip; one button saves.

**P6 · Quiet by default.** Storage trivia and standing warnings retreat to
the record view and to the moment they're relevant. Cards breathe.

**P7 · Three kinds of trouble, three chips.** *(v2 addition.)* Diagnosis
(הקובץ חסר), an unfinished operation (פעולה שלא הושלמה — the mutation journal
already tracks these), and lifecycle (אשפה, future) are different states with
different owners. They never share one badge.

**P8 · Leave seats for what's coming.** *(v2 addition.)* Names and layout
reserve room for Files Discovery and Trash (Package 5): today's delete says
«מחיקה לצמיתות», and the health system is built per-reason so lifecycle chips
slot in later. No fake or disabled placeholder controls.
