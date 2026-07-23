# Post-Package-3 Acquisition Picker UX Research

## Status and authority

- Date: 2026-07-23
- Audit: `LS-20260723-PODTEXT-MEDIA-P3-POST-ACQUISITION-UX-01`
- Approved option:
  `MEDIA-P3-POST-O3-IMMEDIATE-SOURCE-WORKSPACE`
- Approved exception: five sequential mini-tasks, 42–62 engineering hours
- Implementation baseline:
  `786f7c5f699a3d9d2c02f4c93baff02b0ddcbc1f`
- Package 3 implementation:
  `656a7c2ed1b64b3f6fd8392bff88f7cca36d2695`
- Preflight: clean `main`, 0 behind and 19 ahead of `origin/main`

This is a bounded post-Package-3 correction. It preserves the Package 3
architecture and improves its correctness, feedback and picker interaction.
It is not Package 4 owner-image UX and it is not Package 5 physical media
lifecycle work.

## Fixed product semantics

The approved option keeps these rules:

1. Gallery selection does not mutate a row or file.
2. Upload, URL and Storage acquisition becomes a permanent library item as
   soon as acquisition succeeds.
3. A successful new acquisition creates its Curator row, `MediaAsset` and
   Curator provider binding together.
4. The picker chooses the acquired Media row, but owner attachment remains
   pending until the outer owner form is saved.
5. Closing the picker or cancelling the outer owner form does not remove a
   completed acquisition.
6. Existing inventory remains visible even when it needs repair.

No staging token, acquisition request, queued picker operation, owner-save
finalizer, cleanup sweeper or new persistence state is introduced.

## Evidence reviewed

The review reconciled:

- the original Package 3 Stage 1 audit and implementation conversation;
- `docs/phase-02/media-program-p3-acquisition-picker-handoff.md`;
- the completed Package 3 research and plan;
- the superseded Package 4 audit, only for boundary evidence;
- the current picker, admission, Storage browser, external fetcher and Media
  Resource code;
- current picker, acquisition, external-image and Media Resource tests; and
- installed-version Laravel Boost and FilamentExamples results.

The superseded Package 4 audit authorizes nothing. Its owner preview, source
explanation, metadata, copy/download, Change, Remove/Use Automatic,
broken-association and stale-owner checks remain for a fresh Package 4 audit.
Only nested picker focus, dismissal, selection return and cancel behavior
belong here.

## Confirmed defects and UX gaps

### 1. Single-mode upload can create an unintended batch

`MediaPickerPanel` configures its `FileUpload` with unconditional `multiple()`.
A forged or ordinary multi-file state can therefore admit several permanent
rows while single-mode selection retains only the first ID.

Correction:

- configure `multiple($this->isMultiple)`;
- cap single mode at one file; and
- reject multi-file server state before validation or admission.

The standalone Media Resource remains a real batch uploader.

### 2. An operational batch failure hides earlier permanent success

The manager validates the whole upload batch before writing, which correctly
prevents a validation failure from creating any item. Admission then proceeds
one item at a time. If a later database or filesystem operation fails, an
earlier row remains permanent while the caller reports the whole action as
failed and does not select that row.

Correction:

- retain complete prevalidation before the first write;
- stop safely at the first operational admission failure;
- return a structured batch result containing successful permanent
  acquisitions and the unsuccessful remainder;
- report the unexpected failure internally;
- select and report earlier successes; and
- show a safe exact partial-success count.

This follows the approved immediate per-item permanence contract. Rolling back
already completed items would make the UI imply transactionality that does not
exist across independent filesystem/database admissions.

### 3. Storage errors are invisible and Storage reuse can race

Storage acquisition writes its validation error to `storageSearch`, but the
custom Blade view renders no error for that state. The register-in-place path
also performs an unlocked check followed by creation, allowing two concurrent
requests to create duplicate Media rows for one `(disk, path)`.

Correction:

- use a dedicated Storage acquisition error key;
- render and associate the message beside the Storage controls;
- clear it on a new search, refresh or success;
- acquire a Laravel atomic lock keyed by a hash of the resolved disk and path,
  with a deliberately oversized 60-second default/cap for the bounded
  read/validate/admit path;
- resolve and recheck the candidate inside the lock; and
- return a truthful result disposition: reused, registered in place or copied.

The application default database cache and existing `cache_locks` migration
support shared atomic locks. Array/file locks keep tests and local work
supported. The lock serializes only while that cache lease remains owned; it is
not relational uniqueness, and no relational uniqueness migration is approved.
Callers still wait at most three seconds before receiving a retryable
contention failure.

### 4. URL time and errors are not suited to an interactive picker

The external fetcher correctly revalidates HTTPS, public DNS, redirects,
pinned addresses and response bounds, but its 20-second HTTP timeout is applied
to each redirect request. The picker also collapses blocked URLs, missing
responses, temporary failures, deadline exhaustion and invalid image bytes
into one message.

Correction and exact guarantee:

- create one monotonic shared fetch budget;
- check the remaining budget before and after synchronous DNS, pinned request,
  redirect and response-policy stages;
- pass only the remaining allowance to the pinned transport;
- keep manual redirects and every existing SSRF check;
- add narrow rejected/unavailable exceptions with safe reason categories; and
- map categories to bilingual copy without exposing URLs, hosts, IPs, tokens,
  paths or internal exception text.

PHP's synchronous `dns_get_record()` cannot be preempted by this implementation.
DNS can therefore overrun the budget; the next checkpoint prevents transport or
another redirect. The pinned HTTP transport is hard-bounded by its remaining
allowance. Image decoding and Media admission after `fetch()` are outside that
transport budget.

No `Http::retry()`, pool, concurrency, queue, `defer()` or Livewire async action
is added. The picker still needs the new Media ID in the same interaction.
Existing queued Spotify/record downloads may retry at the job boundary, with a
fresh bounded fetch on each job attempt.

### 5. The picker does not communicate irreversible work precisely

Acquisition buttons disappear until usable, a single acquisition requires a
second “Use selected” click, and completion messages do not separate permanent
library admission from pending owner attachment. Storage always claims it
added a new item even when it reused an existing row.

Correction:

- keep source actions visible and disabled until valid;
- label single-mode actions “Add to library and choose”;
- after a successful single acquisition, dispatch its trusted ID to the host
  field and close the picker;
- retain explicit “Use selected” for Gallery and multi-selection;
- state that the item is permanent while owner attachment still requires
  Save; and
- distinguish created, reused, registered and copied results.

### 6. Busy, offline and dismissal behavior is ambiguous

An acquisition can run while Gallery buttons, filters, footer actions and the
custom Close control remain available. Escape and backdrop dismissal also
remain enabled.

Correction:

- use Livewire 4 loading state for a picker-wide progress status and disabled
  competing controls;
- use `wire:offline` for a visible notice and disabled acquisition controls;
- retain FilePond/Livewire upload progress for temporary transfer;
- disable Escape and backdrop dismissal on this irreversible-acquisition
  modal; and
- keep an explicit Close control that is disabled while a Livewire
  acquisition/upload request is active, but remains locally operable while
  idle and genuinely offline.

Filament remains the sole modal and focus owner. No second focus trap or second
Alpine instance is introduced.

Livewire 4.3.3's offline directive changes DOM state; it does not make a
server-dependent action complete offline or replay a failed request. The
explicit idle Close route therefore needs a narrow client lifecycle:

- use Filament's canonical close event so the child modal releases its focus
  trap and scroll lock;
- suppress only that modal's automatic server unmount listener while truly
  offline;
- immediately reopen the parent action modal when the picker is nested;
- restore focus to the stable picker opener; and
- on the browser's next online event, call an exposed picker method that
  unmounts only when this picker action is still the top mounted action.

The unscoped `wire:loading.attr="inert"` ancestor containing Gallery search
and Close applies to every request from `MediaPickerPanel`, including URL and
Storage work. Upload transfer separately inerts the header. Offline idle state
does not imply a pending request, so it intentionally leaves Close available.
Selection return creates a second request plane: once the child dispatches
`insert-media`, the parent owner calls the exposed field method and then
unmounts the picker action. A transient Alpine handoff flag therefore inerts
the entire child workspace and natively disables Close from before the first
parent call until the handoff completes. It does not duplicate persistent
selection state.

### 7. Selection and search semantics need accessibility repair

Gallery cards expose visual selection but not `aria-pressed` state or a clear
accessible name. The selected count is not announced. Search controls lack
explicit labels, blocked reasons are not associated with their buttons, focus
indication is weak, and hover-only card actions are unreliable on coarse
pointer devices.

Correction:

- add explicit labels to both searches;
- add `aria-pressed`, selection names and blocked-reason association;
- announce the selected count through a polite atomic live region;
- add strong `focus-visible` styling;
- expose card actions on keyboard focus and coarse-pointer devices; and
- cover Hebrew RTL and English LTR output.

## O3 source workspace

Gallery remains the large primary workspace. Only the acquisition rail changes:

- Upload, URL and Storage are separate Filament source navigation items;
- the permanence warning stays visible outside source navigation;
- active source is locked, server-owned Livewire state;
- switching sources preserves Upload and URL form state;
- preserved inactive-source state does not participate in the active source's
  validation or dehydration;
- a source failure activates and reveals that source;
- Alpine is used only for local focus presentation after an error;
- Storage is not enumerated at mount;
- first Storage activation loads candidates;
- returning to Storage preserves its current result/search state;
- explicit Refresh enumerates again; and
- unconfigured, initial-empty and search-empty Storage states are distinct.

The source navigation is an organization choice. It is not justified by an
unmeasured browser-speed claim.

## Performance claim and measurement plane

The observed problem is vertical competition inside a 20rem acquisition rail.
The only approved performance claim is narrower:

> Picker mount no longer invokes configured Storage enumeration; the first
> Storage activation invokes it once, later tab switches reuse component state,
> and explicit Refresh invokes it again.

That claim will be verified with a mocked `StorageImageCandidateBrowser`
invocation count. It measures server enumeration calls only. It is not a
browser DOM, heap, paint, network-latency or TTFB claim. No Storage inventory
cache, Livewire island or deferred server fragment is added.

## Installed framework fit

Installed source remains authoritative:

| Package | Installed | Relevant bounded use |
|---|---:|---|
| Laravel | 13.21.1 | `Cache::lock()->block()` and HTTP timeout controls |
| Filament | 5.7.3 | schema-owned nested Livewire component, display-only action schema without a form wrapper, dynamic `FileUpload::multiple()`, action disabled state, native modal dismissal policy, native tabs styling and the 5.7.2 focus-restoration correction |
| Livewire | 4.3.3 | loading targets, automatic loading state, `wire:offline`, upload progress events |
| Alpine | 3.15.12 bundled by Livewire | local focus presentation plus transient offline modal-close/reconnect coordination; no duplicated persistent state |

Official references:

- Laravel atomic locks:
  <https://laravel.com/docs/13.x/cache#atomic-locks>
- Laravel HTTP timeouts:
  <https://laravel.com/docs/13.x/http-client#timeout>
- Filament action modals:
  <https://filamentphp.com/docs/5.x/actions/modals>
- Livewire loading states:
  <https://livewire.laravel.com/docs/4.x/loading-states>
- Livewire offline state:
  <https://livewire.laravel.com/docs/4.x/wire-offline>
- Livewire uploads and progress:
  <https://livewire.laravel.com/docs/4.x/uploads>

Stage 2 began on Filament 5.7.1. The operator later explicitly amended only
the dependency exclusion and authorized a bounded Filament patch refresh
without a new audit cycle. The lockfile update moved the 12 installed Filament
family packages from 5.7.1 to 5.7.3 and the required transitive
`kirschbaum-development/eloquent-power-joins` patch from 4.3.2 to 4.3.3.
There were no installs, removals, manifest changes or reported security
advisories. Composer's Filament upgrade hook republished four changed tracked
assets.

The official release evidence is deliberately narrow:

- Filament 5.7.2 includes “Fix modal focus restoration scrolling”; its action
  modal focus restoration now uses `focus({ preventScroll: true })`:
  <https://github.com/filamentphp/filament/releases/tag/v5.7.2>.
- Filament 5.7.3 contains a nested `MorphTo` relationship path-caching fix,
  not a nested action-modal or Livewire partial fix:
  <https://github.com/filamentphp/filament/releases/tag/v5.7.3>.

The patch update improves the supported modal focus baseline. It does not make
raw nested Livewire markup inside `modalContent()` a supported action-modal
composition and did not replace the picker lifecycle correction.

## FilamentExamples findings

The required multi-query/refinement protocol used:

- `Filament action modal tabs form choose existing record product picker`;
- `Filament file upload modal action error halt notification`; and
- `Filament custom Livewire component modal sticky footer focus`;
- `nested modal nested action modal livewire component action schema`; and
- `custom table field product picker modal Livewire schema`.

The server exposed search/snippet results only; no separate source/detail
fetch tool was available.

Useful evidence:

- “Quote Form with Custom Table Field and Product Picker Modal” uses
  `Filament\Schemas\Components\Livewire::make(...)` with a unique schema key,
  not a raw `<livewire:...>` tag inside custom action-modal content. Relevant
  search-result paths were
  `v4/forms/quote-form-with-custom-table-field-and-product-picker-modal/app/Livewire/ListQuoteProducts.php`
  and
  `v4/forms/quote-form-with-custom-table-field-and-product-picker-modal/app/Livewire/ProductPickerTable.php`.
  Its forced partial render is limited to the event boundary and is not a
  license for duplicate action-modal partial roots.
- “AI-Powered CMS” demonstrates safe action halting and notification feedback
  after a failed long-running action.

Adaptation:

- let the outer Filament action remain the only modal/action-stack owner;
- host `MediaPickerPanel` through the supported schema Livewire component with
  one unique key;
- disable the outer picker's unnecessary form wrapper because it has no submit
  action; picker-owned Edit/rename/swap/delete actions may then own their action
  modal forms without invalid nested forms;
- return trusted selection through
  `callSchemaComponentMethod(...)`, await it, then unmount only the outer
  picker action;
- restore focus to the stable opener with `preventScroll`;
- keep FileUpload's Alpine subtree mounted but hidden/inert across source
  changes so its entangled `state` is not torn down during a child Livewire
  morph; the Filament component's own visibility observer supports delayed
  initialization when it becomes visible;
- dehydrate and validate only the active Upload or URL section so preserved
  invalid state in the inactive source cannot block the visible acquisition;
- do not copy persistence semantics from unrelated examples;
- do not cancel parent owner actions when a picker child action closes; and
- do not introduce another modal system.

This corrects an observed browser failure, not a theoretical preference. Raw
Livewire content produced duplicate `action-modals.*` partial ownership and
could route child requests through the parent. After schema ownership, repeated
open → source switch → close/reopen → Storage acquisition initially exposed an
intermittent Alpine `ReferenceError: state is not defined`; keeping the
FileUpload subtree mounted removed the invalid partial-teardown lifecycle.
Because CSS visibility alone does not change Filament schema validation,
active-only section dehydration plus `validatedWhenNotDehydrated(false)` keeps
that stable DOM lifecycle without allowing a preserved malformed URL to block
Upload or a preserved invalid upload to block URL.
Opening the picker's own Edit action then exposed a second concrete boundary:
Filament's default form wrapper around the display-only picker action nested the
child action form and caused Livewire to lose the visible picker owner.
`formWrapper(false)` on the outer picker action removed that invalid form
nesting; a browser regression opens and cancels the child action twice while
the picker remains mounted.

A final real-network-isolation regression exposed a third boundary: an idle
Close control could be visibly available while its `unmountAction()` request
could not reach the server. The corrected offline branch performs Filament's
full local modal close without sending that request, restores a nested parent
and focus, then reconciles the server action stack exactly once after the
browser returns online. The reconciliation method verifies the current mounted
action name and owning schema-component key before it can unmount anything.

## Expected implementation shape

Narrow additions are acceptable:

- an acquisition disposition enum;
- a single-acquisition result;
- a batch-upload result;
- an external-image failure reason enum; and
- rejected/unavailable external-image exceptions.

Existing ownership remains:

- `MediaPickerPanel`: picker state and user feedback;
- `MediaAcquisitionManager`: source acquisition and admission coordination;
- `SafeExternalImageFetcher`: URL/redirect/DNS policy, shared fetch budget and
  post-blocking checkpoints;
- `PinnedExternalImageTransport`: one pinned request using the remaining
  allowance;
- `StorageImageCandidateBrowser`: configured-source discovery and opaque
  candidate resolution; and
- `PathCuratorPicker`: host-field modal and trusted selection return.

No generic media service, migration or owner-attachment rewrite is needed. The
only dependency change is the operator-authorized bounded Filament patch
refresh described above.

## Test and safety boundaries

Tests will use:

- `RefreshDatabase`;
- test-owned fake `public` and `local` disks;
- test-only cache locks;
- committed image fixtures;
- `Http::preventStrayRequests()`; and
- mocked pinned transport/DNS where appropriate.

Required coverage includes:

- forged multi-file single-mode rejection before admission;
- complete batch prevalidation;
- later operational failure with earlier permanent successes reported;
- Media Resource partial-result compatibility;
- Storage reuse, register/copy disposition, 60-second default lease, lock
  contention and inside-lock recheck;
- decreasing transport allowance across redirects plus DNS-overrun checkpoint
  proof that prevents later transport;
- every safe URL failure category;
- lazy Storage activation and explicit refresh invocation counts;
- source-state preservation and error routing;
- active-source-only validation while inactive source state remains mounted;
- direct single-mode choose and pending-owner wording;
- visible disabled actions, loading/offline status and dismissal policy;
- true BrowserContext-offline Close for direct and nested picker actions,
  followed by guarded reconnect cleanup and a clean reopen;
- delayed parent selection return proving the picker and Close remain guarded
  after child loading ends and until the parent handoff completes;
- accessible search, selection, count, blocked reasons and touch actions;
- nested outer Add/Replace action return, close/focus restoration, cancel and
  save behavior;
- repeated picker-owned child action-modal open/close while the picker survives;
  and
- Hebrew RTL and English LTR browser output.

No local-development database, real media storage, cache, queue, live media
network acquisition, live data, production action or push is authorized.
