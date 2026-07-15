# Public Front v2 Open Questions

> **ARCH1 routing notice — 2026-07-16:** The old JSON-settings storage decision
> for Card Templates/Public Forms is superseded by the approved architecture in
> `docs/research/settings-performance/07-sp3d-pre-research.md`. This file remains
> historical decision evidence and must not be used to reopen ARCH1-A–S.

## Resolved User Decisions

These answers were provided after the research pack was created. Implementation agents should follow `docs/phase-02/public-front-v2-execution-plan.md` as the active decision source.

- Public form submissions are stored in v1 with admin review/status/history.
- Public forms require honeypot and rate limiting before live public use.
- Email notifications are deferred.
- Public form file uploads are deferred.
- Multiple public transcriptions are disabled by default; a content item has one public featured/effective transcription.
- Publishing a second public transcription requires an explicit publish-and-replace style action.
- Public content group URLs move to canonical `/podcasts`; internal architecture remains `ContentGroup`/`ContentItem`.
- About content supports both Markdown and RichEditor JSON through safe/sanitized renderers.
- Homepage JSON columns are decided during implementation planning; current execution plan adds them in the looper step.
- First card families are content item, group, and contributor.
- Card templates are global reusable templates with section-selected template keys and validated semantic section overrides.
- Public menu v1 is flat and includes public form actions.
- Latest/search uses top next/previous controls, bottom load-more, and a custom Livewire filter drawer.
- Demo data includes a cleanup Artisan command.
- Demo podcast cover assets are imported from an operator-provided local source during demo seeding with normalized names.
- `public/images/podtext-logo.jpg` is the normalized public logo asset.

## Blocking Before Implementation

1. Should public form submissions be stored in v1?
   - Recommended: yes only if admins need review/status/history. If yes, create the small `PublicFormSubmission` exception table.

2. Should public forms include email notifications in v1?
   - Recommended: defer until storage and review flow are stable.

3. Should public forms include honeypot/rate limiting before production launch?
   - Recommended: yes before enabling public forms on a live site.

4. What is the default transcription publication policy?
   - Option A: `true`, preserve multiple published transcriptions and Prompt 12 tabs.
   - Option B: `false`, simplify production with one featured public transcription per item.

5. If multiple published transcriptions are disabled, should publishing a new one fail or auto-unpublish existing published siblings?
   - Recommended: fail first; add explicit "publish and replace" action later if needed.

6. Should `/groups` remain the permanent public URL while labels may say podcasts?
   - Recommended: keep `/groups` stable until a deliberate redirect/path plan is approved.

7. Should the About page support Markdown only, RichEditor JSON, or both?
   - Recommended: Markdown first; add RichEditor JSON only if the safe renderer and sanitizer rules are implemented.

8. Should homepage section JSON columns be added in the first JSON architecture implementation step or delayed until the looper step?
   - Recommended: delay until the looper step unless the card template work needs section-level overrides immediately.

## Product Decisions

9. Which card families ship first?
   - Recommended v1: content item, group, contributor. Defer category/tag cards unless needed by loopers.

10. Should card templates be globally reusable only, or can each section store inline overrides?
   - Recommended: global reusable templates with section choosing a template key. Inline overrides can come later.

11. Should the public menu support dropdown/group items in v1?
   - Recommended: defer. Start with flat menu entries and public form actions.

12. Should public form file uploads be included in v1?
   - Recommended: defer until storage, validation, antivirus/size rules, and admin review are specified.

13. Should team profiles link to real `Author` records later?
   - Recommended: not in v1. Keep team profiles as About page settings.

14. Should contributor route labels/path be admin-configurable?
   - Recommended: label yes; path only with a route/redirect decision.

15. Should latest use numbered pagination, next/previous, load more, or all modes?
   - Recommended: next/previous at top and load more at bottom first; numbered pagination optional.

16. Should search filters use a custom drawer or Filament Action slide-over?
   - Recommended: custom Livewire drawer if it keeps URL state clearer; Filament Action only if it integrates cleanly.

17. Should demo content be called by `DatabaseSeeder` in local only or never automatically?
   - Recommended: never automatically in production; local-only call is acceptable if guarded and documented.

18. Should a demo cleanup Artisan command be implemented?
   - Recommended: document cleanup first; implement command only if demo data is used repeatedly.

## Research Access Questions

19. Is `github.com/studioycm/FilamentExamples` private, renamed, or unavailable?
   - Public GitHub API returned 404. Provide source access if this repository should be inspected.

20. Should a future agent get authenticated FilamentExamples source access?
   - The current MCP only returned snippets. Full source access would improve implementation detail for complex Builder/preview/table patterns.
