# Public Front v2 Step 10R-UX3 Implementation Plan

## Selected Step

Step 10R-UX3 - Hebrew smart slugs and key contract alignment.

The user explicitly selected UX3 and explicitly excluded S1c and Importer Workbench.
HF2 is complete as `f719d30 fix: bound snapshot index column lengths for mysql`.
S1c has not run. UX3 was inserted into the central ledger after HF2 and before the
paused normal queue.

## Preflight

- `git status --short --branch`: clean `main...origin/main`.
- `git log --oneline -n 15`: confirms S1b `ada29fb`, HF2 `f719d30`, and the prompt-doc
  commit `e94a8d9`.
- App database connection: `mysql`.
- `php artisan migrate`: passed with `INFO Nothing to migrate`.
- Prompt 13 has not started.
- Step 11, S1c, and Importer Workbench remain out of scope.

## Research Notes

Research note:
`docs/research/public-front-v2/20-step10r-ux3-mcp-research.md`.

Boost and FilamentExamples were both available. FilamentExamples exposed
`search_examples` only, so the implementation uses snippet-backed patterns rather than
claiming source/detail access.

## Files Inspected

- `prompts/pre-13-prompts/hebrew-slugs-ux3-codex-prompt.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `.ai/guidelines/tooling-quality.md`
- `.ai/guidelines/import-export.md`
- `.ai/guidelines/public-panel.md`
- `.ai/guidelines/taxonomy-tags.md`
- `.ai/guidelines/media-embeds.md`
- `docs/phase-02/ai-development-lessons.md`
- Five sluggable models, `ContentTag`, Filament Resource forms, relationship option
  forms, content importers, public tag/podcast page classes, factories, and existing
  Pest tests.

## Implementation Plan

1. Add shared slug support.
   - `App\Support\Slugs\HebrewSlugger::slug()` keeps Hebrew letters, Latin letters, and
     digits, lowercases Latin, strips punctuation including geresh/gershayim and quotes,
     collapses dashes, caps output near 120 characters, and falls back to a lowercased
     ULID for empty output.
   - Add reusable suffixing through an existence callback so model and form paths share
     one behavior.

2. Replace duplicated model slug methods.
   - Keep existing `creating` hooks.
   - Replace private `uniqueSlug()` bodies in `Author`, `Category`, `ContentGroup`,
     `ContentItem`, and `HomepageSection`.
   - Preserve `ContentItem` group-scoped uniqueness exactly.

3. Fix content tags.
   - Override Spatie's `generateSlug($locale)` on `ContentTag`.
   - Add an idempotent artisan command to repair empty or ULID-like translated tag
     slugs using the shared slugger and type-scoped uniqueness.
   - Run the command locally and report counts.

4. Add the shared Filament slug helper.
   - Create `App\Filament\Forms\Components\SlugInput`.
   - Helper returns a source `TextInput` with `live(onBlur: true)` and a slug
     `TextInput` that is optional, max-length 255, has translated helper text, and a
     regenerate hint action.
   - Source blur fills slug only when blank. Manual slug edits are not clobbered.
   - Explicit regenerate re-derives from current source and applies the same uniqueness
     logic, including `ContentItem` group scope through `Get`.

5. Apply form helper.
   - Resource forms: `AuthorForm`, `CategoryForm`, `ContentGroupForm`,
     `ContentItemForm`, `HomepageSectionForm`.
   - Relationship option modals: author, category, and content group forms in
     `RelationshipOptionForms`.
   - Keep `ContentTagForm` slug disabled-display.

6. Align importer/form contracts.
   - Add `max:26` beside `ulid` to reference-key import columns and relationship
     reference-key columns targeting `char(26)`.
   - Keep slug import columns at `max:255` and item slug uniqueness scoped by group.
   - Align `embed_provider` to `maxLength(50)` in the form and `max:50` in the importer.
   - Confirm `media_url` and `embed_url` stay at `maxLength(2048)`.

7. Tests.
   - Add focused slugger unit tests.
   - Add admin/Livewire tests for Hebrew blank-slug creates, manual override, duplicate
     suffixing, regenerate action, relationship option modals, and ContentItem scoped
     suffixing.
   - Add content-tag Hebrew slug and public tag page resolution coverage.
   - Add importer row-failure coverage for 30-character reference keys.
   - Add form metadata assertion for URL/provider max lengths.

8. Final docs and handoff.
   - Update current-state, ledger completion row, AI lessons, and create the UX3 handoff.
   - Full quality gate: `php artisan test`, `vendor/bin/pint --test`,
     `vendor/bin/filacheck`, `npm run build`, and `git diff --check`.
   - Commit locally with `feat: add hebrew smart slugs and key contract alignment`.
   - Do not push.

## Out Of Scope

- S1c inline locks.
- Importer Workbench.
- Existing public slug renames.
- Column type changes or widening.
- Public route changes.
- Step 11, Prompt 13, P2/P3, AX, SL, B4, C2, and 9F work.

## Risks

- Filament hint-action testing can be version-sensitive. If direct hint-action testing is
  brittle, test the helper through visible form state changes and the action object name
  where Filament exposes it.
- Spatie tag slugs are JSON translations. The repair command must update only the
  translated slug values and preserve normal historical slugs.
- Factories still generate English slugs with `Str::slug()`. Hebrew behavior tests must
  create blank-slug records through forms or direct model creates.

## Stop Conditions

- Dirty working tree before app-code edits.
- MySQL migration failure.
- Package API mismatch that prevents implementing the documented slug helper or tag
  override safely.
- Any requirement to run S1c or Importer Workbench.
