# Phase 02 Answers Coverage Matrix

This matrix verifies that the Phase 02 research/specification pack covers the user's answers and all required subject areas. Public results are `ContentItem` records. Pinning belongs only to `ContentItem`. "Latest transcriptions" means `ContentItem` records ordered by their effective/main published transcription `published_at`.

| Topic | Decision | Covered in spec | Covered in prompt | Implementation phase | Notes |
|---|---|---|---|---|---|
| Current project state | Prompt 05 is complete; Phase 02 starts from Slice 0 | `feature-map.md` | 06 | Research | No feature implementation in this task |
| Public result unit | `ContentItem` | `public-panel-ux-spec.md`, `search-and-filters-spec.md` | 11 | Public UI | Never render search results as `Transcription` cards |
| Transcription model | Child model of `ContentItem` | `transcriptions-model-spec.md` | 07 | Domain | Replaces direct item transcript storage |
| Effective/main transcription | Featured published transcript, then latest published transcript | `transcriptions-model-spec.md` | 07, 12 | Domain/Public UI | Defines item page default and latest sorting |
| Latest transcriptions | Items ordered by effective transcription date | `search-and-filters-spec.md` | 11 | Public UI | Hide items without an effective published transcript |
| Item pinning | Pin fields only on `ContentItem` | `feature-map.md`, `homepage-settings-spec.md` | 08, 11 | Domain/Public UI | No transcript/category/group pins |
| Homepage UX | Item-first feed, pinned-first where applicable | `public-panel-ux-spec.md`, `homepage-settings-spec.md` | 11 | Public UI | No separate pinned section by default |
| Public filters | Search, tags, categories, dates, duration, provider | `search-and-filters-spec.md` | 11 | Public UI | URL-backed where practical |
| Sort options | Latest/oldest transcription, title, duration, original date | `search-and-filters-spec.md` | 11 | Public UI | Search sort may disable pinned-first ordering |
| Categories | Custom hierarchical taxonomy | `taxonomy-tags-spec.md` | 08, 09, 11 | Domain/Admin/Public | Group assignment inherited by items |
| Spatie tags | Flat typed tags with public enablement | `taxonomy-tags-spec.md` | 08, 09, 11 | Domain/Admin/Public | Use `content` type/scope |
| Public tag visibility | Enabled public tags only | `taxonomy-tags-spec.md` | 11 | Public UI | Disabled tags remain admin-only |
| Media embeds | URL fields, provider metadata, allowlisted embeds | `media-embed-spec.md` | 12 | Media/Public UI | Never store raw iframe HTML |
| Item page | `ContentItem` page with effective transcript and tabs | `public-panel-ux-spec.md` | 12 | Public UI | Draft transcripts are hidden |
| Parser/viewer | Parse timestamps/speakers from transcription Markdown | `transcript-viewer-and-studio-future-plan.md` | 14 | Future planning | Parser output is derived, Markdown remains canonical |
| Studio planning | Future only | `transcript-viewer-and-studio-future-plan.md` | 14 | Future | No studio implementation in Phase 02 planning task |
| Import/export | Extend Filament-native import/export | `import-export-revision-spec.md` | 10 | Import/export | Portable keys, no numeric IDs |
| Dashboard metrics | Editorial counts and warning tables | `dashboard-metrics-spec.md` | 13 | Dashboard | No analytics/search logging |
| Settings | Spatie Settings for global options | `homepage-settings-spec.md` | 08, 13 | Settings | Section records are separate from global settings |
| Homepage sections | Ordered visible records when dynamic sections are needed | `homepage-settings-spec.md` | 08, 11 | Settings/Public UI | Adapt FilamentExamples homepage-section pattern |
| Admin management | Resources for new models/settings | `feature-map.md` | 09 | Admin UI | No Shield in this phase |
| Permissions | Admin-only for now, ability names documented for later | `feature-map.md` | 09, 13 | Admin UI/Future | Do not install Shield |
| Markdown safety | Sanitize through existing safe renderer path | `transcriptions-model-spec.md`, `public-panel-ux-spec.md` | 07, 12 | Security | Add regression tests when model moves |
| Embed safety | HTTPS allowlist and owned Blade renderer | `media-embed-spec.md` | 12 | Security | Original link fallback required |
| Hebrew/RTL | Hebrew default, RTL layouts | `public-panel-ux-spec.md`, `search-and-filters-spec.md` | 11, 12 | Public UI | Tests should check markers/classes where feasible |
| Tests | Pest coverage per implementation prompt | All specs | 07-14 | All phases | Run focused tests plus quality gate per phase |
| MCP research | FilamentExamples findings recorded | `../research/filament-examples-phase-02.md` | 06 | Research | Token not recorded |
| Prompt pack | Sequential implementation prompts created | `feature-map.md` | 07-14 | Planning | One phase at a time |
| Secrets check | No token/secret in files | `../research/filament-examples-phase-02.md` | 06 | Research | Validate before commit |
| No implementation | Only docs/prompts/guidelines changed | `feature-map.md` | 06 | Research | Commit only if safety checks pass |

## Omission Check

- Homepage UX: covered.
- Filters/search: covered.
- Categories: covered.
- Spatie tags: covered.
- Media embeds: covered.
- Item page: covered.
- Parser/viewer: covered.
- Studio planning: covered as future only.
- Import/export: covered.
- Dashboards: covered.
- Settings: covered.
- Public homepage/search result semantics: item-based and covered.
- Pinning semantics: item-only and covered.
- Latest transcription semantics: effective/main transcription date and covered.
