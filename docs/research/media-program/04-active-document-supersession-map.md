# Media Program Active-Document Supersession Map

## Rule

Historical research, plans, handoffs, commands, measurements, and no-mutation
statements remain true for the run that produced them. Supersession changes
future authority; it does not rewrite history.

## Active controlling documents

| Document | Role |
|---|---|
| `docs/phase-02/media-program-context.md` | Short resume/status helper; update after every package and material decision. |
| `docs/research/media-program/00-media-program-requirements-decisions-and-method.md` | Final operator requirements, stable decisions, invariants, exclusions, and working method. |
| `docs/research/media-program/01-media-program-research.md` | Current source/vendor/schema/data evidence and architecture reconciliation. |
| `docs/research/media-program/02-media-program-master-plan.md` | Sequential five-package implementation contract. |
| `docs/research/media-program/03-local-production-baselines.md` | Correct dual schema/data profiles; not an execution manifest. |
| `docs/research/media-program/04-active-document-supersession-map.md` | This authority map. |
| `docs/research/media-program/packages/*.md` | Active package research/plan only for the selected package; later package files remain routes until reconciled. |
| `docs/phase-02/current-project-state.md` | Repository-wide rolling state and next route. |
| `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md` | Repository mini-step ledger; MediaAsset program is the selected ad-hoc route while active. |

## Active summaries that require reconciliation

### `docs/phase-02/images-media-track-plan.md`

Classification: active historical summary with its forward route superseded.

Retain:

- IMG-A/IMG-B/IE1 delivered behavior;
- app-owned public rendering;
- stable portable identities;
- current content/relationship/import/export facts;
- SSRF and image validation requirements.

Supersede:

- Curator as permanent canonical media authority;
- Curator row/path as the lifecycle ownership boundary;
- fixed physical directory as selection boundary;
- the prohibition on schema beyond G1;
- future acquisition routed directly to Curator/path contracts.

Forward links must point to the MediaAsset registry, helper, research, master
plan, and current package.

### `prompts/README.md`

Classification: active prompt index. Add an ad-hoc MediaAsset program route and
state that no pre-13 prompt file controls this run. Preserve the main prompt
sequence as separate roadmap context.

## Historical compatibility evidence

The following remain evidence for implemented behavior, incident mechanics,
tests, and reusable safety algorithms. They do not control the forward
MediaAsset architecture:

- `docs/research/curator-g1/image-library-o2-research.md`
- `docs/research/curator-g1/image-library-o2-implementation-plan.md`
- `docs/phase-02/curator-g1-image-library-o2-handoff.md`
- `docs/research/curator-g1/legacy-transition-correction-research.md`
- `docs/research/curator-g1/legacy-transition-correction-implementation-plan.md`
- `docs/phase-02/curator-g1-legacy-media-transition-correction-handoff.md`
- `docs/phase-02/curator-picker-hydration-hf1-handoff.md`

Surviving evidence includes:

- strict image validation and SSRF controls;
- deterministic manifest/digest and Transition Closure Gate;
- mutation lease/fence/journal;
- copy-verify-commit-cleanup compensation;
- typed unsafe-owner diagnostics and configured fallback;
- settings path/key pairing and portable import/export;
- exact-path registration lessons;
- the test miss caused by proving separate happy paths instead of the combined
  production state.

Superseded forward choices include Curator-canonical identity, root-purpose
selection coupling, and the old G1-only cutover.

## Superseded operational runbooks

### `docs/phase-02/curator-g1-image-library-production-cutover-runbook.md`

Classification: historical, unexecuted G1/LMTC evidence; do not execute for the
approved MediaAsset route.

Preserve its exact-migration, backup, maintenance, dry-run, digest, retry,
rollback, cache, and verification lessons. Replace it after implementation
with `docs/phase-02/media-asset-production-cutover-runbook.md`, containing the
actual new migration allowlist, schema profiles, converter commands, closure
digest, and separate production approval wording.

### `docs/phase-02/curator-g1-existing-svg-runbook.md`

Classification: historical, unexecuted IDs 6/7 plan; do not execute for any
future operation.

Preserve proof that real IDs 6/7 were never sanitized. The new reusable route
is `docs/phase-02/media-asset-svg-sanitation-runbook.md`, but each real SVG
action remains separately approval-gated.

## Historical images/import evidence

The following retain delivered facts and security/portability evidence but no
longer choose the canonical provider architecture:

- `docs/research/images-media/00-images-media-research.md`
- `docs/research/images-media/01-imga-curator-research.md`
- `docs/research/images-media/02-imgb-research.md`
- `docs/research/images-media/02-imgb-implementation-plan.md`
- `docs/research/images-media/03-ie1-research.md`
- `docs/research/images-media/03-ie1-implementation-plan.md`
- `docs/phase-02/images-arc-imga-handoff.md`
- `docs/phase-02/images-arc-imgb-handoff.md`

Early Spatie-versus-Curator recommendations are superseded by the approved
minimal MediaAsset kernel with Curator as its only provider.

## Spotify evidence

- `docs/research/spotify-fetcher/00-fetch1-research.md`
- `docs/research/spotify-fetcher/00-fetch1-implementation-plan.md`
- `docs/phase-02/spotify-fetcher-fetch1-handoff.md`

Classification: historical feature evidence. Retain current Spotify parsing,
OpenGraph/oEmbed, fixtures, and result UX. Image URL acquisition is now owned by
the unified MediaAsset pipeline for both podcast and episode surfaces.

## Missing tracked packages

No distinct tracked Curator G2, G3, G4, or “Curator URL imports” research files
were found in the checkout or searched Git file history. Conversation findings
from those tasks are input to the requirements registry and must be reconciled
against current source/vendor evidence here. Do not invent historical file
citations.

The amended forward mapping is:

- old G2 gallery -> `MEDIA-P2-GALLERY-REPAIR`;
- old G3 picker/acquisition -> `MEDIA-P3-ACQUISITION-PICKER` plus
  `MEDIA-P4-OWNER-IMAGE-UX`;
- old G4 moves/lifecycle -> `MEDIA-P5-FILES-LIFECYCLE`;
- URL-import ideas -> the unified acquisition pipeline in Package 3.

## Deferred external choices

- Spatie Media Library: rejected for this program.
- `mwguerra/filemanager`: optional future Files UI only, dependency audit and
  approval required; never canonical mutation authority.
- Other providers: future provider adapter work after a concrete consumer and
  amended audit.

## Update rule

At each package closeout:

1. update the context helper status and links;
2. reconcile this map only if authority changed;
3. add the package handoff as historical compatibility evidence after its hash
   stamp;
4. keep unfinished later package routes marked pending;
5. do not rewrite completed historical handoffs to claim the new architecture
   existed earlier.
