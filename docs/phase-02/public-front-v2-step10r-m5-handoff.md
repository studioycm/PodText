# Public Front v2 M5 Handoff

## Purpose

Step 10R-M5 adds safe card-template labels, icons, label alignment, and grouped nested parts across the `content_item`, `content_group`, and `contributor` card families.

## What was implemented

- Added escaped label rendering for card parts.
- Added label positions: `hidden`, `inline_before`, `inline_after`, `above`, and `below`.
- Added label alignment tokens: `start`, `center`, `end`, and `between`.
- Added finite icon rendering through an app-owned `PublicFrontCardIconResolver` using `Filament\Support\Icons\Heroicon` enum values.
- Added icon positions: `hidden`, `inline_before`, and `inline_after`.
- Added `part_group` with one nested child level and finite layout tokens.
- Added shared Blade part shell and family-specific recursive part components for item/group/contributor cards.
- Added nested `part_group.children` admin Builder support while excluding `part_group` from child blocks.
- Preserved legacy `before` / `after` saved position values by normalizing them to `inline_before` / `inline_after`.
- Restored previously-commented HF1 transcript viewer timestamp/speaker/copy controls so existing parser/viewer regression tests stay green. This is not the IP3 actions-menu implementation.

## Requirement IDs landed

M5 does not directly land IP requirement IDs R1-R23.

M5 provides the card-template primitives that IP1 will use for card-side date labels/icons and that later card convergence/layout steps can reuse.

## Finding IDs resolved

No performance finding IDs are resolved by M5.

F11 remains scheduled for B4, and F13 remains scheduled for C2.

## Files changed

- `app/Filament/Pages/PublicContentSettings.php`
- `app/Support/PublicFront/Cards/PublicContentGroupCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContributorCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicFrontCardIconResolver.php`
- `app/Support/PublicFront/Cards/PublicFrontCardPart.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRenderer.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `resources/views/components/public/card-part-shell.blade.php`
- `resources/views/components/public/content-group-card-part.blade.php`
- `resources/views/components/public/content-group-card.blade.php`
- `resources/views/components/public/content-item-card-part.blade.php`
- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/contributor-card-part.blade.php`
- `resources/views/components/public/contributor-card.blade.php`
- `resources/views/livewire/public/content-item-transcript-viewer.blade.php`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `docs/phase-02/public-front-v2-step10r-m5-implementation-plan.md`
- `docs/research/public-front-v2/18-step10r-m5-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-m5-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`

## Migrations/settings/schema changes

No database migration was added.

No new top-level settings key was added. M5 extends existing `card_templates.parts` JSON entries with optional finite fields and nested `children`.

## Settings keys and defaults

Existing card part JSON now accepts:

- `label_position`: `hidden|inline_before|inline_after|above|below`
- `label_alignment`: `start|center|end|between`
- `icon`: finite registry key, with `none` rendering no icon
- `icon_position`: `hidden|inline_before|inline_after`
- `type: part_group`
- `layout`: group layout `inline|stacked|grid` for `part_group`
- `columns`: `1|2|3|4|auto`
- `gap`: `compact|comfortable|spacious`
- `alignment`: `start|center|end|between`
- `children`: one-level nested normal parts

Defaults:

- Group `layout`: `inline`
- Group `columns`: `auto`
- Group `gap`: `compact`
- Group `alignment`: `start`
- Label/icon positions are nullable/hidden by render fallback.

## Registry/validator/render-context changes

- `PublicFrontCardTemplateRegistry` now exposes finite label position, label alignment, icon position, group layout, group column, group gap, and group alignment option lists.
- `PublicFrontConfigValidator` normalizes M5 fields and rejects unsafe/unknown values.
- Legacy `before` and `after` position tokens normalize to `inline_before` and `inline_after`.
- Nested `part_group` below one level is rejected.
- Group-only fields on normal parts are reported and not stored.
- `PublicFrontRenderContext` was not changed.

## Public rendering behavior

- Labels render as escaped text only.
- Icons render only through the app-owned finite `Heroicon` map.
- Raw SVG, Blade/PHP strings, Tailwind/classes, CSS, and HTML remain rejected by validator paths.
- `part_group` renders child parts in fixed inline, stacked, or grid class maps.
- Empty groups are skipped.
- Content item, content group, and contributor cards now use shared part-shell rendering.
- Existing HF1 transcript timestamp/speaker/copy controls are visible again; IP3 will later move/consolidate transcript actions behind settings.

## Admin behavior

- Public Content Settings card-template Builder exposes label position, label alignment, icon, icon position, group layout, columns, gap, alignment, and child part controls.
- `part_group.children` uses nested Builder blocks.
- Nested group blocks are excluded from child builders to prevent unbounded recursion.
- English and Hebrew labels/helper text were added for all new admin controls.

## Query/performance behavior

- No new database queries are added by M5.
- Group recursion is bounded to one nested level.
- Icon resolution is static PHP map lookup.
- The bounded query-count harness remains green.

## Translation keys added/updated

Updated in both `lang/en/admin.php` and `lang/he/admin.php`:

- `admin.fields.card_template_part_label_alignment`
- `admin.fields.card_template_part_group_alignment`
- `admin.fields.card_template_part_group_children`
- `admin.fields.card_template_part_group_columns`
- `admin.fields.card_template_part_group_gap`
- `admin.helpers.card_template_part_label_alignment`
- `admin.helpers.card_template_part_group_alignment`
- `admin.helpers.card_template_part_group_children`
- `admin.helpers.card_template_part_group_columns`
- `admin.helpers.card_template_part_group_gap`
- `admin.card_template_part_types.part_group`
- `admin.card_template_label_positions.*`
- `admin.card_template_label_alignments.*`
- `admin.card_template_icon_positions.*`
- `admin.card_template_group_layouts.*`
- `admin.card_template_group_columns.*`
- `admin.card_template_group_gaps.*`
- `admin.card_template_group_alignments.*`

## Tests added/updated/renamed

Updated `tests/Feature/PublicFrontCardTemplateBuilderTest.php`:

- Normalization coverage for label/icon/group fields, legacy position tokens, invalid token handling, group-only field rejection, and nested-depth rejection.
- Rendered-output coverage for all label positions, label alignment markers, finite icons, hidden controls, inline groups, grid groups, and all three card families.
- Settings page save coverage for nested `part_group.children` Builder shape.

No tests were renamed.

## Tests changed because old assertions intentionally moved/changed

No M5 test assertions were intentionally removed.

The existing `PublicItemPageMediaParserTest` assertions for `toggle-timestamps` and `toggle-speakers` exposed that the viewer controls were commented out in the local checkout. M5 restored those existing controls so the HF1/parser assertions continue to pass; IP3 still owns the future actions-menu/default-hidden behavior.

## Security/fallback behavior

- Label output uses escaped Blade `{{ }}` rendering.
- Icon output never reads raw SVG/component names/classes from JSON.
- Fixed PHP/Blade class maps are used for labels, groups, gaps, columns, and alignment.
- Invalid token values normalize to safe defaults or are removed.
- Nested groups beyond one level are rejected.
- Empty or unsupported groups are skipped at render time.

## Bounded query-count harness result

`php artisan test tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php` passed: 7 tests, 64 assertions.

## Impact on later mini-steps

- IP1 can add date attributes and rely on M5 labels/icons for custom date card parts.
- IP2/IP3 can reuse the same finite label/icon vocabulary if they need card-compatible shell rendering.
- B4 can converge legacy card options without inventing another label/icon path.
- C2 can build semantic card layout tokens on top of existing grouped part rendering.

## Open issues / follow-up decisions

- M5 did not add default date parts or default template changes; IP1 owns date attributes and any later default-template decision.
- M5 did not resolve F11 or F13.
- M5 did not implement IP3 transcript actions-menu/fullscreen/player-toggle behavior.
- Step 10R-IP1 is next.

## Quality gate summary

Focused gates passed:

- `php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `php artisan test tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php`
- `php artisan test tests/Feature/PublicPodcastsGroupsUxTest.php tests/Feature/PublicContributorsTopTranscribersUxTest.php`
- `php artisan test tests/Feature/PublicItemPageMediaParserTest.php`
- `php artisan test --filter=PublicTranscriptRenderingTest`
- `vendor/bin/pint --dirty --format agent`

Full gate passed:

- `vendor/bin/pint --dirty --format agent`
- `php artisan test` - 273 tests, 2276 assertions
- `vendor/bin/pint --test`
- `vendor/bin/filacheck` - 0 issues
- `npm run build`
- `git diff --check`

## Commit hash

This commit: `feat: add card template grouped parts labels and icons`
