# Public Front v2 Step 10R-M5 Implementation Plan

## 1. Selected Mini-Step And Dependency Verification

Selected mini-step: Step 10R-M5 - Card-template labels/icons rendering, label alignment, and `part_group` nested parts.

Dependencies verified from local history and the central ledger:

- M1 `800218a` exists.
- M2 `e813513` exists.
- M3 `825004c` exists.
- M4 `af9f399` exists.
- HF1 `2a5ff96` exists.
- The ledger marks M5 as the first active pending step. IP1, IP2, and IP3 were inserted after M5 and before M6 as required by the continuation runner.

## 2. Current Local Repo Evidence

- Current branch: `main...origin/main`.
- Current HEAD before implementation: `2db6d5b refactor: comment out unused transcript-related components and update Hebrew translation for homepage title`.
- Working tree before app-code changes contains only expected docs-only sequence updates plus the M5 research note.
- `php artisan migrate:status` shows the completed M1-M4/HF1 migrations as run, including `author_transcription`, `drop_author_content_item_table`, `add_public_transcription_policy_setting`, and `add_public_transcription_display_settings`.
- Public routes for `items`, `podcasts`, `contributors`, `search`, and `about` are present.
- Admin routes include the public content settings page.

## 3. Files Inspected

- `AGENTS.md`
- `.ai/guidelines/tooling-quality.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `docs/phase-02/public-front-v2-transcription-display-decisions.md`
- `docs/phase-02/public-front-v2-performance-efficiency-audit.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/phase-02/ai-development-lessons.md`
- M1-M4, HF1, and prior A/B handoffs named by the runner
- `docs/research/public-front-v2/17-step10r-m4-public-rendering-aggregates-mcp-research.md`
- `app/Support/PublicFront/Cards/PublicFrontCardPart.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplate.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRenderer.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContentGroupCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContributorCardPresenter.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/content-group-card.blade.php`
- `resources/views/components/public/contributor-card.blade.php`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php`
- `lang/en/admin.php`
- `lang/he/admin.php`

## 4. Laravel Boost Findings

Boost tools used: `application_info`, `database_schema`, and `search_docs`.

Relevant findings:

- Installed versions are Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2.
- The `settings` table stores Spatie settings payloads. M5 adds optional nested fields inside existing `card_templates` entries, so no database migration is required.
- Filament Builder and Repeater components are the right fit for existing `card_templates.parts` editing.
- Filament `Heroicon` enum values should be resolved from PHP-owned finite maps rather than accepting component/class names from JSON.
- Blade `{{ }}` escaping and fixed class maps remain the safety boundary for labels and custom text.

Search-docs query themes:

- Filament settings tabs, Builder, Repeater, nested arrays, and dependent select fields.
- Heroicon enum usage in Filament/Blade.
- Pest rendered-output assertions.
- Tailwind v4 fixed utility class usage.
- Safe Blade dynamic attributes and escaped output.

## 5. FilamentExamples MCP Findings And Access Level

Access level: search/snippet only. No source/read/detail tool was exposed.

Query batches:

- `card template part group`, `nested builder settings`, `badge icon label`, `metadata row renderer`
- `safe icon map settings`, `SettingsPage tabs`, `settings repeater`, `builder nested blocks`
- Refined: `public card layout presenter`, `custom view card grid`, `repeater itemLabel collapsed settings`, `nested settings state path`

Relevant examples:

- `v4/tables/table-as-grid-with-cards/.../UserResource.php`: keep card display prepared as controlled structured data before rendering.
- `v4/forms/select-with-custom-html-values-and-search-results/.../CategoryForm.php`: finite icon options are acceptable, but PodText should not persist raw HTML option labels.
- `v4/forms/repeater-five-advanced-use-cases/...`: nested form controls should be bounded and user-manageable.
- `v4/forms/wizard-invoice-form/.../ManageSettings.php`: settings pages can edit nested array state through form components.

## 6. Requirement Or Finding IDs Owned This Run

M5 is a generic card-template foundation step.

- R IDs landed: none directly. IP1 depends on M5 for card-side date labels/icons and grouped metadata rows.
- F IDs resolved: none. F11 remains scheduled for B4, and F13 remains scheduled for C2.

## 7. Schema And Settings Reality Check

- No schema changes are needed.
- Existing `PublicContentSettings::card_templates` stores templates as JSON.
- Existing card part storage already has `label`, `label_position`, `icon`, and `icon_position`; these are not rendered yet.
- Existing position tokens are `before` and `after`. M5 will normalize them to `inline_before` and `inline_after` for compatibility.
- New nested group fields live only under individual card part entries.

## 8. Data Mapping Check

No episode-date data mapping is implemented in M5.

M5 only ensures any supported card part can safely render escaped labels, finite icons, and one-level grouped children. IP1 owns `published_at`, `original_published_at`, and transcription-date card attributes.

## 9. Current Rendering, Query, And Settings Gaps

- Labels and icons are normalized into part arrays but ignored by Blade.
- No `label_alignment` token exists.
- No `part_group` type or child part support exists.
- Current card views repeat per-part `data-card-part` attributes across three families.
- Existing presenters return flat parts only.
- Existing settings admin cannot configure group fields or nested children.
- M5 should add no new queries; all output should be rendered from already-prepared card data.

## 10. Exact JSON Shape, Defaults, And Validator Changes

New finite part fields:

```json
{
  "label_position": "hidden|inline_before|inline_after|above|below",
  "label_alignment": "start|center|end|between",
  "icon": "none|image|title|description|calendar|clock|tag|folder|user|users|microphone|link|play|document|podcast|sparkles|arrow_right",
  "icon_position": "hidden|inline_before|inline_after"
}
```

New grouped part shape:

```json
{
  "type": "part_group",
  "layout": "inline|stacked|grid",
  "columns": "1|2|3|4|auto",
  "gap": "compact|comfortable|spacious",
  "alignment": "start|center|end|between",
  "children": []
}
```

Validator changes:

- Add `part_group` to the finite part type list.
- Add `label_alignment`, `columns`, `gap`, `alignment`, and `children` to allowed fields.
- Normalize legacy `label_position` / `icon_position` values `before` and `after` to `inline_before` and `inline_after`.
- Validate group fields through finite registries only.
- Normalize child parts recursively with a maximum group depth of one.
- Reject nested `part_group` children below the first group level.
- Continue rejecting raw HTML, CSS/Tailwind class strings, PHP/Blade-looking strings, SVG-looking strings, and unknown fields through existing plain/finite validators.

## 11. Exact Files To Change

Application/support:

- `app/Support/PublicFront/Cards/PublicFrontCardPart.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRenderer.php`
- `app/Support/PublicFront/Cards/PublicFrontCardIconResolver.php` (new)
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContentGroupCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContributorCardPresenter.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`

Admin/settings:

- `app/Filament/Pages/PublicContentSettings.php`

Blade:

- `resources/views/components/public/card-part-shell.blade.php` (new)
- `resources/views/components/public/card-part-icon.blade.php` (new if needed)
- `resources/views/components/public/content-item-card-part.blade.php` (new)
- `resources/views/components/public/content-group-card-part.blade.php` (new)
- `resources/views/components/public/contributor-card-part.blade.php` (new)
- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/content-group-card.blade.php`
- `resources/views/components/public/contributor-card.blade.php`

Translations/tests/docs:

- `lang/en/admin.php`
- `lang/he/admin.php`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `docs/research/public-front-v2/18-step10r-m5-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-m5-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-m5-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`

## 12. Tests To Add Or Update

Primary focused suite: `tests/Feature/PublicFrontCardTemplateBuilderTest.php`.

Coverage to add:

- Label renders above, below, inline before, and inline after a card value.
- Label alignment tokens normalize and emit safe data markers/classes.
- Finite icons render before/after values.
- Hidden labels and icons do not render.
- Invalid icon, label position, icon position, and label alignment values normalize safely.
- `part_group` renders multiple child parts in one line.
- Grid group renders configured columns/gap/alignment markers.
- Nested depth is constrained.
- Raw classes/HTML/SVG/PHP/Blade-looking values are rejected.
- Content item, content group, and contributor card families support labels/icons.
- Settings page can expose/save the nested group form shape if implementation remains manageable.
- `tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php` remains green.

## 13. Translation Keys To Add Or Update

`lang/en/admin.php` and `lang/he/admin.php`:

- Add `part_group` to `card_template_part_types`.
- Replace/add label positions: `hidden`, `inline_before`, `inline_after`, `above`, `below`.
- Replace/add icon positions: `hidden`, `inline_before`, `inline_after`.
- Add label alignment options: `start`, `center`, `end`, `between`.
- Add group column options: `1`, `2`, `3`, `4`, `auto`.
- Add group gap options: `compact`, `comfortable`, `spacious`.
- Add group alignment options: `start`, `center`, `end`, `between`.
- Add admin field/helper labels for label alignment, group columns, group gap, group alignment, and group children.

## 14. Performance Implications

- M5 should add no database queries.
- Presenter recursion is bounded to one nested group level.
- Icon resolution is a static PHP map.
- Group rendering uses already-prepared child part arrays.
- The bounded public rendering query-count harness must stay green.

## 15. Out Of Scope

- No IP1 date settings or item-page settings.
- No episode page header rebuild.
- No transcript viewer actions/menu/fullscreen changes.
- No public-front config cache.
- No listing query optimization.
- No derived transcript segment storage.
- No B4 legacy card-options convergence.
- No C2 semantic layout token convergence.
- No 9F, Step 11, Prompt 13, Prompt 14, or Prompt 15 work.
- No new models, public Filament Tables, worktrees, pushes, or `.env` edits.

## 16. Stop Conditions

Stop before app-code changes if:

- New unexpected app-code dirt appears.
- The M5 ledger status no longer matches the selected step.
- The grouped part implementation requires schema changes.
- Full nested admin UI would require unbounded recursion or raw class/HTML fields.
- A contradiction appears between the runner, existing docs, installed package guidance, and code reality.
