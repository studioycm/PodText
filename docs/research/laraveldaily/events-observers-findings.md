# LaravelDaily on events, listeners and observers — findings

Requested by the Boost 2.5 session, 2026-08-07. Question: is having **no event spine**
defensible, and which of the three observer-registration styles to standardise on.

Sources are three lessons, all read from their **text bodies** (all three are substantial, so
per the transcript rule in [README](README.md) §3 no captions were needed). Every API claim
below is confirmed against `laravel/framework v13.23.0` as installed.

---

## 1. When LaravelDaily says to reach for events at all

The sharpest statement is not in the events lesson — it is in
[When NOT to Extract](https://laraveldaily.com/lesson/laravel-projects-structure/when-not-to-extract-avoiding-over-engineering)
(Mar 2026), which argues against the course's own preceding lessons.

**Use events when:**
- several *unrelated* things must happen in response to one action
- future code should be able to react **without modifying existing code**
- some reactions should run asynchronously

**Do not use events when:**
- there is exactly one consequence — call it directly
- cause and effect are tightly related and should be read together
- you are adding one "just in case" someone needs it later

Their worked example is unambiguous: if creating a user always and only sends a welcome email,
calling `Mail::send()` directly in the service is clearer than dispatching an event to one
listener. The stated cost of events is **indirection** — "when you fire an event, it's not
immediately obvious what happens next."

The dedicated lesson,
[Events/Listeners: When and How](https://laraveldaily.com/lesson/laravel-projects-structure/events-listeners-when-how),
frames the whole pattern as *"future-first thinking… opening the system for other developers to
add their listeners in the future."*

### Verdict on the no-event-spine question

**Defensible by their standard, and arguably correct.** Their test is "multiple unrelated
reactions" plus "extension by other developers without touching existing code". A single-team
app is not the audience that second clause describes, and zero `event()` calls is a smaller
problem than a speculative event layer, which is precisely what lesson 14 warns against.

Treat it as a deliberate position, not a gap — but revisit **if** a single action ever grows
three or more unrelated consequences.

### Caveat — flagging rather than adapting silently

**Every example they give is controller-dispatch in a classic HTTP app.** The lesson's own
demo dispatches from `store()`, and both open-source references (`laravelio/laravel.io`,
`tighten/novapackages`) are controller/job-centric codebases. Nothing in the lesson addresses
dispatching from Filament Resources, page actions, or Livewire components.

The *decision rule* transfers. The *worked examples* do not. With ~9 thin controllers and 48
lines of routes, there is no equivalent of their dispatch site in this app — the analogous
points are Filament action handlers and model lifecycle, and the course is silent on both.

---

## 2. Model events: Observers vs `booted()` vs Mutators

From [Before Saving: Mutator or Observer](https://laraveldaily.com/lesson/laravel-projects-structure/before-saving-mutator-observer)
(Mar 2026) and [Model Observers](https://laraveldaily.com/lesson/laravel-eloquent-expert/model-observers) (Mar 2026).

They split by **when the work happens**, not by taste:

| need | their recommendation |
| --- | --- |
| Transform a value **before** it is saved | **Mutator** — `Attribute::make(set: …)` |
| Side effects **after** the record changes | **Observer** |
| Same logic inline on the model | `booted()` — explicitly called *"old school"* |

Two things worth quoting because they are stronger than the usual "it depends":

- On `booted()`: it is described as what they saw *"in older Laravel projects"*, and that as
  Laravel matured developers moved to *"code structures explicitly dedicated to such
  operations."*
- On using an Observer for before-save transformation: they note the use case *"isn't mentioned
  in Laravel documentation"*, so it is *"probably not officially recommended"*, and that
  observers are *"more widely used for operations after saving."* Their own pick for the
  before-save example is the Mutator.

The Eloquent lesson adds the lifecycle detail: `created/updated/deleted/restored/forceDeleted`
fire after the write; `creating/updating/deleting` fire before; `saved()` covers create and
update together.

Their best observer example (`koel/koel`'s `AlbumObserver`) uses four hooks for four distinct
jobs — dispatch a thumbnail job on `saved`, clean up old files on `updating`, keep a
denormalised column in sync on `updated`, delete files on `deleted`. That is the shape they
consider idiomatic.

### Applied to the three-way split here

Measured in this repo: `::observe()` in providers ×9 · `booted()` in models ×10 ·
`#[ObservedBy]` ×2 · `Attribute::make` ×2 · `app/Observers` 4 files.

By their guidance the **`booted()` ×10 is the group to look at first** — not because it is
wrong, but because it is the one they call legacy, and because whatever sits in those closures
is doing either before-save transformation (their answer: Mutator/cast) or after-save side
effects (their answer: Observer). That is a content question, not a registration question.

---

## 3. Registration style: `Model::observe()` vs `#[ObservedBy]`

**Neither lesson states an explicit preference**, which is worth saying plainly rather than
inventing one. But both lead with the attribute:

- The structure lesson presents it as the way: *"you need to register the Observer on the Model
  with PHP attribute `ObservedBy`"* — no alternative shown.
- The Eloquent lesson shows both, attribute first, then `User::observe(UserObserver::class)` in
  `AppServiceProvider::boot()`.

### Verified against Laravel 13 — including a detail neither lesson mentions

`Illuminate\Database\Eloquent\Attributes\ObservedBy`:

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ObservedBy
{
    public function __construct(public array|string $classes) {}
}
```

- **Both call shapes are valid.** The structure lesson writes `#[ObservedBy([UserObserver::class])]`
  and the Eloquent lesson writes `#[ObservedBy(UserObserver::class)]`. The `array|string` union
  means neither is wrong — worth knowing, since the inconsistency between two lessons of the
  same publisher reads like one of them is a typo.
- **The attribute is `IS_REPEATABLE`** — several `#[ObservedBy]` on one model is legal. Neither
  lesson says so.

### Recommendation for the pick

If the goal is one style, **`#[ObservedBy]` is the better-supported choice**: it is what both
2026 lessons lead with, it keeps the wiring visible on the model rather than in a provider that
must be read separately, and it survives a provider being reorganised.

The one thing `::observe()` buys that the attribute does not is **conditional registration** —
registering only in some environments, or behind a config flag. If any of the 9 provider
registrations do that, they should stay.

---

## 4. Laravel 12/13-era API changes

Both lessons flag one change, and it checks out:

> In Laravel 10 and below, observers were registered in `EventServiceProvider`, which was
> removed in Laravel 11+.

Accurate **for the application skeleton** — this app has no `app/Providers/EventServiceProvider.php`
(only `AppServiceProvider`, `Filament/`, `HorizonServiceProvider`). The framework base class
`Illuminate\Foundation\Support\Providers\EventServiceProvider` still exists; it is the
app-level one that went.

The structure lesson also claims `#[ObservedBy]` "appeared in Laravel 10.44". **Not verified** —
the attribute exists in 13, but I did not confirm the introducing version.

Nothing in any of the three lessons is specific to Laravel 12 or 13.

### One trap in the events lesson

Its open-source examples show `EventServiceProvider` with `protected $listen = [...]` arrays,
from two codebases predating Laravel 11. **The lesson does not flag them as dated.** A reader
copying that pattern into a Laravel 13 app would be adding a provider the skeleton no longer
has.

The lesson does state the modern answer in passing — *"Events are registered automatically by
scanning `app/Listeners`"* — and that is verified:

```php
protected static $shouldDiscoverEvents = true;

protected function discoverEventsWithin()
{
    return static::$eventDiscoveryPaths ?: [$this->app->path('Listeners')];
}
```

So auto-discovery is the default and `$listen` arrays are legacy, but the lesson presents both
without distinguishing them.

**Worth a look in passing**, not part of the question asked: `StampImportSource::handle(ImportStarted $event)`
is the discoverable shape. `ResendWebhookEventSubscriber` maps several event classes to strings
in a property — that is a *subscriber*, which auto-discovery does not register; subscribers need
`Event::subscribe()`. Flagging it as worth confirming rather than asserting it is broken.

---

## 5. Sources

- [When NOT to Extract](https://laraveldaily.com/lesson/laravel-projects-structure/when-not-to-extract-avoiding-over-engineering) — Mar 2026, text
- [Events/Listeners: When and How](https://laraveldaily.com/lesson/laravel-projects-structure/events-listeners-when-how) — Mar 2026, text
- [Before Saving: Mutator or Observer](https://laraveldaily.com/lesson/laravel-projects-structure/before-saving-mutator-observer) — Mar 2026, text
- [Model Observers](https://laraveldaily.com/lesson/laravel-eloquent-expert/model-observers) — Mar 2026, video **with** text body; demo repo at `LaravelDaily/Laravel-Eloquent-Expert-Course` (per-lesson branches, e.g. `tree/lesson-09`)
- Framework source at v13.23.0: `Eloquent/Attributes/ObservedBy.php`,
  `Foundation/Support/Providers/EventServiceProvider.php`

### What I could not obtain

- **No dedicated events/listeners course exists** — the coverage is these lessons inside other
  courses. Site search for "events" surfaced no standalone course.
- The "Laravel 10.44" claim for `#[ObservedBy]` is unverified.
- Nothing found on dispatching events from **Filament** actions or Livewire components; the
  gap in §1 is a real gap in their catalogue, not a gap in my search.
