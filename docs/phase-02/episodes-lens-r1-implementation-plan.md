# Episodes-Lens R1 Implementation Plan (Native-first)

Date: 2026-08-05. Session: episodes/nav mini-project (design session continued
into implementation on the operator's direct instruction, which also released
this session from the orchestrator's quiet-machine hold — the hold named the
operator as a valid releaser). Spec: `docs/phase-02/episodes-lens-design-spec.md`
plus the 2026-08-04/05 decision rounds (EQ-1…EQ-12, P-EL1–P-EL8, and the three
new rules) recorded in the spec's decision annex. R2 (custom-forward) is a
LATER phase — nothing custom-control ships here.

Push posture: commits stay local; **prepare to push** only. Auto-deploy is ON —
the push happens on the operator's explicit word, never from this plan.

## Component-docs round (operator directive) — what R1 adopts natively

- Enums drive everything: `SelectColumn::make('status')->options(PublicationStatus::class)`
  auto-labels from `HasLabel`; enum-cast columns auto-label in `TextColumn`
  and as **group titles** in table grouping (zero custom title code for the
  status group).
- `FilamentTimezone::set()` — global Filament timezone default; set once from
  `UiTimezone` (structural one-home win; existing explicit per-column
  timezones remain and agree).
- `->since()` + `->dateTimeTooltip()` — the עודכן column renders relative
  with the exact day-first stamp in a tooltip.
- Tabs: `getTabs()` keys persist in `?tab`; labels passed explicitly
  (Hebrew); `extraAttributes(['data-scope' => …])` for test targeting.
- Editable-column testing rides `updateTableColumnState($column, $recordKey, $input)`
  (vendor `HasColumns.php:43`) + `assertTableColumnStateSet`.
- Vendor-verified earlier (research doc): `FiltersLayout::AboveContentCollapsible`,
  `filtersFormColumns`, filter `schema()`, `ToggleButtons::grouped()`,
  `groups()/Group::collapsible()`, `SelectColumn` concerns
  (`selectablePlaceholder`, `disabled`, `rules`, implicit `in` rule), column
  `action()` (`CanCallAction.php:14`), `reorderableColumns()`,
  `NavigationGroup::collapsed()`, `getSubheading()`.

## Task 1 — Model layer: one publication-date home

New `app/Models/Concerns/InteractsWithPublicationDate.php` used by
`ContentItem`, `ContentGroup`, `Transcription` (all three carry
`status` (PublicationStatus cast) + `published_at`):

```php
trait InteractsWithPublicationDate
{
    public static function bootInteractsWithPublicationDate(): void
    {
        static::saving(function (Model $model): void {
            // publish-stamps-date (operator rule 2026-08-04): a record saved
            // as published with no publish date gets stamped now; an explicit
            // date is never overwritten; unpublishing keeps the date.
            if ($model->status === PublicationStatus::Published && blank($model->published_at)) {
                $model->published_at = now();
            }
        });
    }

    protected function effectivePublishedAt(): Attribute
    {
        // effective-published-date resolver (operator rule 2026-08-05):
        // read-side fallback to created_at for published rows saved before
        // the stamping rule existed. No backfill, no data invention.
        return Attribute::get(fn (): ?CarbonInterface => $this->published_at
            ?? ($this->status === PublicationStatus::Published ? $this->created_at : null));
    }

    public function scopeOrderByEffectivePublishedAt(Builder $query, string $direction = 'desc'): Builder
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $query->orderByRaw(
            "coalesce(published_at, case when status = ? then created_at end) {$direction}",
            [PublicationStatus::Published->value],
        )->orderBy('id', $direction);
    }
}
```

Notes: the stamp hook runs on every save path (forms, inline column, importer
rows, workspace) — explicit values win because the hook only fills blanks.
Importer-provided dates are set before save → preserved. `ContentGroup`'s
publish flip via the remedy action stamps its date through the same hook.

Tests `tests/Feature/PublicationDateRuleTest.php` (dataset over the three
models): stamps on publish-with-null (create + update paths); preserves an
explicit date; draft saves never stamp; unpublish keeps the date; accessor:
published+null → created_at, published+set → published_at, draft → null;
sort scope: published(old date) vs published(null, older created_at) order
both directions; re-saving a published row does not move the stamp.

## Task 2 — ContentItemPolicy (EQ-6)

`app/Policies/ContentItemPolicy.php`: `viewAny/view/create/update` → `true`
(uniform panel-admin authority preserved); `delete/deleteAny` →
`$user->hasRoleAtLeast(UserRole::SuperAdmin)`. Registered via
`Gate::policy(ContentItem::class, ContentItemPolicy::class)` beside the
existing two policies in `AppServiceProvider`.

Known blast radius (operator-approved): the episodes bulk `DeleteBulkAction`,
the relation manager's `DeleteAction`, and the workspace header `DeleteAction`
become super-admin-only (Filament consults the policy once it exists).
`Gate::allows('update', $record)` flips from false (no policy) to true for
admins — which is what arms the inline column's `disabled()` gate correctly.

Tests `tests/Feature/ContentItemPolicyTest.php`: policy unit matrix; list
page: admin does NOT see the delete bulk action, super-admin does; workspace:
`assertActionHidden(DeleteAction)` for admin, visible for super-admin;
update stays allowed for both tiers (inline column enabled).

## Task 3 — Scope enum + counts service + six tabs (EQ-4)

`app/Enums/EpisodeListScope.php` — string enum `All/Drafts/Visible/Scheduled/
Blocked/Pinned`, `HasLabel` from `admin.episode_scopes.{value}` (en+he),
`description()` from `admin.episode_scopes.descriptions.{value}`.

`app/Support/ContentItems/EpisodeListScopeQuery.php` — the ONE home for scope
predicates, built to reconcile exactly (`טיוטות + גלויים + מתוזמנים + חסומים
= הכל`):

- `visible` → the model's own `published()` scope (one home; no re-derived
  predicate).
- `scheduled` → status published AND `published_at > now()`.
- `blocked` → status published AND (`published_at` null or ≤ now) AND NOT
  (group published AND a published transcription exists).
- `drafts` → status draft. `pinned` → `currentlyPinned()` (window scope).
- `apply(Builder, EpisodeListScope): Builder` and `counts(): array` — counts
  in ONE aggregate query (`sum(case …)` + `exists` subqueries per branch,
  pinned window included, plus total). No polling, no cache in R1 (one cheap
  aggregate per page load at filling-era scale; a cache seat is noted for
  when scale demands it).

`ListContentItems`:

```php
public function getTabs(): array
{
    $counts = app(EpisodeListScopeQuery::class)->counts();

    return collect(EpisodeListScope::cases())->mapWithKeys(fn (EpisodeListScope $scope): array => [
        $scope->value => Tab::make($scope->getLabel())
            ->modifyQueryUsing(fn (Builder $query): Builder => app(EpisodeListScopeQuery::class)->apply($query, $scope))
            ->badge($counts[$scope->value] ?? 0)
            ->extraAttributes(['data-scope' => $scope->value]),
    ])->all();
}

public function updatedActiveTab(): void
{
    $this->activeTab = EpisodeListScope::tryFrom((string) $this->activeTab)?->value
        ?? EpisodeListScope::All->value;   // state-narrows-at-the-door

    parent::updatedActiveTab();
}

public function getSubheading(): ?string
{
    // scope description + «של הפודקאסט X» when the podcast filter is active
}
```

Tests `tests/Feature/EpisodeListScopeTest.php`: an 8-state fixture matrix
(draft; visible; scheduled; blocked-by-group; blocked-by-transcript;
blocked-both; pinned-active; pin-expired) proving membership per scope AND
the exact partition sum; tab badges rendered; switching tabs filters records;
forged tab value narrows to `all`; subheading with and without the podcast
filter; a structural loop test asserting every enum case has en+he labels +
descriptions and a tab (whole-set-contracts).

## Task 4 — Table overhaul + relation-manager parity

`ContentItemsTable::configure` changes (and shared static builders so
`ContentItemsRelationManager` reuses the same pieces):

- Query: add `withExists(['transcriptions as has_published_transcription' =>
  fn ($q) => $q->published()])`; keep existing eager loads (podcast state
  arrives via the loaded `contentGroup`).
- New `app/Enums/EpisodePublicState.php` — `Visible/Scheduled/Draft/
  BlockedGroup/BlockedTranscription`, `HasLabel` + `HasColor`
  (success/info/gray/danger/danger), labels en+he. Small pure resolver
  `EpisodePublicState::for(ContentItem $record)` computing ONLY from loaded
  data (`service-hop-cost` discipline) — a query-budget test renders 25 rows
  under `DB::listen` and asserts a fixed query count.
- Column set (order): image (toggleable), **title (the only non-toggleable,
  P-EL8)**, podcast (toggleable), `public_state` badge column (state = enum,
  color/label from enum; tooltip carries the scheduled date or blocker
  detail; toggleable), transcript context (existing, toggleable),
  transcribers (toggleable), **status → `SelectColumn`** (below), **פורסם →
  `effective_published_at`** (below), pinned (toggleable), **עודכן →
  `updated_at->since()->dateTimeTooltip()`** (toggleable, visible), existing
  hidden tail unchanged. `reorderableColumns()` on. Structural test loops the
  column set asserting toggleability everywhere except `title`.
- Status inline (EQ-5): `SelectColumn::make('status')
  ->options(PublicationStatus::class)->selectablePlaceholder(false)
  ->rules(['required'])->disabled(fn (ContentItem $r): bool =>
  ! Gate::allows('update', $r))->afterStateUpdated(…truthful notification…)`.
  The notification recomputes the record's public state fresh and says what
  actually happened: «פורסם — גלוי באתר» / «פורסם — מתוזמן ל־…» /
  «פורסם — עדיין לא גלוי: …» / «הועבר לטיוטה — ירד מהאתר». The model hook
  stamps the date on the same save.
- פורסם column: `TextColumn::make('effective_published_at')` day-first via
  the shared format home, `sortable(query: orderByEffectivePublishedAt)`,
  `placeholder('—')`, tooltip «לפי תאריך היצירה» when riding the fallback,
  and `->action(changePublishedAtAction())` — the CELL opens the reschedule
  modal (`DateTimePicker` `seconds(false)`, `displayFormat('d/m/Y H:i')`,
  `timezone(UiTimezone::name())`, helper text; `authorize('update')`).
- Remedy doors (EQ-9, P-EL6): contextual ungrouped actions —
  «פרסום הפודקאסט» (visible when `BlockedGroup`; `requiresConfirmation`;
  server-side re-check; updates the group → its own stamp hook fires) and
  «פתיחת התמלול» (URL → workspace, visible when `BlockedTranscription`).
- ActionGroup (P-EL4): [שינוי תאריך פרסום · הוספת תמלול · image ·
  download-external ×2 · **עריכת הפודקאסט** (URL → ContentGroup edit) ·
  עריכה (מערכת)]. Ungrouped stay: workspace + effective-transcription (+
  contextual remedies).
- Filters (EQ-7): `layout: FiltersLayout::AboveContentCollapsible` +
  `filtersFormColumns(3)`; pinned `TernaryFilter` → `Filter::schema([
  ToggleButtons (הכל/מוצמדים/לא מוצמדים) grouped])` + `indicateUsing`
  (P-EL7); NEW published date-range filter — two `DatePicker`s, Jerusalem
  day bounds converted to UTC instants (`jerusalem-walls`; near-midnight
  fixture test), `Indicator::removeField` per side; `embed_provider` options
  behind a 60s bounded cache (public-filters precedent); status
  `SelectFilter` KEPT (dashboard doorway coupling — retirement stays in the
  later controls-polish step); podcast/categories/tags/transcriber filters
  unchanged.
- Grouping (EQ-8): `groups([Group::make('contentGroup.title')->collapsible()
  ->label(…), Group::make('status')->collapsible()->label(…)])` — status
  titles auto-label from the enum cast. No `defaultGroup`.
- `defaultSort('updated_at', 'desc')` (EQ-10).
- Relation manager: adopts the shared builders (public-state column, status
  SelectColumn, effective-date column + action, remedy doors, ActionGroup)
  while keeping its own ordering and delete action; a parity test pins the
  shared pieces.

## Task 5 — Navigation (EQ-1/2/3)

`AdminNavigationOrder`: `GROUPS` order becomes [taxonomy, content];
`ContentItemResource` → `['sort' => 15, 'group' => null]` (lands between
פרק חדש 10 and רשומות 20); the content `NavigationGroup` gains
`->collapsed()` (taxonomy stays expanded). `TranscriptionResource` overrides
`shouldRegisterNavigation()`: visible when multi-mode OR super-admin
(single-mode admins lose the item; URL keeps working — docs caveat is the
intended declutter semantics). `AdminPhase02ResourcesTest` re-pins: new
ungrouped order, group order, collapsed flag, and a visibility matrix
(single+admin hidden / single+super-admin visible / multi+admin visible).
Dashboard stays the landing (no change). Labels unchanged (EQ-11).

## Task 6 — Global timezone default (structural win)

`FilamentTimezone::set(UiTimezone::name())` in `AppServiceProvider::boot()`.
Existing explicit per-column timezones agree with it; date-only fields are
exempt by vendor design. One assertion added beside the UiTimezone policy
test.

## Task 7 — Lang keys (en+he, `speaks-both-languages`)

`admin.episode_scopes.*` (6+6 descriptions), `admin.episode_public_state.*`
(5), actions (`change_published_at`, `edit_podcast`, `publish_podcast`,
`open_blocked_transcript`), pinned filter options, published-range filter
labels + indicators, subheading patterns, notifications (4 outcomes + date
saved + podcast published), helper texts. Presence asserted in tests for both
locales with mutation checks (F3 pattern).

## Gate + docs + commits

Per-task targeted Pest runs; `vendor/bin/pint --dirty` after each slice;
`vendor/bin/filacheck --dirty` during iteration (never `--fix`). Final gate,
in order: full `php artisan test` · `vendor/bin/pint --test` · full
`vendor/bin/filacheck` · `npm run build`. Then
`docs/phase-02/current-project-state.md` gains the R1 row, the spec's status
updates, and pathspec commits land per slice. **No push** — a prepare-to-push
summary closes the work.

Out of scope (R2/later): chip-strip component, grouping/sort toggle rows,
three-segment visibility cell, saved views, per-column search, sticky
sort/search, status-filter retirement + dashboard doorway re-pointing,
workspace podcast-context strip (EL-P7), month grouping.
