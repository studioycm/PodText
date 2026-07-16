# SP3 Filament Forms UX and Performance Audit

Date: 2026-07-16

Audit mode: static, read-only application/test/schema review

Skills used: `filament-forms-ux-audit`, `filament-performance-audit`

Installed stack: Filament 5.6.7, Livewire 4.3.3, Laravel 13.19.0

## Reconciled outcome

The static findings remain valid, but their forward disposition changed during
the operator decision session. ARCH1-A through ARCH1-S are now controlling:
Card Templates and Public Forms leave settings JSON and become normal,
versioned model/Resource aggregates before SP3D. The current custom-data
Template library, whole-library Public Forms Repeater, focused settings writer,
and SP3C canaries are migration-source evidence—not the final surface on which
SP3D should freeze budgets.

The operator also settled the Select policy: tiny finite choices use native
controls by default unless required custom/HTML rendering or behavior needs a
non-native Select; growing sources are searchable, non-preloaded, constrained,
and capped at 50. This is no longer a pending veto.

Groups 13–14 subsequently added a per-user mutable autosave working draft with
explicit immutable revision checkpoints and selected a complete Shield/Spatie
authorization migration before ARCH1. Those decisions are researched in
`09-arch1-drafts-authorization-research.md`; they supersede any assumption that
one shared draft or the current enum-role gates remain the target.

No application change was made by this audit.

## Installed-version and current-documentation validation

Laravel Boost confirmed the installed versions above. The audit was checked
against current primary documentation:

- Filament custom-data tables must implement their own search, filtering,
  sorting, and pagination: <https://filamentphp.com/docs/5.x/tables/custom-data>.
- Filament Builder is appropriate for validated JSON block documents and its
  preview interaction opens editing controls/modal state:
  <https://filamentphp.com/docs/5.x/forms/builder>.
- Livewire public state is dehydrated and untrusted; identity and authorization
  must be restored and checked server-side:
  <https://livewire.laravel.com/docs/4.x/properties> and
  <https://livewire.laravel.com/docs/4.x/security>.
- Livewire lazy loading and islands add requests and have state/scope
  constraints. Aggregate work must be measured, not only initial HTML:
  <https://livewire.laravel.com/docs/4.x/lazy> and
  <https://livewire.laravel.com/docs/4.x/islands>.

## Verified current-state findings

### High — authenticated browser closure evidence is still absent

SP3C measures component/server HTML and serialized state. It does not prove
hydrated browser DOM, teleported modal DOM, listeners, heap, real navigation,
Back behavior, or cold/warm TTFB. Some recorded component maxima are not yet
literal assertions.

Disposition: after ARCH1 acceptance, SP3D must exercise and repair all three
approved browser paths—authenticated in-app browser, serial Pest Browser, and
external Playwright/Node—and calibrate the final Resources plus surviving
settings pages. Keep deterministic and browser planes separate. Do not claim
that islands/lazy loading improved the system without measuring total requests,
bytes, and state consistency on the same fixture.

### High — Public Forms are still a whole-library settings form

`ManagePublicForms` and `PublicFormsSettingsForm` edit the complete
`public_forms` settings root. Each form contains a Builder and nested options
Repeaters; one save normalizes and writes the whole root. Submissions currently
store a form key/name snapshot and payload, but no exact form/revision foreign
key.

Disposition: ARCH1 replaces this with `PublicForm` and immutable
`PublicFormRevision` records, one-record editing, published revision pointers,
and exact revision binding from each `PublicFormSubmission`. Nested fields and
options remain validated JSON. The new preview must be non-submittable and must
not trigger OTP, mail, rate limits, or submission writes. The bounded
`public_forms.require_email_verification` policy remains a locked settings field
on a focused policy surface; only definitions/revisions move to the Resource.

### Medium — the monolithic schema remains a coupling boundary

`BuildsPublicContentSettingsSubjectSchemas` is still a 2,477-line trait with
old `Tab` wrappers, ordinary settings schemas, the obsolete whole-list Card
Templates Builder, and shared part helpers. Focused pages extract children from
these wrappers.

Disposition: ARCH1 first removes Template/Form ownership from settings. SP3D
then deletes those obsolete branches and replaces the remaining singleton
settings monolith with owner-specific providers plus only genuinely shared
small factories. This is a maintainability/ownership finding; runtime cost must
still be measured rather than asserted.

### Medium — current Template cardinality is unbounded legacy behavior

The current custom-data library correctly implements search/filtering in its
records source but projects, validates, and renders the complete collection
with pagination disabled. Its one-query reference scan is bounded in existing
tests, but the synthetic 100-row canary does not execute the full production
projector/settings/reference path.

Disposition: do not optimize this custom-data library into the final design.
ARCH1 replaces it with a normal paginated Eloquent `CardTemplateResource` and
immutable revisions. Preserve the approved 100 configured templates across
families plus registry defaults and 50 referencing Homepage Sections as
migration/cutover evidence, then run that production-shaped fixture against the
final Resource, revision resolution, references, and renderer. Pagination
beyond normal Resource behavior is not preauthorized; stop and return options
if the approved budget fails.

### Medium — focused settings pages still read/validate/save the whole group

Focused pages read a complete `PublicContentSettings` snapshot, normalize and
validate the whole configuration, overlay owned roots, and call the existing
one-save lifecycle. This preserves sibling/hidden state but is not a slice read
or authoritative changed-field write.

Disposition: keep this boundary through ARCH1 migration safety and SP3D. SP4
later adds slice reads and server-calculated change sets only for surviving
singleton settings, using fresh normalized storage and the existing cache/event
authority. Template/Form changes use ARCH1's shared after-commit coordinator.

### Decision settled — Select loading and presentation

The old audit counted broad use of non-native Select controls. Static review
found the intended growing-source pattern, but cannot prove keyboard, RTL, DOM,
or perceived UX.

Disposition: the operator-approved policy is authoritative. Re-audit surviving
settings and new Resource forms after ARCH1 ownership is known; use native
controls for tiny finite choices unless concrete custom/HTML behavior requires
otherwise. Validate affected pages in the authenticated browser evidence.

## Forms UX implications of ARCH1

- A whole-library Repeater is not the durable editor for independently growing,
  listable, referenceable, and auditable Templates/Forms.
- Each parent becomes a Resource record with one active revision editor,
  explicit draft/preview/publish/archive/history behavior, and stable identity.
- Template parts/nested children and Form fields/options remain Builder-owned
  validated JSON inside immutable revisions; they do not become row-per-node
  relational schemas merely for normalization.
- Owned preview uses the actual sanitized renderer and normalized unsaved state,
  refreshed explicitly or on change/blur rather than every keystroke.
- Hebrew content is required, English optional with explicit Hebrew fallback.
  Application settings copy belongs in dedicated Hebrew/English settings files.
- Unauthorized revision JSON never hydrates. Safe metadata/preview access, if
  allowed, is separately authorized.
- Uploads are deferred until a private attachment/security/retention model is
  explicitly approved.

## Measurement and claim boundary

- Verified: current monolith/custom-data/whole-settings shape, missing
  definition models/revisions/FKs, literal test assertions, and current package
  behavior cited above.
- Historical baseline only: SP3C component caps and the existing focused
  settings writer/library behavior.
- Not measured: authenticated browser DOM, modal teleports, listeners, heap,
  total network cost, and fixed-runner TTFB.
- Approved forward order: AUTHZ1 acceptance, ARCH1 acceptance, SP3D
  cleanup/calibration, SP4, then LOG1.
- No numeric browser cap may be invented. Use the operator-approved two-run
  calibration, deterministic max plus 10%, DOM product target `<3000`, fixed
  warm median TTFB `<800ms`, and advisory heap/listeners until repeatable.

The complete controlling decisions, inventory, risks, and SP3D wireframe are in
`07-sp3d-pre-research.md`.
