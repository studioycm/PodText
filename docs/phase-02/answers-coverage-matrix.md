# Phase 02 Answers Coverage Matrix

| Topic | Decision | Covered in spec | Covered in prompt | Implementation phase | Notes |
|---|---|---|---|---|---|
| Public listings | `ContentItem` records | public-panel/search | 11 | Public UI | No public `Transcription` cards |
| Effective transcription | featured published, latest published, null | transcriptions | 07, 12 | Domain/Public | Same-item validation required |
| Featured unpublish/delete | clear or reject safely | transcriptions | 07 | Domain | Must be tested |
| Latest transcriptions | items ordered by effective transcription `published_at` | search | 11 | Public UI | User-facing label only |
| Item-only pinning | `ContentItem` fields only | feature-map/homepage | 08, 11 | Domain/Public | No group/category/tag/transcription pins |
| Manual pin order | `pin_order`, `pinned_at`, `pinned_until` | homepage | 08 | Domain | Expired pins ignored |
| Homepage order | pinned then latest combined list | public-panel | 11 | Public UI | No separate pin model |
| Group badge | cover image or initials | public-panel | 11 | Public UI | Blade component |
| Group homepage order | explicit field only if needed | homepage | 08 | Settings | Separate from item pinning |
| Homepage settings | Spatie Settings | homepage | 08 | Settings | Approved for Phase 02; Prompt 08 owns package addition if absent |
| Homepage sections | ordered visible DB records | homepage | 08, 11 | Settings/Public | Section queries return public items |
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

All required subjects are represented: homepage UX, filters, categories, Spatie tags, media embeds, item page, parser/viewer, studio planning, import/export, dashboards, settings, Boost, Blueprint, FilaCheck, and FilamentExamples source-snippet access proof through `search_examples`.
