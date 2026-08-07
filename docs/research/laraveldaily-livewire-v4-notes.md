# LaravelDaily: Livewire v3 → v4 — course notes

Notes on [Livewire v3 to v4: Changes You Need to Know](https://laraveldaily.com/course/livewire-v4)
(7 video lessons, 31 min, released **Jan 2026**), read in full on 2026-08-07 on the
operator's premium account.

**Every API claim below was re-verified against the `livewire/livewire` v4.3.4 actually
installed in this repo** (released 2026-07-31), by reading vendor source and the compiled
`dist/livewire.js`. Where the course and the installed version agree I say so; where the
course is silent about something that exists, I say that too. Measured facts are marked as
such; two inferences are labelled as inferences.

---

## 0. Staleness verdict: **passes**, with a six-month gap that costs nothing

The course is Jan 2026, describing Livewire 4.0. This repo runs 4.3.4, six months and three
minor releases later. Applying the sniff test from `larastan-playbook.md`:

| marker | course claim | measured on 4.3.4 | verdict |
| --- | --- | --- | --- |
| install command | `composer require livewire/livewire` | unchanged | ok |
| config publish | `php artisan livewire:config` | `ConfigCommand.php` present | ok |
| component types | `sfc`, `mfc`, `class` | `config/livewire.php:72` — `'type' => 'sfc', // Options: 'sfc', 'mfc', 'class'` | ok |
| convert command | `livewire:convert`, cannot target `class` | `ConvertCommand.php` accepts only `--sfc` / `--mfc`; with neither, converts to the opposite of what it finds | ok |
| island directive | `@island` / `@endisland` | `Blade::directive('island')` / `('endisland')` registered | ok |
| `wire:sort` sub-attrs | handle / ignore | `wire:sort:item`, `wire:sort:handle`, `wire:sort:ignore`, `wire:sort:group`, `wire:sort:config` all present | ok |
| `data-loading` | replaces `wire:loading`, needs Tailwind 4 for the `in-*` variants | Livewire sets `el.setAttribute("data-loading", "true")`; `wire:loading` still implemented | ok |
| `wire:transition` | uses browser View Transitions | `document.startViewTransition` | ok |

Nothing in the course is deprecated at 4.3.4. **No package renames, no removed APIs, no
version claims that have since been overtaken.** This is the opposite of the Larastan course
result recorded in `larastan-playbook.md` §6.

What the six months *did* add is surface the course does not cover — listed in §3 and §9.
That is a coverage gap, not rot.

---

## 1. Method note: video courses are now readable

The previous research round recorded "no YouTube transcripts" as a hard limit, which is why
video courses were deprioritised. **That limit is gone for LaravelDaily.** Its videos are
Vimeo-hosted and carry auto-generated English caption tracks.

The extraction path, which works and is worth reusing:

1. From a lesson page, same-origin `fetch()` each lesson URL and regex out
   `player.vimeo.com/video/(\d+)\?h=([a-z0-9]+)`. One JS call gets every lesson's video id.
2. Navigate the browser to `https://player.vimeo.com/video/<id>?h=<hash>` and read
   `window.playerConfig.request.text_tracks[0].url`.
3. `curl` that signed URL from the shell. Strip `WEBVTT`, cue numbers, timestamps, and
   consecutive duplicate lines.

Two constraints, both measured:

- **The Vimeo config endpoint must be reached by navigation, not fetch.** `curl` against
  `player.vimeo.com/video/<id>/config` returns HTTP 403, and same-origin `fetch()` of it
  from within the player page returns an HTML error document — it is gated on the embed
  referer. Only a real navigation populates `window.playerConfig`.
- **The caption URL must be fetched by `curl`, not from the page.** `captions.vimeo.com`
  sends no CORS headers, so in-page `fetch()` fails; the URL is signed and time-limited
  (`?expires=…&sig=…`, roughly 24h) and plain `curl` retrieves it fine.

Caption quality is adequate but the ASR mangles identifiers consistently: *LVO R / live R /
LIR* = Livewire, *wire pole* = `wire:poll`, *sentiment element* = sentinel element, *bullying*
= boolean, *Alpine GS* = Alpine.js, *fire folks* = Firefox, *Larval* = Laravel. **Never take an
API name from a caption** — every identifier in this document was confirmed against vendor
source instead. That verification step is not optional for auto-captioned material.

---

## 2. Component formats — the actual v3→v4 change, and the one thing to decide here

v4 keeps class-based components but no longer defaults to them. Three formats:

| format | `make:livewire` output | where |
| --- | --- | --- |
| **SFC** (default) | one file, inline class + Blade | `resources/views/components/⚡name.blade.php` |
| **MFC** | a directory with `name.blade.php` + `name.php` | `resources/views/components/name/` |
| **class** (v3 layout) | `app/Livewire/Name.php` + `resources/views/livewire/name.blade.php` | as before |

Measured details the course states correctly: the ⚡ prefix is cosmetic and defeats nothing
(search still finds the file); `emoji => false` in config removes it; `--class` / `--sfc` /
`--mfc` flags override the config per-invocation; `livewire:convert` moves a component
between SFC and MFC but **cannot** produce a class-based one.

Measured detail the course does not give: SFC and MFC files are written to the **first entry
of `component_locations`**, which defaults to `resource_path('views/components')`
(`config/livewire.php:16-19`).

### PodText gotcha — concrete and current

Measured on this repo, 2026-08-07:

- `config/livewire.php` is **not published**.
- Therefore `config('livewire.make_command')` resolves to the package default —
  verified via tinker: `{"type":"sfc","emoji":true,…}`.
- All 14 existing components in `app/Livewire/` are class-based.
- `resources/views/components/public/` holds 19+ ordinary Blade components.

So the next `php artisan make:livewire Foo` in this repo will silently produce
`resources/views/components/⚡foo.blade.php` — a format matching none of the 14 existing
components, dropped into the tree that currently holds ordinary Blade components. Nothing
warns about this.

**Proposal (operator decides, no code changed):** publish the config and pin the format.

```bash
php artisan livewire:config
```

then set in `config/livewire.php`:

```php
'make_command' => [
    'type' => 'class',   // match app/Livewire/*, keep Livewire and Blade components separate
    'emoji' => false,
    ...
],
```

Choosing `class` is the low-risk option and needs no migration — it just makes the existing
convention explicit. Adopting SFC is defensible too, but it is a real convention change
across 14 components and belongs in its own decision, not in a `make:` side effect. Either
way the current state — *unpublished config silently disagreeing with every component in the
repo* — is the one option with no upside.

---

## 3. Islands — the headline feature, and what the course gets structurally right

An island is a region of a component's Blade that re-renders independently, so a partial
update does not pay for the whole component's render (and its queries).

```blade
@island(name: 'latest-tickets', defer: true)
    @placeholder
        <div class="animate-pulse">…</div>
    @endplaceholder

    @foreach ($tickets as $ticket) … @endforeach
@endisland
```

Refresh it from outside with `<button wire:click="refresh" wire:island="latest-tickets">`.

### Verified options

The full signature, from `HandlesIslands::renderIslandDirective()`
(`vendor/livewire/livewire/src/Features/SupportIslands/HandlesIslands.php:49`):

```php
renderIslandDirective($name = null, $token = null, $lazy = false, $defer = false,
                      $always = false, $skip = false, $with = [])
```

The course covers `name`, `lazy`, `defer` and `@placeholder`. It never mentions **`always`,
`skip`, or `with`** — three real parameters. `with` matters most; see below.

### Mechanism the course does not explain (and which makes both its caveats predictable)

Islands are **extracted at compile time into separate view files**. `IslandCompiler` pulls
each `@island … @endisland` body out, compiles it as its own view, and even has to
deliberately carry the parent view's `use` imports across (`IslandCompiler.php:21-23`,
comment: *"Islands are extracted into separately compiled views"*). Each extracted island
gets a token derived from a hash of the source path plus its **positional occurrence index** —
`$token = $hash . '-' . $occurrence;` (`IslandCompiler.php:92`).

That single fact explains both limitations the course reports empirically:

1. **"Variables from outside the island are undefined inside."** Correct — the island body is
   a different view, so the enclosing scope is simply not there. **But the course stops at the
   symptom and omits the built-in fix.** `generateScopeProviderCode()` compiles the directive's
   `with:` array into an `extract(…, EXTR_OVERWRITE)` at the top of the island view. So:

   ```blade
   @island(name: 'panel', with: ['agentId' => $agentId])
   ```

   is the supported way to pass outer data in. Worth knowing before anyone works around the
   limitation by promoting a local into a public component property.

2. **"You cannot put an island inside a `@foreach`."** Also correct. *Inference, not measured:*
   the reason is that the token is compile-time and positional — one per source location — so
   every loop iteration would share one `name|token` pair, and `islandAlreadyRendered()`
   dedupes on exactly `"name={$name}|token={$token}"` (`HandlesIslands.php:143-152`). The
   likely failure mode is therefore a **silent** drop of all iterations after the first, not
   an exception. I did not build a loop case to confirm the observable behaviour; the course
   likewise only says "that would not work". The supported shape is the inverse — one island
   containing the `@foreach` — which is what the course demonstrates.

`wire:poll` works inside an island and polls only that island. Measured in the payload shape
the course shows: the response carries `islandFragments`, and
`SupportIslands::addEffect('islandFragments', …)` confirms it is a discrete effect rather
than a full component render.

### Relevance here

Genuine, and unusually well-matched to this codebase. Public pages are `ContentItem`
listings gated on published group + published item + effective/main published transcription —
the exact "one heavy query blocks first paint" shape islands target. The transcript viewer is
the strongest candidate: item-page identity and media can paint immediately while the
transcript body defers.

This is a design question, not a mechanical change: it interacts with the public visibility
query contract in `.ai/public-panel` and `.ai/search-filters`, and `defer`/`lazy` change when
those constraints are evaluated relative to first paint. **Recorded as a candidate, not a
recommendation** — it needs its own spec pass before anyone edits
`app/Livewire/Public/ContentItemTranscriptViewer.php`.

---

## 4. Lazy / defer / bundle — course correct, mechanism worth writing down

Measured in `SupportLazyLoading.php`:

| syntax | effect | measured mechanism |
| --- | --- | --- |
| `:lazy="true"` | loads when scrolled into view | island path compiles to `wire:intersect.once` |
| `:defer="true"` | loads immediately after first paint | compiles to `wire:init` |
| `:lazy.bundle` / `:defer.bundle` | several components load in **one** request | sets `$isolate = false` (`:63-64`) |
| `#[Lazy(isolate:, bundle:)]`, `#[Defer(…)]` | same, as PHP attributes | `BaseLazy.php`, `BaseDefer.php` |

The course's claim that "defer is the old `lazy(onLoad)` renamed" is exactly right and
verifiable: `if ($params['lazy'] === 'on-load') $isDeferred = true;` (`:58`).

Placeholder resolution order, measured in `getPlaceholderView()` (`:180-196`) together with
`Wrapped::withFallback()` (`src/Wrapped.php:11-20`) — and **inverted from the reading the
course's phrasing suggests**:

1. the component's **`placeholder()` method wins outright** if it is defined. `withFallback()`
   only supplies a value when `! method_exists($this->target, 'placeholder')`. Then, as
   fallback:
2. the compiled `@placeholder` block (SFC/MFC only — `view()->exists("livewire-compiled::{$name}_placeholder")`), then
3. `config('livewire.component_placeholder')` — a **global** default placeholder view, which
   the course never mentions and which is the cleanest answer for a consistent app-wide
   skeleton, then
4. `<div></div>`.

The course says placeholders "are available for single or multi file components, not
class-based components — for that you may use the `placeholder` method". True as far as it
goes, but it leaves the impression that the method is the *lesser* route. It is the opposite:
a `placeholder()` method silently overrides an `@placeholder` block on the same component.

Also uncovered by the course: `Route::macro('lazy')` (`:34`) makes a full-page component lazy
per-route.

---

## 5. `wire:intersect` — it is Alpine's `x-intersect`

Measured in `dist/livewire.js`:

```js
// js/features/supportWireIntersect.js
if (el.attributes[i].name.startsWith("wire:intersect")) {
    let modifierString = name.split("wire:intersect")[1]
    Alpine.bind(el, { ["x-intersect" + modifierString](e) { … } })
}
```

The modifier string is passed through **verbatim**. So `wire:intersect` is a thin wrapper
that routes an Alpine `x-intersect` hit into a Livewire action, and every `x-intersect`
modifier works unchanged: `.enter`, `.leave`, `.once`, `.half`, `.full`, `.threshold.NN`,
`.margin.NNpx`. The course lists these correctly. Alpine's intersect plugin is bundled into
Livewire's own dist, so nothing extra is installed.

Practical consequence the course does not draw: **the authority for `wire:intersect`
semantics is the Alpine `x-intersect` docs**, not the Livewire docs, and any future Alpine
change to those modifiers lands here directly.

The course's own hedge on the margin modifier ("I'm not sure how it would work with
responsive design, so I wouldn't trust it 100%") is honest and correct to keep.

The load-more/infinite-scroll pattern it demonstrates is generic and applies to any paginated
list. Not currently used anywhere in this repo (0 occurrences of `wire:intersect`).

---

## 6. `data-loading` — correct, and mostly **not applicable here**

Measured mechanism (from `dist/livewire.js`), which is more specific than the course states:
during a message, Livewire sets the attribute on the **origin element of the action**, then
removes it on finish:

```js
let el = origin.hasOwnProperty("targetEl") ? origin.targetEl : origin.el
el.setAttribute("data-loading", "true")
undos.push(() => el.removeAttribute("data-loading"))
```

That is why the course's "no `wire:target` needed" claim holds: the attribute lands on the
element that triggered the request, so targeting is implicit. You then style against it —
`data-loading:opacity-75`, `data-loading:cursor-wait`, and for descendants Tailwind 4's
`in-data-loading:hidden` / `not-in-data-loading:hidden`. The Tailwind-4 requirement the course
states applies to the `in-*` descendant variants; plain CSS (`[data-loading] { … }`) works
with no Tailwind at all. `wire:loading` is not deprecated and still ships.

### Why this barely applies to PodText

Measured across `resources/views/`, 33 occurrences:

| form | count |
| --- | --- |
| `wire:loading.attr` | **28** |
| `wire:loading` (bare) | 2 |
| `wire:loading.class` | 1 |
| `wire:loading.class.remove` | 1 |
| `wire:loading.remove` | 1 |

`data-loading` is a **styling** hook. It cannot set `disabled`, `inert`, or `aria-busy` —
CSS cannot write attributes. So 28 of 33 uses are not migratable even in principle, and the
migration surface is the remaining 5.

Worse, those `.attr` uses are load-bearing rather than decorative. The comment already
standing in [media-picker-panel.blade.php:46](resources/views/livewire/admin/media-picker-panel.blade.php:46)
records that `disabled` on those targets has multiple writers — this component's upload guard,
Filament's own `wire:loading.attr`, and the modal focus trap — and that a single unverified
`focus()` fails two ways as a result. That is the same area as the M2 close/reopen race.

**Verdict: skip.** The lesson is sound and the feature is real, but for this repo it is a
5-site cosmetic change in the neighbourhood of a defect that has already cost a debugging
round. Recorded so nobody re-derives it and proposes a sweep.

---

## 7. `wire:sort` — verified syntax (note the colons)

Drag-and-drop reordering with no external plugin. Measured attribute names, all colon-
separated (**not** dot-separated — the captions are ambiguous here and it is an easy mistake):

`wire:sort` · `wire:sort:item` · `wire:sort:handle` · `wire:sort:ignore` ·
`wire:sort:group` · `wire:sort:group-id` · `wire:sort:config`

```blade
<ul wire:sort="reorder">
    @foreach ($rows as $row)
        <li wire:sort:item="{{ $row->id }}">
            <div wire:sort:handle>⠿</div>
            …
        </li>
    @endforeach
</ul>
```

The handler receives `($id, $position)`; position is supplied automatically.

Measured detail the course does not give: **handle mode is auto-detected, not configured.**

```js
useHandles: !!el.querySelector(handleSelector) || …
handle: preferences.useHandles ? "[x-sort\\:handle],[wire\\:sort\\:handle]" : null
```

If any `wire:sort:handle` exists in the container the whole row stops being draggable; if
none exists the whole row is draggable and `wire:sort:ignore` is the way to exempt links and
buttons. It also detects handles inside `<template>` content, so Alpine-templated rows work.
`wire:sort:group` / `:group-id` (dragging between lists) are not covered by the course at all.

Relevance: this is the natural replacement for hand-rolled ordering UI. Note that the card
template ordering redesign already settled on a **position-canonical, ×10 storage / 1..N
display, move-modal** model — a deliberate choice of an explicit move affordance over
drag-and-drop. `wire:sort` does not reopen that decision; it is worth knowing only if a
future surface wants dragging *in addition*. 0 occurrences in the repo today.

---

## 8. Optimistic UI — `wire:show`, `wire:text`, `wire:bind`, `$dirty`, `wire:transition`

Grouped in the course from Caleb Porzio's Laravel News article. `wire:show` and `wire:text`
predate v4 (Livewire 3.6); `wire:bind` and `$dirty` are v4.

All measured present in 4.3.4: `wire:show`, `wire:text`, `wire:bind` (and the
`wire:bind:<attr>` form), `wire:transition`, plus `wire:current` — which the course does not
mention at all.

`$dirty` is a wire property taking an **optional** property name
(`wireProperty("$dirty", (component) => (property) => …)`), so both `$dirty` (whole component)
and `$dirty('subject')` (one property) work, matching the course. `$island` exists as a magic
alongside it.

`wire:transition` measured behaviour, more complete than the course's version:

```js
if (!fromEl.querySelector("[wire\\:transition]") && !toEl.querySelector(…)) return callback()
if (typeof document.startViewTransition !== "function") return callback()
if (document.querySelector("dialog:modal")) return callback()
```

Three separate bail-outs, all silent: no marked element, no browser support, **or a native
modal `<dialog>` is open**. That third condition is undocumented in the course and is the
kind of thing that reads as "the transition randomly doesn't fire". It also composes with
islands — the morph checks whether an island's range contains a `[wire:transition]` element.

The course's Firefox caveat (basic transitions supported, *transition types* not) is a
browser-support claim I did not independently verify; it is plausible and was current at
recording. Treat as unverified.

**RTL note, not raised by the course and specific to this project:** View Transitions
animate along a physical axis. Any forward/backward directional transition designed on an
LTR assumption will run backwards in this app's Hebrew RTL UI. Anyone adopting
`wire:transition` here has to decide direction from the document direction, not from
"next/previous".

---

## 9. Applies here vs. generic — summary

| lesson | verified on 4.3.4 | applies to PodText? |
| --- | --- | --- |
| 01 Component formats | yes | **Yes, and there is an open gotcha** — §2. Config unpublished; `make:livewire` would produce an SFC unlike all 14 existing components. |
| 02 Islands | yes | **Yes, best candidate.** Public listings/transcript viewer. Needs a spec pass, not a patch — §3. |
| 03 Lazy/defer/bundle | yes | Possible, pairs with islands. The global `component_placeholder` config is the useful uncovered part. |
| 04 `wire:intersect` | yes | Generic. Real option for load-more on long listings; unused today. |
| 05 `data-loading` | yes | **No** — 28 of 33 `wire:loading` uses are `.attr`, which CSS cannot replace — §6. |
| 06 `wire:sort` | yes | Not now. Ordering UX already decided against drag-and-drop. |
| 07 Optimistic UI | yes | `$dirty` is the most useful piece (unsaved-changes affordance on admin forms). `wire:transition` carries an unflagged RTL direction problem — §8. |

### Surface that exists in 4.3.4 and the course never mentions

Worth a look before assuming the course is a complete v4 map: island `always:` / `skip:` /
`with:`; `config('livewire.component_placeholder')`; `Route::macro('lazy')`;
`wire:sort:group` / `:group-id` / `:config`; `wire:current`; `$island`. And from the
feature directory listing, whole subsystems outside the course's scope: `SupportCSP`,
`SupportInterceptors`, `SupportJsModules`, `SupportCssModules`, `SupportSlots`,
`SupportAsync`, `SupportLargePayloads`, `SupportCompiledWireKeys`.

---

## 10. Proposals arising (no code changed in this session)

1. **Publish `config/livewire.php` and set `make_command.type`** — §2. Smallest, highest
   value, no migration. Recommended value `'class'` with `'emoji' => false`.
2. **Spec an islands pass for the public item page** — §3. Candidate file
   `app/Livewire/Public/ContentItemTranscriptViewer.php`, but the visibility-query contract in
   `.ai/public-panel` has to be settled first. Not a patch.
3. **Do not sweep `wire:loading` → `data-loading`** — §6. Recorded as decided-against.
4. **If `wire:transition` is ever adopted, derive direction from document direction** — §8.

---

## 11. Sources

- [Course index](https://laraveldaily.com/course/livewire-v4) — 7 lessons, Jan 2026, video.
  - [01 Main Changes from v3](https://laraveldaily.com/lesson/livewire-v4/main-changes-from-v3)
  - [02 Islands](https://laraveldaily.com/lesson/livewire-v4/islands)
  - [03 Lazy, Defer, and Bundle](https://laraveldaily.com/lesson/livewire-v4/lazy-defer-bundle)
  - [04 wire:intersect](https://laraveldaily.com/lesson/livewire-v4/wire-intersect)
  - [05 Data-loading](https://laraveldaily.com/lesson/livewire-v4/data-loading)
  - [06 wire:sort](https://laraveldaily.com/lesson/livewire-v4/wire-sort)
  - [07 Optimistic UI and wire:transition](https://laraveldaily.com/lesson/livewire-v4/optimistic-ui-wire-transition)
- Vendor source read directly at v4.3.4:
  `config/livewire.php`;
  `src/Features/SupportIslands/{HandlesIslands.php,SupportIslands.php,Compiler/IslandCompiler.php}`;
  `src/Features/SupportLazyLoading/{SupportLazyLoading.php,BaseLazy.php,BaseDefer.php}`;
  `src/Features/SupportConsoleCommands/Commands/{ConvertCommand.php,MakeCommand.php}`;
  `dist/livewire.js` (for `data-loading`, `wire:intersect`, `wire:sort`, `wire:transition`,
  `$dirty`).
- This repo, measured 2026-08-07: `config('livewire.make_command')` via tinker; component
  inventory under `app/Livewire/` and `resources/views/components/`; `wire:loading` form
  breakdown across `resources/views/`.

### What I could not obtain

- **The course's demo repository.** Lesson 2 says "you will have access to the full
  repository, the link will be at the bottom of this lesson" — no such link is present in the
  lesson page text. Every code sample here was therefore reconstructed from captions and then
  confirmed against vendor source rather than copied from the author's repo.
- **Anything beyond the spoken track.** Lessons are screencasts with no text body; on-screen
  code that was never read aloud is not recoverable from captions. This is why §3–§8 lean on
  vendor source for exact syntax — the captions establish *what feature was shown*, not *how
  it was spelled*.
- **Independent confirmation of the Firefox transition-types limitation** (§8).
- The course has no discussion of Livewire 4 inside Filament. Filament 5 renders its own
  Livewire components, so whether islands are usable *within* Filament resource pages — as
  opposed to this repo's own `app/Livewire/` components — is untested and unanswered here.
