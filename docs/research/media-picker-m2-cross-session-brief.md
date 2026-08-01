# M2 — media picker duplicate root: cross-session research brief

Written 2026-08-01. One consolidated context for the session that fixes M2.
Collects four working sessions plus one worktree audit. Nothing in this file is
implemented; it is the starting state. The authoritative queue entry is **M2**
in `docs/phase-02/dashboard-metrics-phase-2R-handoff.md` — whoever lands the
fix updates that entry and the media-picker gotcha bullet there, plus this
file's status line.

**Mission, in the operator's words:** find how to solve this *simply*, with
what Filament and Livewire intended. The mechanism below is verified; the fix
is deliberately not chosen. Do not inherit any prior session's fix bias.

## The defect

Open an owner-image action on `/admin/content-groups/1/edit`, open the media
picker inside it, close it, reopen it. A duplicate, uninitialised picker root
appears in the DOM. From then on every Filament partial update for the host
page throws `Multiple elements found for partial [action-modals.1]` and the
picker is dead until a full page reload. Reproduced with ordinary clicks in a
real browser. Appears in ~13% of instrumented browser-test runs. **Production
defect, not test flakiness** — no wait or timeout can fix it because the broken
state persists for the page's life.

## Confirmed mechanism (source-verified at both ends)

1. The picker is a nested Livewire component (`App\Livewire\Admin\MediaPickerPanel`)
   mounted in the action modal schema with a **stable** key:
   `media-picker-workspace-{fieldKey}` set at
   `app/Filament/Forms/Components/PathCuratorPicker.php:550`, passed through
   `vendor/filament/schemas/src/Components/Livewire.php:120-122` to
   `Livewire::mount($component, $properties, $key)` as `wire:key`. Full form:
   `{hostId}.mountedActionSchema{n}.media-picker-workspace-{fieldKey}` —
   byte-identical on every reopen at the same nesting index.
2. When an action mounts/unmounts, Filament re-renders only the
   `action-modals.{index}` partial. To do that it forces the host to skip
   rendering — the sole purpose of
   `vendor/filament/support/src/Livewire/Partials/DataStoreOverride.php`.
   Livewire's `SupportNestingComponents::dehydrate()` sees `skipRender` and
   calls `keepRenderedChildren()`, so **child keys persist in the memo even for
   children not rendered this request**. Filament's own
   `PartialsComponentHook::dehydrate()` also merges `previousChildren` forward
   (it only ever adds).
3. On reopen, Livewire's pre-mount hook finds the key already registered and
   **skips the child**, emitting a snapshot-less stub —
   `vendor/livewire/livewire/src/Features/SupportNestingComponents/SupportNestingComponents.php:17`
   (check) and `:26` (stub: `<div wire:id wire:name wire:key></div>`, no
   `wire:snapshot`). This is documented, intentional Livewire behaviour.
4. Filament's `vendor/filament/support/resources/js/partials.js:70` handles
   exactly that stub: `if (child.hasAttribute('wire:snapshot')) return`, else
   `child.replaceWith(existingComponent.cloneNode(true))`. `cloneNode` copies
   attributes (`wire:id`, `wire:key`, `wire:snapshot`) but not JS properties —
   the clone has **no `__livewire`** and nothing ever initialises it.
5. `partials.js` attributes partial containers by
   `findClosestLivewireComponent(el) === message.component`. The walk passes
   straight through the uninitialised clone to the host, so the picker's own
   `action-modals` container (it renders `<x-filament-actions::modals />` at
   `resources/views/livewire/admin/media-picker-panel.blade.php:759`) is
   attributed to the host. Two matches for one partial name → throw.

Key insight from instrumentation: **four action-modal containers co-existing is
normal** while the picker is open, each resolving to a distinct component. Only
an uninitialised root breaks the attribution.

## Ruled out — do not re-litigate

- **Key collision between the two picker variants.** Action picker uses
  `media-picker-workspace-{fieldKey}`, inline uses
  `inline-media-picker-workspace-{fieldKey}` (PathCuratorPicker.php:576).
  Distinct strings. The captured broken DOM had two elements sharing ONE
  `wire:id` — a clone fingerprint, not a second mount.
- **`smart_wire_keys`** — already `true` at runtime (Livewire 4 default).
- **"Livewire in a modal is unsupported."** False; Filament supports Livewire
  schema components. Removing the nesting is not the fix direction.
- **Test timing.** The error is a persistent broken state, not a race a wait
  can absorb. (A separate, *fixed* test defect existed — see ledger.)

## Discrepancy the fixing session must resolve first

The M2 queue entry (earlier) says: a plain top-level open→close→reopen cycle is
clean; the broken state needed a **nested** mount (`launchPanel` at
`mountedActionSchema1` while `chooseContentGroupCover` was mounted at index 0).
The gotchas bullet (later, after ~100 instrumented runs) says: **two ordinary
open/close cycles of the picker reproduce it.** These agree only if the picker
is effectively always nested under the owner-image action in real usage.
Reproduce deliberately and pin down: which actions at which indices, and why
only ~13% of runs when the key is byte-identical every time. A fix experiment
without the deterministic repro proves nothing — the Pest fixtures never hit
this because they seed no media, so no nested item action exists and no
`action-modals.1` partial can collide.

## Fix directions to research (not conclusions)

1. **Per-mount key** — Livewire's documented lever: "if you want to force a
   component to re-render, you can simply change its key"; keys must be unique
   across the whole page. `HasKey::key()` accepts a Closure evaluated per
   render. The missing piece is a per-mount token: `mountedActions[]` entries
   hold only `name` / `arguments` / `context` — `arguments` is the supported
   per-mount slot, or a host-side mount counter. Cost to verify: a changed key
   destroys and recreates the picker each open (likely *desired* for a modal;
   uploads, filters, scroll reset — check).
2. **Upstream fix** — `partials.js` could *move* the live node instead of
   cloning it (moving preserves `__livewire`). Check whether Filament > 5.7.5
   already changed this; if not, this repo's captured DOM fingerprint (shared
   `wire:id` + `wire:key`, missing `__livewire`) is a strong new upstream
   report — none of the known issues describe it.
3. **What others do** — mine FilamentExamples for nested Livewire in modals
   (see assignments), and the official `filamentphp/demo` repo on GitHub for
   the Filament 5 branch.
4. **Docs** — Boost `search-docs` for Filament "Rendering a Livewire component
   in a schema" / key requirements, and Livewire "nesting" / "wire:key" /
   "islands". Check whether a per-mount key requirement is already documented.

## Upstream references already collected

Same family upstream (all "Snapshot missing on Livewire component with id",
none with our clone fingerprint):
filamentphp/filament#12816 (custom component tabs + actions), #15242
(Infolist sections + header action), #10715 (multiple Livewire components +
header action), discussion #13205 (re-render breaks modal in table cell).
Livewire background: livewire/livewire#654 (forcing nested re-render),
discussion #9090 ("the wire:key again"), discussion #4244 (multiple root
elements when reusing a component).

## Cross-session ledger

- **"Fix flaky media picker acquisition browser test"** (`local_6a24f004`) —
  wrote the M2 entry and the gotchas bullets; ~100 instrumented browser runs;
  proved the `[action-modals]` error real (~13%) and page-fatal; separated and
  fixed an unrelated *test* defect (`return_guard_released` read `disabled`,
  which has a second writer — Filament's `wire:loading.attr="disabled"` on
  every icon-button; fixed by settling Livewire and asserting the guard's own
  attribute — commit `3cc4906`); left a third unclassified 1/30 Storage-listing
  timeout.
- **"Type settings properties as their enums"** (E5 session, this brief's
  author) — verified the whole mechanism in vendor source both ends; collected
  the upstream issue list; found that `->key()` on a schema Section re-keys its
  **children** (`HasKey` inheritance — after keying, component-key assertions
  become `workspaceTranscriptionSection.<field>`), a direct caution for any key
  fix; found a **second suspect flake in the same domain**:
  `OwnerImageWorkspaceTest` "it proves canonical dedicated owner actions across
  locale and device contracts" fails ~1-in-4 standalone — check whether it is
  the same clone mechanism; recorded the M1 decisions (below). E5 commit
  `9859145`.
- **"Fix Asia/Jerusalem hardcoded in admin helper text"** (`local_aa1f7d69`) —
  `86f7e85`; re-verified the typed `isMultiMode()` path (13/13); corrected its
  own commit message's "known flake" framing after M2 was reclassified.
- **"PodText defensive hardening research"** (`local_51579218`) — secondary
  hypothesis: browser tests drive an in-process server, parallel workers can
  collide on ports and storage beyond SQLite `:memory:` isolation. Superseded
  as the cause of the `[action-modals]` error, possibly relevant to the other
  intermittents (the 1/30 timeout, the owner-actions flake).
- **"Audit Filament explicit keys across admin pages"** (`local_7c2d4843`,
  worktree `.claude/worktrees/upbeat-ramanujan-61c4d4`, branch
  `claude/upbeat-ramanujan-61c4d4`) — unfinished and uncommitted:
  `CardTemplateSettings` / `ImporterSettings` edits plus a new
  `tests/Feature/AdminTableQueryStringKeysTest.php`. Its scope is page/table
  **query-string** key collisions (cousin of the already-fixed
  `queryStringIdentifier` item), not component `wire:key`s. Coordinate before
  touching the same files.

## Adjacent decisions already taken (do not reopen)

- **M1 (Storage panel):** hide already-registered files; real pagination, not a
  raised cap or a "showing 50 of N" label. Separately, the operator floated
  moving the Storage source out of the picker into a dedicated
  storage-importer page — that would ease M1 and shrink the panel, but it does
  **not** fix M2 (the picker still nests in the modal); do not sell it as an
  M2 fix.
- `disabled` on picker controls is a shared channel (Alpine guard + Filament's
  `wire:loading.attr`). The guard's own observable is `aria-disabled` — Filament
  only writes that from its static `$disabled` prop, which we never pass.

## Constraints

- Shared tree `/Users/studioycm/Herd/PodText`, local `main`, **13 commits ahead
  of `origin/main` (`7bba038`)**. Auto-deploy is ON: **never push**. Two
  settings migrations sit in the unpushed stack.
- Gate: `php -d memory_limit=2G vendor/bin/pest --compact`,
  `vendor/bin/pint --dirty`, full `vendor/bin/filacheck` (never `--fix` without
  explicit operator approval), `npm run build`.
- Stack: Filament 5.7.5 / Livewire 4.3.4 / Laravel 13.23.0 — pins deliberate.
- Record FilamentExamples research in
  `docs/research/filament-examples-phase-02.md` per the tooling protocol.
- On landing: update the M2 entry, the gotchas bullet, and this file's status.

**Status: open — research brief only, no fix implemented.**
