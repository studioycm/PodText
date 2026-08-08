<!-- Produced 2026-08-08 by a 7-agent workflow (6 classifiers over every Gate:: site + 1 synthesizer),
     operator decision D6. Inventory verified against the tree the same day; line numbers drift — re-grep
     before trusting any citation. Companion docs: roles-permissions-notes.md (course validation of the
     staged position), 21-authz-subsystem-dormancy-record.md (the dormant spatie layer). -->

# PodText Authorization Gate Surface Map (F9)

**Question:** ~113 `Gate::` facade call sites, zero `$this->authorize()`, zero `$user->can()` in app code. Is the architecture coherent?

**Short answer:** Yes, deliberately so. The app has replaced Laravel's controller/model authorization sugar with three explicit layers: (a) a panel-access role floor as the outer wall, (b) four `Gate::policy` bridges consumed implicitly by Filament's resource authorization, and (c) direct `Gate::forUser($actor)->authorize()` calls at every mutation boundary in the media service mesh. `$this->authorize()` is the `AuthorizesRequests` controller trait — the app's ~9 controllers are almost all Filament-internal or file-streaming, and the one that authorizes (`AdminMediaFileController`) uses the Gate facade directly. `$user->can()` is sugar for exactly what `Gate::forUser($user)` does; the codebase standardized on the facade form. Verified 2026-08-08: 113 `Gate::` sites across 29 files; zero `$this->authorize(`, zero `->can(`, zero `@can` in `app/` and `resources/views`.

---

## 1. Ability catalogue

### 1a. Named gates (`Gate::define`) — exactly three

| Gate | Defined at | Predicate | Consumers |
|---|---|---|---|
| `super-admin` (= `UserRole::SuperAdmin->value`) | `app/Providers/AppServiceProvider.php:205` | `$user->hasRoleAtLeast(UserRole::SuperAdmin)` | `superAdminOnly()` macros (AppServiceProvider:221, 231); `UserResource.php:50,55,60`; **called back from inside `CuratorMediaPolicy`** at `app/Policies/CuratorMediaPolicy.php:127,153` |
| `multi-transcription` | `app/Providers/AppServiceProvider.php:206` | `MultiTranscriptionSurfaces::userCan($user, $minimumRole)` — role floor **AND** global multi-mode flag; optional minimum-role argument, defaults SuperAdmin | `multiTranscription()` macros only (AppServiceProvider:216, 226), used at `ContentItemForm.php:183`, `TranscriptionsRelationManager.php:196`, `BuildsPublicContentSettingsSubjectSchemas.php:341–359` |
| `viewHorizon` | `app/Providers/HorizonServiceProvider.php:33` | nullable-user FilamentUser `canAccessPanel('admin')` | Horizon vendor dashboard middleware only |

There is **no `Gate::before`** — no super-admin bypass at the gate layer. `super-admin` is an ordinary ability, and that absence is load-bearing (see §5).

### 1b. Policy bridges (`Gate::policy`) — four, two of them vendor models

All at `app/Providers/AppServiceProvider.php:201–204`:

| Model | Policy | Ability vocabulary |
|---|---|---|
| `Media` (vendor: Awcodes Curator) | `CuratorMediaPolicy` | CRUD: `viewAny view create update delete deleteAny` + domain verbs: `bulkUpload download relocate trust makePublic correctDisk mintReferenceKey detachAndDelete rename repair swap select attach detach` (25 methods; verified against `app/Policies/CuratorMediaPolicy.php:17–296`) |
| `SettingsBackupVersion` | `SettingsBackupPolicy` | `viewAny/view` → Admin; `create/update` → hard `false`; `delete/deleteAny` → SuperAdmin |
| `Filament\Actions\Imports\Models\Import` (vendor) | `ImportPolicy` | `viewAny` → Admin; `view` → Admin **or owner** (`$import->user()->is($user)`, the failed-row CSV download gate); `create/update/delete` → hard `false` |
| `ContentItem` | `ContentItemPolicy` | `viewAny/view/create/update` → Admin; `delete/deleteAny` → SuperAdmin |

### 1c. The role substrate

`App\Enums\UserRole` (string enum, `users.role` column, cast at `User.php:37`): SuperAdmin(500) > Admin(400) > Moderator(300) > Transcriber(200) > User(100). Every policy floor is `User::hasRoleAtLeast()` (`User.php:41`), which defaults a null role to `User`. Panel access itself — the outer wall — is `User::canAccessPanel()` (`User.php:46`): admin panel + `hasRoleAtLeast(Admin)`. Spatie permission tables exist but are empty and dormant; nothing in the gate surface reads them.

### 1d. Domain grouping of checks

- **Media curation/mutation: ~95 of 113 sites.** The overwhelming majority of the gate surface is a single-domain authorization mesh over `CuratorMediaPolicy`.
- **Content publication: 3 sites** (`ContentItemsTable.php:319,332`, `UndoesPublicationToggle.php:40`, all `update` on ContentItem) plus one Filament string-form `->authorize('update')` at `ContentItemsTable.php:430`.
- **Role-gated admin UI: 7 sites** (UserResource ×3, the four macro checks in AppServiceProvider).
- **Vendor dashboards: 1** (`viewHorizon`).
- **SettingsBackup and Import: 0 direct `Gate::` sites** — those two bridges are consumed exclusively through Filament's resource authorization layer and the vendor failed-row download check (see §3).

---

## 2. Where enforcement happens — surfaces ranked by call count

| Rank | Surface | Sites | Character |
|---|---|---|---|
| 1 | `app/Support/Media/MediaFilesystemMutationCoordinator.php` | 14 | All `authorize`-throw; entry guard + in-transaction re-check on `lockForUpdate` row for every filesystem mutation (TOCTOU defense at :147/:261, :329/:360, :437/:485, :729/:838) |
| 2 | `app/Filament/Forms/Components/PathCuratorPicker.php` | 11 | Throwing checks at every field-state boundary: browser event (:250/:251), hydration (:744/:800), reactive update (:934/:935), dehydration (:824), per-item actions (:659), detach (:958/:964) |
| 3 | `app/Providers/AppServiceProvider.php` | 10 | Definitions (4 bridges + 2 gates) + 4 macro-embedded `Gate::denies` visibility checks |
| 3 | `app/Filament/Resources/Media/Tables/MediaTable.php` | 10 | UI-tier `Gate::allows` in `->authorize()/->disabled()` closures + one execution-time throw on a refetched record (:349) |
| 3 | `app/Filament/Resources/Media/Pages/ReviewMediaIssues.php` | 10 | One throwing `Gate::forUser(...)->authorize` per repair action closure (:186–:522) |
| 6 | `app/Support/Media/OwnerImagePresenter.php` | 9 | Ambient soft checks shaping projections (URLs, choices) |
| 7 | `app/Support/Media/MediaIssueReviewPresenter.php` | 8 | Soft `allows`/`inspect`; **`Response::deny()` messages surfaced verbatim as UI "blocked" reasons** (:133–:215) |
| 8 | `app/Livewire/Admin/MediaPickerPanel.php` | 7 | `trustedRecord`/`trustedRecords` choke points (:1134, :1200) authorizing every browser-supplied id; mount/reload `viewAny` (:149, :953) |
| 9 | `app/Support/Media/MediaAttachmentManager.php` | 4 | In-transaction attach/detach authorization on locked rows |
| 10+ | 20 more files | 1–3 each | Services (`MediaRecordCorrections`, `MediaMissingFileResolver`, `MediaAcquisitionManager`, `CuratorMediaAdmission`, `MediaMutationFence`, `MediaBulkDeleteCensus`, `MediaOwnerTitleApplier`, `MediaRelocationBatch`, projectors), queued jobs (`DownloadExternal*Image.php:71` — rehydrate user by id, re-authorize in `handle()`), one HTTP controller (`AdminMediaFileController.php:36`), UserResource, ContentItemsTable, HorizonServiceProvider, CreateMedia, ListMedia, and `CuratorMediaPolicy` itself (calls out to the `super-admin` gate) |

**The tier pattern (consistent across all surfaces):**
1. **Presenters/projections** — ambient `Gate::allows`/`Gate::inspect`, deny degrades the payload (null URL, hidden button, skipped row), never throws.
2. **Filament UI closures** — boolean `Gate::allows` inside `->authorize()`/`->visible()`/`->disabled()`.
3. **Livewire execution closures** — throwing `Gate::forUser($actor)->authorize()`, frequently on a re-fetched record rather than the hydrated one (`MediaTable.php:349`).
4. **Service layer** — throwing checks at entry against scope-trusted re-fetches, **re-proven inside the commit transaction on the locked row**, plus a third dynamic re-assertion in `MediaMutationFence::begin()` (`MediaMutationFence.php:39`).

A UI bypass therefore still hits a hard gate; a service-layer TOCTOU still hits the locked-row re-check.

---

## 3. Why `$this->authorize()` and `$user->can()` are absent

Three replacements, each a considered idiom rather than an omission:

**(1) Filament resource authorization replaces controller `$this->authorize()`.** There are no CRUD controllers to put the trait call in — admin CRUD is Filament resources, and Filament v5 auto-consults registered policies for `viewAny/create/update/view/delete/…` (verified in `vendor/filament/filament/src/Resources/Resource/Concerns/HasAuthorization.php`: delegates to `get_authorization_response(...)` with policy-existence checking on). The `SettingsBackupPolicy` and `ImportPolicy` bridges have **zero direct `Gate::` call sites in app code** — they are enforced entirely through this implicit Filament layer (plus Filament's own failed-row download check hitting `ImportPolicy::view`, the owner-or-admin rule at `ImportPolicy.php:29`). This is why grep-for-`Gate::` undercounts the real enforcement surface: the four `Gate::policy` lines at `AppServiceProvider.php:201–204` fan out into every resource page, table action, and relation manager for those models without any visible call site.

**(2) `Gate::forUser($actor)` replaces `$user->can()`.** They are semantically identical, but the codebase standardized on the facade form and threads an **explicit `User $actor` through service signatures** — 19 of 20 sites in the service mesh use `forUser`, and the picker pins the actor with `abort_unless($actor instanceof User, 403)` first. The reason is structural: much of this code runs where ambient `auth()` is unreliable or wrong — queued jobs (`DownloadExternalContentGroupImage.php:71` re-authorizes the *enqueueing* user rehydrated by id, not the absent ambient user), batch services, and Livewire methods reachable via browser-forgeable payloads. Also, `forUser()->authorize()` throws with the policy's `Response` intact, and `Gate::inspect()` exposes deny messages — `$user->can()` returns a bare bool, which the presenter tier specifically cannot use (deny messages are UI copy, §2 tier 1).

**(3) Macros replace scattered role checks.** The `->superAdminOnly()` / `->multiTranscription()` macros (`AppServiceProvider.php:214–233`) are declarative authorization affordances; the actual `Gate::denies` calls live in the macro bodies, invisible to grep at the 8 usage sites.

**Is it consistent?** Substantially. Deviations are minor and mostly documented in-code: `PathCuratorPicker.php:398` is the single ambient `Gate::authorize` amid a forUser-everywhere file; `ContentItemsTable.php:430` and three `MediaTable` actions (:377, :406, :445) use Filament's string-form `->authorize('ability')` instead of a Gate closure — same policy bridge, different syntax, but it means those actions' *execution* enforcement lives in the delegated services, not the closure. One genuine incoherence: the naming split between kebab-case app gates (`super-admin`, `multi-transcription`) and Horizon's vendor-convention `viewHorizon`, plus the fact that the `super-admin` gate name is not a literal — renaming the enum value silently renames the gate **and** breaks `CuratorMediaPolicy:127,153`, which look the gate up by `UserRole::SuperAdmin->value`.

---

## 4. Holes and asymmetries (cross-referenced)

**Defined but never checked / checked but never defined: none.** All three named gates have consumers (§1a); every ability string checked anywhere resolves to an existing policy method (all 25 `CuratorMediaPolicy` methods verified present). No orphans in either direction.

Real findings, ordered by weight:

1. **Policy-less resources are open to every panel user.** Only 4 of ~13 Filament resources have policies. `ContentGroups`, `Transcriptions`, `Categories`, `ContentTags`, `Authors`, `HomepageSections`, `PublicFormSubmissions` have none, and Filament's policy-existence check **allows** when no policy is registered. The outer wall (`canAccessPanel` → Admin floor, `User.php:46–49`) is the *only* gate on those models — a Moderator or Transcriber can't get in at all, but every Admin can do everything to them, including deletes. Coherent as a staged-authz position (matches the recorded "transitional dual authz" finding), but it means the asymmetry between `ContentItemPolicy::delete` (SuperAdmin, `ContentItemPolicy.php:41–47`) and *no* delete floor on ContentGroup/Transcription is arbitrary: an Admin cannot delete an episode but can delete the podcast that owns it.

2. **`ListMedia::continueRootRelocation()` (line 68) has no gate of its own.** It's a public Livewire method relying on `$this->relocationRun` state having been set by `deleteAny`-authorized `startRootRelocation()` (`ListMedia.php:57`). Mitigated downstream (each chunk re-checks `relocate` per row via `MediaRelocationBatch.php:44/50` and the coordinator re-authorizes at :147/:261), but the page-level method itself trusts component state.

3. **`MediaAttachmentManager.php:210` skips the per-record `detach` check when the media row cannot be resolved** through the inventory scope — the attachment row is deleted with only the class-level `viewAny` floor from :175. Deliberate (dangling attachment cleanup) but it means `detach` authorization is conditional on scope resolution.

4. **Bulk-action UI gates are weaker than the mutation they front.** `titleByOwnerSelected` gates on `viewAny` only (`MediaTable.php:603`) while performing per-record writes; per-record `update` enforcement is delegated to `MediaOwnerTitleApplier.php:87/94`, which *skips* rather than throws. Present and covered, but the enforcement is silent-degrade, not deny-visible.

5. **Silent no-op denials on forgeable paths.** `UndoesPublicationToggle.php:40` re-checks `update` on a browser-forgeable Livewire event and silently no-ops on denial (its docblock declares this redundancy deliberate divergence insurance). Same silent-skip pattern in `MediaOwnerTitleApplier.php:32`. Safe, but invisible to the denied user and to logs.

6. **Fragile-by-construction couplings** (not holes, but tripwires): the enum-value-as-gate-name (§3); `CuratorMediaPolicy` calling back out to a provider-defined gate (`CuratorMediaPolicy.php:127,153`) — the only policy→gate dependency in the app; class-level checks passing `config('curator.model', Media::class)` (`CuratorMediaAdmission.php:28` et al.) — a `curator.model` override would detach those checks from `CuratorMediaPolicy`; and `viewHorizon` resolving the admin panel non-strictly, so a panel rename silently denies everyone (fail-closed, at least).

7. **Inventory line drift:** the reader inventory cites `AppServiceProvider.php:202–207` for the bridge/define block; current file has it at **201–206**. Cosmetic, but future readers should re-grep rather than trust cached line numbers.

---

## 5. What a future Shield activation must preserve

Shield (spatie/laravel-permission + generated policies) replaces exactly the two layers this app hand-built. The migration constraints, in order of danger:

1. **No `Gate::before` super-admin bypass — this is the big one.** Shield's default `super_admin` wiring registers a `Gate::before` that returns `true` for the role, bypassing *all* policy bodies. In this app, policy denials are **invariant guards, not permission checks**: `CuratorMediaPolicy::delete` blocks deletion of media with live references (`CuratorMediaPolicy.php:42–61`), `relocate` blocks already-managed rows and duplicate identities (:73–:88), `trust` blocks non-applicable records (:94–:113), `mintReferenceKey` protects key immutability. A before-hook bypass would let super-admins delete referenced media and violate journal invariants. If Shield is activated, its super-admin behavior must be the intersection-with-policy mode (or the before-hook disabled), never blanket-allow.

2. **`Response::deny()` messages are user-facing copy.** `Gate::inspect` denial messages render verbatim in the issue-review panel (`MediaIssueReviewPresenter.php:133–215`), bulk-delete census, and picker tooltips (`MediaPickerPanel.php:1069`). Shield-generated policies returning bare `false` instead of `Response::deny(__('…'))` would blank out load-bearing UI.

3. **The gate names are API.** `super-admin` (= `UserRole::SuperAdmin->value`), `multi-transcription`, and `viewHorizon` are consumed by macros, UserResource, Horizon, and — critically — from *inside* `CuratorMediaPolicy` (:127, :153). Shield permission names must not collide with these, and the `super-admin` gate must keep answering after roles move to spatie tables (note the name collides exactly with the conventional spatie role string — decide whether that becomes the same thing or must be renamed *together with* its three consumer sites and the enum value).

4. **The four `Gate::policy` bridges cover two vendor models** (Curator `Media`, Filament `Import`) that Shield's resource-driven policy generation will not know about. `shield:generate` output must not unregister or shadow `CuratorMediaPolicy` (25 methods, 19 beyond CRUD — a regenerated CRUD-only policy silently drops `select/attach/detach/trust/…`, and Laravel's missing-policy-method behavior would deny them all) or `ImportPolicy` (whose `view` owner-check is the failed-row download authorization required by the import/export spec).

5. **Role-floor semantics (`hasRoleAtLeast`, ranked) are not flat permission sets.** Every policy floor and `canAccessPanel` (`User.php:46`) uses ordered comparison. A Shield mapping must reproduce the ordering (each rank's permission set ⊇ the rank below) or every `hasRoleAtLeast` call site must migrate simultaneously — including `MultiTranscriptionSurfaces::userCan`, which additionally composes the global multi-mode flag that no permission table can express.

6. **The `forUser($actor)` job/service pattern must keep working** — spatie permission checks must resolve for an explicitly passed user outside request context (queued jobs at `DownloadExternal*Image.php:71`, batch census at `MediaRelocationBatch.php:44`), including spatie's permission-cache behavior under queue workers.

7. **Policy-less resources are currently wide-open-to-Admins by design.** Shield generates policies for *all* resources; that flips the default for `ContentGroups`, `Transcriptions`, etc. from "any panel Admin" to "whatever permissions got seeded" — a behavior change to stage deliberately, with the §4.1 delete-floor asymmetry as the natural first thing to rationalize.

8. **UserResource's hard-coded `false` for create/delete/deleteAny** (`UserResource.php:63–75`) and `SettingsBackupPolicy`/`ImportPolicy`'s hard `false` methods are structural prohibitions, not missing permissions — Shield must not "grant" them back.

---

*Sources: six-reader classified inventory (113 sites, 29 files — count re-verified against tree at HEAD 45a59e1); ground truth re-read from `app/Providers/AppServiceProvider.php:201–233`, `app/Providers/HorizonServiceProvider.php:33`, `app/Enums/UserRole.php`, `app/Models/User.php:41–50`, all four files in `app/Policies/`, `vendor/filament/filament/src/Resources/Resource/Concerns/HasAuthorization.php`.*