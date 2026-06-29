# Phase 02 Answers Coverage Matrix

| Topic | Decision | Covered in spec | Covered in prompt | Implementation phase | Notes |
|---|---|---|---|---|---|
| Public listings | `ContentItem` records | public-panel/search | 11 | Public UI | No public `Transcription` cards |
| Effective transcription | featured published, latest published, null | transcriptions | 07, 12 | Domain/Public | Prompt 07 committed; later prompts must preserve behavior |
| Same-item `featured_transcription_id` validation | featured transcription must belong to same item | transcriptions | 07, 09 | Domain/Admin | Implemented in Prompt 07 model logic; admin action tests still needed in Prompt 09 |
| Featured unpublish/delete | clear or reject safely | transcriptions | 07, 09 | Domain/Admin | Prompt 07 ignores unpublished featured records publicly and uses FK null-on-delete; admin unpublish/delete UX remains follow-up |
| Queryable effective transcription sorting | order items by effective transcription `published_at` | transcriptions/search | 07, 11 | Domain/Public UI | Prompt 07 implemented/tested a query scope; Prompt 11 must preserve in new UI |
| Latest transcriptions | items ordered by effective transcription `published_at` | search | 11 | Public UI | User-facing label only |
| Legacy `transcript_markdown` deprecation | no new canonical writes to item field | transcriptions/import-export | 07, 10 | Domain/Import | Prompt 07 removed normal writes; Prompt 10 must never import transcript content to legacy field |
| Prompt 07 post-run verification | Prompt 07 committed, migrations pending locally | current-state | docs-sync | Planning | Latest commit `7edb82d`; local DB has not run Prompt 07 migrations |
| Item-only pinning | `ContentItem` fields only | feature-map/homepage | 08, 11 | Domain/Public | No group/category/tag/transcription pins |
| Manual pin order | `pin_order`, `pinned_at`, `pinned_until` | homepage | 08 | Domain | Expired pins ignored |
| Homepage order | pinned then latest combined list | public-panel | 11 | Public UI | No separate pin model |
| Group badge | cover image or initials | public-panel | 11 | Public UI | Blade component |
| Group homepage order | explicit field only if needed | homepage | 08 | Settings | Separate from item pinning |
| Homepage settings | Spatie Settings | homepage | 08 | Settings | Approved for Phase 02; Prompt 08 owns package addition if absent |
| Homepage sections | ordered visible DB records | homepage | 08, 11 | Settings/Public | Section queries return public items |
| Slug auto-generation in admin forms | live-on-blur/title-name derived, manual override allowed | feature-map/taxonomy/homepage | 08, 09 | Admin UX | Check FilamentExamples/Povilas-style patterns before implementation |
| Israel/Hebrew date-time UI | `dd/mm/yyyy`, `dd/mm/yyyy HH:mm`, `Asia/Jerusalem` display/input | feature-map/public/search/import/dashboard | 08-13 | Admin/Public UX | Store dates normally with Laravel |
| Technical field helper text | slug/reference/provider/external/metadata/pin/featured fields need hints | feature-map/media/admin | 08, 09 | Admin UX | Use translation keys |
| Admin dashboard available metrics | show metrics available from current schema; stage later metrics | dashboard | 13 | Dashboard | No analytics/search logging |
| Immediate results | search shows initial items | search | 11 | Public UI | No empty-until-filtered default |
| Transcript search | deferred explicit action | search | 11 | Public UI | Not live default |
| Default search fields | item title, group title, categories, enabled tags | search | 11 | Public UI | Enabled tags only |
| Advanced search fields | authors, description, transcript, speakers, metadata, provider, URL | search | 11 | Later mode | Keep controlled |
| Desktop filters | search, chips, advanced panel, Apply/Clear | search | 11 | Public UI | Accessible |
| Mobile filters | drawer plus Apply/Clear | search | 11 | Public UI | URL state |
| Sort options | latest/oldest transcription, title, duration, original date | search | 11 | Public UI | Explicit user sort may override pins |
| Categories | custom hierarchy | taxonomy | 08, 09, 11 | Domain/Admin/Public | Descendant filtering |
| Group categories | inherited by items | taxonomy | 08, 11 | Domain/Public | Include in filters |
| Spatie tags | typed `content` tags | taxonomy | 08, 09, 11 | Domain/Admin/Public | No duplicate tag pivot |
| Enabled public tags | required | taxonomy | 11 | Public UI | Disabled hidden |
| Media foundation | fields before import/export | media | 08 | Domain | Prompt 08 owns schema |
| Safe embeds | URL-only, HTTPS, allowlist, Blade component | media | 12 | Security/Public | Fallback source link |
| Item page | one item, media, transcript tabs, metadata | public-panel | 12 | Public UI | Draft transcripts hidden |
| Parser now | timestamp/speaker parse in Prompt 12 | viewer | 12 | Public UI | Markdown canonical |
| No player sync now | deferred | viewer | 12, 14 | Future | Prompt 14 plans sync |
| Viewer show/hide | timestamps and speakers | viewer | 12 | Public UI | Local preference only |
| Future studio | planning only | viewer | 14 | Future | No implementation |
| Import/export transcript files | `.md`/`.txt` to `Transcription` records | import-export | 10 | Import/export | Never legacy field |
| Dashboard metrics | editorial widgets | dashboard | 13 | Dashboard | No analytics/search logging |
| Boost usage | use when available; record failures | tooling | 06, all | Tooling | MCP available in Prompt 06S; fallback documented for transport failures |
| Blueprint usage | blueprint files per prompt | blueprints | 06-15 | Planning/Implementation | Exact primitives required |
| FilaCheck usage | final gate in implementation prompts | tooling | 07-15 | Quality | Full `vendor/bin/filacheck` final |
| FilamentExamples proof | source snippets through MCP search | research | 06 | Research | No separate fetch tool exposed |

## Omission Check

All required subjects are represented: Prompt 07 post-run state, homepage UX, filters, categories, Spatie tags, media foundation, media embeds, item page, Prompt 12 parser/viewer, Prompt 14 studio planning, import/export, dashboards, settings, slug auto-generation, Hebrew/Israel date formatting, technical field helper text, Boost, Blueprint, FilaCheck, and FilamentExamples source-snippet access proof through `search_examples`.
