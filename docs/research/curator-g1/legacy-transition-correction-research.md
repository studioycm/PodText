# Curator G1 Legacy Media Transition Correction Research

## Decision record

- Audit ID: `LS-20260721-CURATOR-G1-LEGACY-MEDIA-TRANSITION-CORRECTION-03`
- Approved option: `CURATOR-G1-LMTC-O1-IN-PLACE-JOURNALED-TRANSITION-DEFAULT-FALLBACK`
- Approval date: 2026-07-21
- Stage: Stage 2 implemented locally; final repository gates and canonical
  closeout pending

This is an ad-hoc correction to the implemented G1 transition. It does not
reopen the broad Curator architecture. The approved outcome is a safe path from
valid legacy data to the existing strict app-owned gallery, plus controlled
owner repair and default-image fallback when the legacy identity cannot be
trusted.

## Exact baseline

The Stage 2 preflight observed:

- working directory and Git root: `/Users/studioycm/Herd/PodText`;
- branch: `main`;
- HEAD: `d1732f8269fe9b647e435a0f36785380f92f4496`;
- `origin/main`: `7c55dca4012ce48779b32b2e3c4d2076d9198807`;
- local `main` two commits ahead of `origin/main`;
- clean worktree and no overlapping Curator/media/content/settings changes;
- G1 implementation commit `fa5b57c` and hash-stamp commit `d1732f8`
  preserved;
- no prompt under `prompts/pre-13-prompts/` active.

Installed source, without dependency changes:

- Laravel 13.19.0;
- Filament 5.6.7;
- Livewire 4.3.3;
- Pest 4.7.4;
- `awcodes/filament-curator` 5.1.2 at source reference
  `2a79bf031099d2d75351377eae15322fb590ab43`.

Installed application and vendor source remain authoritative. Laravel Boost
provided installed-version guidance for migration paths and steps, console
commands, Filament actions/tables, Livewire actions and testing. The required
FilamentExamples multi-query and refinement passes exposed search snippets
only. The returned examples were Filament v4 material, and no source/detail
reader was available, so they are neighboring ideas rather than proof for this
Filament 5 implementation.

## Incident evidence accepted for reconciliation

The verified 2026-07-21 snapshot is not reproduced with development database or
storage commands in Stage 2. It records 15 existing Curator rows, all with null
keys and present files; ContentGroup legacy paths referencing IDs 1-5; settings
paths referencing ID 9; SVG IDs 6-7; root-level IDs 12-15; and no attachment or
mutation rows. The data-free relational migrations and settings-shape migration
had run. The strict gallery became empty and an owner image action mounted into
an uncaught disallowed-row exception.

## Diagnosis reconciliation

1. **Confirmed.** `MediaRecordScope` requires an immutable
   `reference_key`. Existing null-key rows disappear from normal gallery,
   picker, browse, search, selection, view, download and mutation surfaces.
2. **Confirmed.** `MediaIdentityResolver::uniqueMediaForPath()` first locates
   the raw Curator row, then applies the strict scope and throws when the row is
   disallowed. That exception currently propagates through attachment form
   state into the ContentGroup/ContentItem action mount.
3. **Confirmed and retained.** `StoredMediaValidator` issues a key only after
   content, metadata, normalized-byte and checksum proof. A null key alone is
   never authority to mint a trusted identity.
4. **Confirmed.** A valid legacy JPEG/PNG that changes under the required
   canonical re-encode is deliberately ineligible for the present key-only
   backfill.
5. **Confirmed.** `LegacyMediaRegistrationPlanner` treats a matching existing
   Curator row as `existing_media`; `RegisterExistingCuratorMedia` therefore
   cannot transition that row.
6. **Confirmed.** Attachment and settings key backfills depend on a trusted,
   keyed row and cannot close the state above.
7. **Confirmed.** Existing tests separately prove canonical-byte backfill and
   registration without an existing Curator row. They do not prove the
   production-shaped combination: existing Curator row, noncanonical valid
   raster and active owner/settings reference.
8. **Confirmed.** The implemented commands leave that valid active legacy media
   without an executable transition.
9. **Confirmed.** Ordinary media operations must remain fail-closed. An owner
   edit/list action must instead return a typed diagnostic and offer authorized
   replacement or detach-to-default without previewing or trusting the bad row.
10. **Confirmed.** Cache clearing, removing SP3A pass-through middleware or
    rerunning the same migrations cannot normalize bytes, establish checksum
    proof, issue a key or create attachments.

## Root cause and gate miss

The implementation correctly built a strict steady-state trust boundary but
did not close every legacy input state into an executable transition. The
registration route handled a file with no Curator row; the key backfill handled
an already-canonical Curator row. Their intersection was absent. Separate
green A, B and C tests did not prove A+B+C. Final gates consequently proved the
implemented cases, not the production-shaped state machine.

Every cutover now needs a **Transition Closure Gate**:

1. every preflight status maps to one executable action or an explicit block;
2. one combined fixture matches the real legacy row, bytes and references;
3. preflight exits non-zero when an active reference has no reviewed executable
   transition, detach or blocked disposition;
4. manifest and digest tests prove the exact reviewed plan cannot drift between
   dry run and apply.

## Invariants that remain strict

- Curator remains image-only with a positive MIME/extension allowlist.
- Raster normalization and strict staged SVG sanitation remain mandatory.
- Public disk, public visibility and fixed roots remain mandatory.
- Root-level legacy files do not weaken the allowed-root policy.
- Livewire record IDs, submitted paths and client state remain untrusted.
- The existing Admin-or-higher authorization boundary remains; no Shield
  architecture is introduced.
- Stable immutable reference keys are portable identity. Numeric Curator IDs
  are local relational identity.
- A reference key is issued only after content validation, canonical output and
  SHA-256 proof. Null does not mean safe; null plus complete proof may enter
  `key_only` or `normalize_existing`.
- Settings resolve reference key first and legacy path only as compatibility
  fallback. Import/export continues to use reference keys.
- Shared `MediaAttachment` relationships and singleton owner/role constraints
  remain.
- Disallowed rows and bytes never enter normal browse, preview, download,
  selection, rename, swap or delete surfaces.
- Read paths never mutate. Missing, corrupt or blocked owner media renders as
  absent, allowing the configured family/system default image to resolve.

## Schema-independent preflight contract

Preflight reads installed schema capability, Curator metadata, fixed-root file
facts, owner paths, attachment state, settings paths/keys and relevant journal
state without assuming that every G1 migration has run. It emits a stable,
sorted manifest. Each candidate includes the source identity and SHA-256,
detected MIME/extension/dimensions, metadata agreement, allowed purpose/root,
Curator ID and key, owner/settings references, attachment state, journal state,
disposition and exact executable next action.

The digest is calculated from canonical serialization of all decision-bearing
fields. Apply requires the expected digest and replans under locks. Changed
bytes, references, row identity, destination, operation or disposition reject
the apply; no best-effort continuation is allowed.

Dispositions are exhaustive:

| Disposition | Meaning and action |
|---|---|
| `key_only` | Existing allowed raster is already canonical. Verify checksum and metadata, issue the immutable key under the journal/fence, then allow ordered attachment/settings backfills. |
| `normalize_existing` | Existing allowed raster is valid but noncanonical. Run the approved same-ID O1 transition. |
| `sanitize_svg` | Existing SVG requires the reusable sanitation engine and exact approval. IDs 6 and 7 remain untouched in this task. |
| `import_exact_path` | Fixed-root file has no conflicting Curator identity. Import only an exact reviewed path through normalization/sanitation and the same proof boundary. |
| `detach_to_default` | An owner holds missing, corrupt or otherwise unusable legacy identity. Authorized explicit repair clears that owner identity; public/admin rendering uses the configured default. Evidence remains excluded. |
| `blocked` | Duplicate/ambiguous identity, wrong root, stale plan, conflicting references, missing proof or other unsafe state. Report exact reason; do not weaken scope or guess. |

Root-level IDs 12-15 remain `blocked` or explicitly dispositioned by a later
review. They are not imported by expanding allowed roots. Existing SVG IDs 6-7
remain under the separately approved production sanitation runbook.

## Approved same-ID O1 algorithm

For `normalize_existing`:

1. require Admin actor, exact operation key and expected manifest digest;
2. lock/replan the Curator row, active references and incomplete operation;
3. create or resume one durable operation and acquire its mutation fence;
4. copy the exact original bytes to private checksum-verified quarantine;
5. normalize into private staging and validate the staged result;
6. copy to a generated allowed public destination, then verify size and
   SHA-256 independently;
7. in a short transaction, recheck the fence and digest, preserve the numeric
   Curator ID, update its storage metadata, issue/preserve the immutable key,
   and atomically switch compatible owner paths, attachments and settings
   path/key pairs;
8. mark database commit before any destructive cleanup;
9. after commit, invalidate old/new Curator, Glide, placeholder and palette
   cache identities; remove the old public source only when zero references
   remain; retain the private original according to the existing quarantine
   contract; and mark cleanup complete.

Crash before the database commit leaves the old identity and source active;
staging/destination artifacts are journal-owned and resumable. Crash after
commit leaves `cleanup_pending`; exact-operation repair resumes idempotently.
A stale/foreign fence holder cannot commit or finalize. Existing matching
destination bytes are accepted only after checksum proof; conflicting bytes
block. Missing source, changed bytes/references, duplicate identity and stale
curation block before switch. Cleanup never causes rollback of an already
committed trusted identity.

Same-ID O1 is preferred over creating a replacement row because it preserves
local foreign identity and makes rollback/repair smaller. Atomic replacement is
not needed for the verified shape and remains outside this correction.

## Owner repair and default fallback

ContentGroup and ContentItem form/action preparation must catch only the typed
legacy-media diagnostic, not suppress arbitrary exceptions. It returns small
database-derived status state and must not hash/decode files per table row.
Lists may show one localized warning/action for affected records without
query-per-row behavior. Edit/action mounts stay usable.

Authorized choices are:

- choose a strict-gallery image, replacing the owner identity through existing
  validated attachment/form behavior without exposing the old bytes;
- explicitly detach the unusable legacy identity, recording the reviewed
  action and clearing compatible owner path/attachment identity; or
- leave it unchanged while operators run the preflight transition.

After detach, or while public resolution finds no trusted image, the value is
treated as no image and the configured ContentGroup/ContentItem/system default
image chain is used. No automatic read-time database change is allowed.

## Reusable sanitation and exact-path recovery

The sanitizer must be a reusable journaled engine, not an IDs 6/7 special case.
It accepts one exact reviewed fixed-root SVG, sanitizes in private staging,
validates output, uses a generated destination, verifies checksum and switches
reviewed references under the same digest/fence guarantees. This task adds the
mechanism and isolated fixtures only; it does not sanitize Media IDs 6 or 7.

Exact-path import is the bounded recovery seam for a manually added file that
has no conflicting row. It never scans into the normal gallery, never previews
untrusted bytes and never imports a whole directory. A future secondary
recovery gallery or file-manager plugin remains deferred.

## Cutover ordering

No command below is authorized for production by this implementation task.
After a future environment-specific review and approval:

1. run the schema-independent preflight and retain manifest/digest;
2. apply only the four explicitly allowlisted G1 migrations, never broad
   `migrate --force`;
3. rerun preflight and resolve `normalize_existing`, approved `sanitize_svg`,
   `import_exact_path`, `detach_to_default` or explicit `blocked` entries;
4. run `key_only`/reference-key work before attachment backfill;
5. run attachment backfill before settings key backfill;
6. rerun every dry run and integrity report; require zero active reference with
   no executable or explicitly blocked disposition and no incomplete journal;
7. activate normal media writes only after the Transition Closure Gate passes.

All transition/backfill operations are dry-run first, exact-digest apply,
idempotent and journal-aware. A broad migration rollback is forbidden. Dormant
empty permission tables may safely remain; any targeted cleanup is unrelated
future work.

## Test proof required

- deterministic manifest ordering, canonical digest and schema capability
  matrix;
- all dispositions and closure-gate non-zero exit;
- production-shaped existing-row + noncanonical-raster + owner/settings
  fixture;
- key-only proof and idempotence;
- O1 same-ID success, retry and every pre/post-commit failure point;
- duplicate destination, missing/changed source, stale digest/reference,
  foreign lease, cache cleanup and cleanup resume;
- reusable SVG sanitation with malicious/changed fixtures, without real IDs;
- exact fixed-root import and wrong-root/duplicate refusal;
- ordered idempotent attachment/settings backfills and integrity categories;
- safe ContentGroup/ContentItem list/edit/action mounts, replacement,
  detach-to-default, authorization, forged input, Hebrew/English text and
  bounded query counts;
- strict gallery/picker/view/download exclusion remains unchanged.

## Scope classification

### Correction required

- preflight, exhaustive dispositions, digest and closure gate;
- same-ID journaled raster normalization;
- reusable SVG sanitation engine and exact-path import seam;
- backfill/integrity reconciliation and production-shaped fixture;
- typed owner diagnostics, safe replacement and detach-to-default UX;
- migration/cutover documentation corrections.

### Already correct

- strict gallery scope, allowlists, fixed roots and authorization;
- canonical-byte validator, immutable key contract, attachment schema;
- journal, mutation fence and copy-verify-commit-cleanup primitives;
- reference-key-first settings and portable import/export contracts.

### Production-only

- environment inventory and backup;
- applying migrations, transitions, backfills, repairs or sanitation;
- deployment, cache/process action and final production verification.

### Unrelated or deferred

- dependency upgrades;
- full filesystem/recovery gallery or file-manager plugin;
- sanitation of actual Media IDs 6/7;
- disposition of root-level IDs 12-15 beyond explicit reporting;
- cleanup of dormant empty permission tables.

### Blocked

- any candidate whose current manifest cannot produce checksum proof, unique
  identity, allowed root/purpose and a reviewed executable disposition.

## Schema, dependency and effort conclusion

No new schema or dependency is required. Existing journal, attachment and
Curator key schema support the correction. Implementation is five sequential
work packages with an audit forecast of 60-90 engineering hours; discovery
showed no material schema, dependency, security-boundary or scope drift that
would require another Stage 1 audit.

## Post-implementation source reconciliation

The implementation matches the approved architecture without schema or
dependency drift:

- `LegacyMediaTransitionPlanner`, `LegacyMediaTransitionManifest`, and
  `LegacyMediaTransitionDisposition` own the schema-aware canonical manifest,
  exhaustive classifications, decision fingerprints, and digest;
- `PreflightLegacyMediaTransition` is the read-only human/JSON closure gate;
- `LegacyMediaTransitionExecutor` accepts one exact ID or one exact rowless
  path and delegates same-ID/rowless copy-verify-switch work to the existing
  `MediaFilesystemMutationCoordinator`, `MediaMutationFence`, and
  `MediaMutationLease` boundaries;
- `LegacyMediaReferenceSwitcher` atomically rechecks and switches reviewed
  owner, attachment, and settings references while preserving same-ID local
  identity for an existing row;
- the three backfills, registration command, and integrity report now consume
  the transition vocabulary and exact digest/order contract;
- `LegacyOwnerMediaDiagnostics` produces database-metadata-only typed
  fingerprints, while `LegacyOwnerMediaRepairer` performs an authorized,
  locked, database-only replacement/detach journal without touching unsafe
  bytes;
- ContentGroup/ContentItem list, relation, edit, action, and public-default
  paths expose repair/default behavior while ordinary Media abilities remain
  strict; and
- focused regression coverage includes the 300-owner/2-SVG/default/settings
  production shape, same-ID raster/SVG and rowless paths, compensation/retry,
  real Filament/Livewire repair actions, forged/stale state, and 1/10/50
  database-only diagnostics.

The real IDs 6/7 operation, production cutover, root-level IDs 12-15
disposition, recovery gallery, dependency upgrades, and permission-schema
cleanup remain outside the implementation.
