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
| Admin Resource relation manager research | use official Filament 5 docs plus FilamentExamples source snippets | research/admin UX | pre-08 docs refinement, 09 | Planning/Admin UX | New research file: `docs/research/filament-examples-admin-resource-relation-managers.md` |
| `ContentItemResource` transcriptions relation manager | add `TranscriptionsRelationManager` for item-scoped transcript management | admin/transcriptions | 09 | Admin UX | Primary admin UX for adding/editing transcript bodies for one item |
| Standalone `TranscriptionResource` | keep for global search/filtering/maintenance | admin/transcriptions | 09 | Admin UX | Does not replace item-scoped relation manager |
| Combined relation manager tabs with content form | use researched Filament combined tabs on `EditContentItem` | admin/transcriptions | 09 | Admin UX | `hasCombinedRelationManagerTabsWithContent()` decision lives in Prompt 09 blueprint |
| Content/form tab label customization | use translation-key label for item details tab | admin/transcriptions | 09 | Admin UX | Keep content/form tab before relation tabs unless implementation proves otherwise |
| Relation manager tab badge/icon | customize transcriptions tab with label, Heroicon enum where supported, and count badge | admin/transcriptions | 09 | Admin UX | Defer badge only after version/API support is verified |
| After-create redirect to index/list | standalone Create pages return to Resource index unless documented otherwise | admin/resources | 09 | Admin UX | Use `$this->getResource()::getUrl('index')` |
| After-edit redirect to index/list | standalone Edit pages return to Resource index unless continued editing is intended | admin/resources | 09 | Admin UX | `EditContentItem` may stay because relation manager work continues there |
| Relation manager create/edit location | create/edit stays on owning item edit page | admin/transcriptions | 09 | Admin UX | Relation manager actions do not redirect away from owner context |
| Relation page vs relation manager decision | relation manager now; `ManageRelatedRecords` future optional | admin/transcriptions/viewer | 09, 14 | Admin UX/Future | Dedicated relation page only for larger workspace, sub-navigation, bulk workflows, or studio-style tooling |
| No Repeater for full transcript Markdown | avoid nested Repeater for transcript bodies | admin/transcriptions | 09 | Admin UX | Full Markdown transcript forms are too large for inline nested rows |
| Prompt 09 relation manager tests | cover render/create/edit/filter/featured/owner-scope/tab/redirect behavior | admin/testing | 09 | Testing | Relation manager tests pass owner record and page class to Livewire |
| Prompt 07 post-run verification | Prompt 07 committed and locally migrated | current-state | docs-sync | Planning | Latest inspected commit `dd60315`; Prompt 07 implementation commit `7edb82d` remains in history |
| Prompt 07 migrations applied locally | all three Prompt 07 migrations are `Ran` | current-state/transcriptions | docs-sync, 08 preflight | Planning | Verified by Boost schema/query and `php artisan migrate:status` |
| Prompt 07 focused tests | focused Prompt 07 tests passed | current-state/transcriptions | docs-sync, 08 preflight | Testing | `TranscriptionsModelTest` and `PublicTranscriptionVisibilityTest` passed during post-migration sync |
| Prompt 07 database state verified with Boost | `transcriptions` exists, `featured_transcription_id` exists, legacy item transcript field still exists | current-state/transcriptions | docs-sync, 08 preflight | Planning | Boost `application_info`, `database_schema`, and `database_query` were available and used |
| Prompt 08 completed | taxonomy, tags, pinning, settings, and media foundation implemented | current-state/feature-map | 08 | Domain/Admin foundation | Committed as `b15f5c1 feat: add taxonomy tags pinning settings and media foundation` |
| Prompt 09 completed | admin content management implemented | current-state/feature-map | 09 | Admin UX | Committed as `22e11d0 feat: add phase two admin content management` |
| Admin UX repair completed | post-Prompt-09 admin management UX hardening implemented | current-state/feature-map | repair | Admin UX/Testing | Committed as `16ab33a fix: repair admin management ux after phase two resources` |
| ContentItem edit tab fixed | use `getContentTabLabel(): ?string` for the item details tab label | current-state/admin | repair | Admin UX | Prevents replacing the content tab component and preserves real form fields |
| First transcription auto-featured | first child transcription sets `featured_transcription_id` automatically | current-state/transcriptions | repair, 10 | Domain/Admin | Prompt 10 import tests must account for this model behavior |
| Set-featured action visibility | set-featured action is visible only when useful | current-state/admin | repair | Admin UX | Action is exposed when an item has more than one transcription |
| ContentItem create redirect | create redirects to edit page | current-state/admin | repair | Admin UX | Notification tells admins to add a transcription from the transcriptions tab |
| ContentItemsTable add transcription action | row action creates a child transcription for the selected item | current-state/admin | repair | Admin UX | Keeps transcript creation in item context |
| Associate-existing transcription deferred | belongs-to association would move the transcription from another item | decision/current-state | repair | Admin UX | Deferred unless a future prompt designs an explicit move workflow |
| HomepageSection type-driven configuration | latest/category/tag/content-group sections expose the relevant target fields | homepage/current-state | repair, 11 | Admin/Public | Curated query remains deferred |
| PublicContentSettings stored, public consumption deferred | settings persist in admin/settings table but public pages do not consume them yet | settings/current-state | 08, 11 | Settings/Public UI | Prompt 11 must read `PublicContentSettings` and `HomepageSection` |
| Spatie ContentTag decision accepted | keep `ContentTag` as configured custom Spatie tag model for enabled/moderation metadata | decision/taxonomy | 08, repair, 10 | Domain/Admin/Public | Use `tags` and `taggables`; do not create a custom content item tag pivot |
| Browser-visible admin regression test added | browser test asserts ContentItem edit tabs and core fields are visible | current-state/testing | repair | Testing | `tests/Browser/AdminContentItemBrowserTest.php` protects the content-tab repair |
| Prompt 10 next | import/export is next after state sync and clean quality baseline | feature-map/prompts | 10 | Planning/Quality | Prompt 10 has not started |
| Local data reset caveat | local data may have been reset if `migrate:fresh --seed` or equivalent was used | current-state | docs-sync | Planning | All migrations are batch 1; exact manual command was not observed |
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
| Blueprint contract section in prompts 08-13 | prompts must treat blueprint as implementation contract | tooling/prompts | 08-13 | Tooling | Added to each active implementation prompt |
| Blueprint completion checklist in final reports | final report must classify blueprint requirements | tooling/prompts | 08-13 | Tooling | Use implemented/already existed/deferred/not applicable/blocked |
| Prompt 08 blueprint contract | schema, relationships, casts, settings, tests, validation | blueprint 08 | 08 | Domain foundation | Blueprint governs fields and package-owned foundations |
| Prompt 09 blueprint contract | admin Resources, forms, tables, actions, shared form rules | blueprint 09 | 09 | Admin UX | Shared admin form rules are required |
| Prompt 10 blueprint contract | import/export classes, columns, resolution, failed rows, tests | blueprint 10 | 10 | Import/export | Native Filament import/export remains authoritative |
| Prompt 11 blueprint contract | public Livewire/Table architecture and result cards | blueprint 11 | 11 | Public UI | Cards are `ContentItem` records and reuse item-card component |
| Prompt 12 blueprint contract | parser class, item page rules, media component, viewer tests | blueprint 12 | 12 | Public UI | Parse-only viewer; no player sync |
| Prompt 13 blueprint contract | widget names, metrics, staging, columns, warning modes, links | blueprint 13 | 13 | Dashboard | Implement available metrics and document unavailable schema-dependent metrics |
| Prompt 14 planning-only blueprint contract | future viewer/studio plan only | blueprint 14 | 14 | Planning | No migrations, Resources, Livewire, Blade, or sync/studio implementation |
| Prompt 15 security-audit blueprint contract | audit checklist, findings, low-risk fixes only if allowed | blueprint 15 | 15 | Security audit | No broad refactors or new product features |
| FilaCheck usage | final gate in implementation prompts | tooling | 07-15 | Quality | Full `vendor/bin/filacheck` final |
| FilamentExamples proof | source snippets through MCP search | research | 06 | Research | No separate fetch tool exposed |

## Omission Check

All required subjects are represented: Prompt 07 post-run state, Prompt 08 completion, Prompt 09 completion, post-Prompt-09 admin UX repair completion, homepage UX, filters, categories, Spatie tags, media foundation, media embeds, item page, Prompt 09 admin Resource relation-manager research, `ContentItemResource` transcriptions relation manager, combined content/relation tabs, standalone create/edit redirects, no Repeater for full transcript Markdown, relation page as future optional, Prompt 12 parser/viewer, Prompt 14 studio planning, import/export, dashboards, settings, slug auto-generation, Hebrew/Israel date formatting, technical field helper text, Boost, Blueprint contract usage, Blueprint completion checklists, FilaCheck, browser-visible admin regression coverage, and FilamentExamples source-snippet access proof through `search_examples`.
