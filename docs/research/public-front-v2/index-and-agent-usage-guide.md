# Public Front v2 Research Index and Agent Usage Guide

> **ARCH1 routing notice — 2026-07-16:** Read
> `docs/research/settings-performance/07-sp3d-pre-research.md` before using this
> pack. Its JSON-first rule is superseded for independently managed Card
> Templates and Public Forms: those are now approved versioned Resources with
> revision-owned JSON. Bounded singleton policy remains Spatie Settings. Read
> `../settings-performance/09-arch1-drafts-authorization-research.md` as well:
> AUTHZ1 precedes ARCH1 and autosave working drafts are per user.

## Purpose

This index tells future agents how to use the Public Front v2 JSON-settings research pack without starting implementation.

## Read Order

1. `docs/phase-02/public-front-v2-final-report.md`
2. `docs/phase-02/public-front-v2-open-questions.md`
3. `01-json-settings-architecture.md`
4. The topic-specific research file for the feature being planned.
5. The matching blueprint under `docs/phase-02/blueprints/public-front-v2/`.

## Core Rule

JSON-first configuration remains the default inside an owning aggregate.
Independently listable, editable, referenceable, or auditable Card Templates and
Public Forms are approved model/Resource aggregates with immutable
revision-owned JSON. Bounded global policy remains typed Spatie Settings.

## Implementation Boundary

These files are planning artifacts only. Do not infer that implementation has been approved. Prompt 13 dashboard metrics should not start until Yoni approves the public-front plan.

## Topic Map

- `01-json-settings-architecture.md`: settings convention, registries, security, and test strategy.
- `02-card-template-builder.md`: card templates and parts.
- `03-public-display-sections-loopers.md`: homepage sections, loopers, query displays.
- `04-public-menu-header-manager.md`: public header/menu settings.
- `05-about-page-content-team-builder.md`: About page and team profiles.
- `06-public-forms-submissions.md`: JSON form definitions and submission exception.
- `07-transcription-publication-policy.md`: one-or-many published transcription policy.
- `08-contributors-transcribers-ux.md`: contributor directory and top transcribers.
- `09-latest-search-ux.md`: latest section and search/filter UX.
- `10-podcasts-groups-ux.md`: groups/podcasts page and group page.
- `11-seeders-demo-data.md`: seeders/defaults/demo cleanup.
- `12-povilas-filamentexamples-source-index.md`: external examples and access limitations.

## Required Agent Behavior

- Re-check `docs/phase-02/current-project-state.md` before implementation.
- Use Laravel Boost before code changes.
- Use FilamentExamples MCP before Filament code.
- Preserve Prompt 11R custom public Livewire/Blade listings.
- Preserve Prompt 11B `Author` contributor discovery.
- Preserve Prompt 12 item page, safe media, and parse-only transcript viewer.
- Update tests for real behavior, not static registration only.

## Decision Points To Resolve Before Coding

- Default value for multiple published transcriptions per item.
- Whether public form submissions are enabled in v1 and whether notifications/rate limiting are required.
- Whether `/groups` path is permanent while label can say podcasts.
- Whether About content uses Markdown only or RichEditor JSON plus safe renderer.
- Whether homepage section JSON columns are part of step 1 or delayed to looper step.

## Do Not Do

- Do not create `CardTemplate`, `PublicMenuItem`, `AboutPageBlock`, `TeamProfile`, `PublicFormDefinition`, `PublicDisplaySection`, or `PublicLooper` by default.
- Do not store raw Tailwind/CSS/SQL/PHP/Blade/HTML in JSON.
- Do not run Prompt 13 until the plan is approved.
