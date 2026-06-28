# Phase 02 Answers Coverage Matrix

Source inputs:

- `phase 02 answers.txt`
- Later chat clarification:
  - Public listings are `ContentItem` records.
  - Transcriptions are child records.
  - Pinning belongs to `ContentItem` only.
  - “Latest transcriptions” means items ordered by effective/main transcription `published_at`.

This file is intended to be saved in the project as:

```text
docs/phase-02/answers-coverage-matrix.md
```

## Coverage Status Legend

- `Spec`: target specification file.
- `Prompt`: target implementation or planning prompt.
- `Phase`: recommended build step.
- `Notes`: short implementation reminder.

## Core Semantics

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Public result unit | `ContentItem` | public-panel-ux | 11 | Public UI | Not `Transcription` cards |
| Transcription model | child model | transcriptions-model | 07 | Domain | Item has many |
| Effective transcription | featured or latest | transcriptions-model | 07 | Domain | One canonical source |
| Featured field | item FK preferred | transcriptions-model | 07 | Domain | `featured_transcription_id` |
| Latest label | item sort | search-and-filters | 11 | Public UI | By effective transcription |
| Pin target | `ContentItem` only | feature-map | 08 | Domain | No transcript pins |
| Hidden items | no published transcript | public-panel-ux | 11 | Public UI | Exclude publicly |
| Multiple transcripts | tabs | public-panel-ux | 12 | Item page | Published only |
| Draft transcripts | hidden | transcriptions-model | 12 | Item page | No public tab |

## Homepage

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Homepage visibility | published only | public-panel-ux | 11 | Public UI | Items only |
| Homepage order | pinned then latest | public-panel-ux | 11 | Public UI | Same list |
| Pinned section | none | public-panel-ux | 11 | Public UI | Combined feed |
| Latest date | effective `published_at` | search-and-filters | 11 | Public UI | From main transcript |
| Group badge | image or initials | public-panel-ux | 11 | Public UI | Blade component |
| Group order | admin controlled | homepage-settings | 08 | Settings | Section use |
| Homepage sections | admin managed | homepage-settings | 11 | Public UI | Page or Resource |
| Latest layout | mixed cards/rows | public-panel-ux | 11 | Public UI | Default layout |
| Max counts | site settings | homepage-settings | 08 | Settings | Spatie Settings |

## Pinning

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Pin model | `ContentItem` | feature-map | 08 | Domain | Item only |
| Manual order | yes | feature-map | 08 | Domain | `pin_order` |
| Expiration | yes | feature-map | 08 | Domain | `pinned_until` |
| Pinned timestamp | yes | feature-map | 08 | Domain | `pinned_at` |
| Filter aware | yes | search-and-filters | 11 | Public UI | Respect filters |
| Display limit | setting | homepage-settings | 08 | Settings | Admin value |
| Admin action | admin-only now | feature-map | 09 | Admin UI | Shield later |
| Bulk pin | plan | feature-map | 09 | Admin UI | Optional action |

## Search Behavior

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Initial results | show immediately | search-and-filters | 11 | Public UI | No empty start |
| Result unit | `ContentItem` | search-and-filters | 11 | Public UI | Card grid |
| Default fields | title/group/tags/categories | search-and-filters | 11 | Public UI | Enabled tags only |
| Transcript search | deferred action | search-and-filters | 11 | Public UI | Not live |
| Advanced fields | later | search-and-filters | 11 | Later | Collapsed |
| Search logging | no | feature-map | 13 | Later | Optional only |

## Search Fields

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Item title | default | search-and-filters | 11 | Public UI | Indexed |
| Group title | default | search-and-filters | 11 | Public UI | Relationship |
| Enabled tags | default | taxonomy-tags | 11 | Public UI | Public only |
| Categories | default | taxonomy-tags | 11 | Public UI | Include inherited |
| Author name | advanced later | search-and-filters | 11 | Later | Checkbox |
| Item description | advanced later | search-and-filters | 11 | Later | Deferred |
| Transcript body | advanced later | search-and-filters | 11 | Later | Explicit action |
| Speaker names | advanced later | viewer-studio | 11 | Later | Parsed data |
| Metadata | advanced later | media-embed | 11 | Later | JSON/search |
| Provider | advanced later | media-embed | 11 | Later | Dropdown |
| Source URL | advanced later | media-embed | 11 | Later | Admin safe |

## Filter UI

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Desktop search | full width | search-and-filters | 11 | Public UI | Top bar |
| Desktop filters | chips/toggles | search-and-filters | 11 | Public UI | Important filters |
| Advanced filters | collapsed | search-and-filters | 11 | Public UI | Optional panel |
| Mobile search | search bar | search-and-filters | 11 | Public UI | Top |
| Mobile filters | drawer | search-and-filters | 11 | Public UI | Responsive |
| Filter mode | deferred | search-and-filters | 11 | Public UI | Apply button |
| Apply action | required | search-and-filters | 11 | Public UI | Deferred filters |
| Clear filters | required | search-and-filters | 11 | Public UI | One click |
| URL state | preferred | search-and-filters | 11 | Public UI | Shareable |
| Result count | required | public-panel-ux | 11 | Public UI | Above results |

## Sorting

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Latest transcription | sort option | search-and-filters | 11 | Public UI | Effective date desc |
| Oldest transcription | sort option | search-and-filters | 11 | Public UI | Effective date asc |
| Title A-Z | sort option | search-and-filters | 11 | Public UI | Item title |
| Title Z-A | sort option | search-and-filters | 11 | Public UI | Item title |
| Duration shortest | sort option | search-and-filters | 11 | Public UI | Seconds asc |
| Duration longest | sort option | search-and-filters | 11 | Public UI | Seconds desc |
| Original newest | sort option | search-and-filters | 11 | Public UI | External date desc |
| Original oldest | sort option | search-and-filters | 11 | Public UI | External date asc |
| Homepage pinned | always first | public-panel-ux | 11 | Homepage | Not search lock |
| Search pinned | user choice | search-and-filters | 11 | Public UI | Sort may disable |

## Categories

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Category model | custom | taxonomy-tags | 08 | Domain | Not Spatie tags |
| Hierarchy | `parent_id` | taxonomy-tags | 08 | Domain | Multi-level |
| Group categories | yes | taxonomy-tags | 08 | Domain | Many-to-many |
| Item categories | optional | taxonomy-tags | 08 | Domain | Many-to-many |
| Inheritance | group + item | taxonomy-tags | 11 | Public UI | Filter/display |
| Descendants | included | taxonomy-tags | 11 | Public UI | Parent filter |
| Category table | `categories` | taxonomy-tags | 08 | Domain | Custom |
| Group pivot | `category_content_group` | taxonomy-tags | 08 | Domain | Pivot |
| Item pivot | `category_content_item` | taxonomy-tags | 08 | Domain | Pivot |
| Landing pages | required | public-panel-ux | 11 | Public UI | Category pages |

## Tags

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Tag package | Spatie tags | taxonomy-tags | 08 | Package | Filament plugin |
| Tag hierarchy | flat | taxonomy-tags | 08 | Domain | No nesting |
| Public tags | enabled only | taxonomy-tags | 11 | Public UI | Hide disabled |
| Volunteer tags | disabled later | taxonomy-tags | Later | Author panel | Future |
| Admin tags | admin-only now | taxonomy-tags | 09 | Admin UI | Shield later |
| Tag scope | `content` | taxonomy-tags | 08 | Security | Avoid unscoped |
| Tag moderation | planned | taxonomy-tags | Later | Moderation | Enabled fields |
| Tag filters | public | search-and-filters | 11 | Public UI | Enabled tags |
| Tag landing | required | public-panel-ux | 11 | Public UI | Tag pages |

## Public Architecture

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Public shell | Filament panel | public-panel-ux | 11 | Public UI | Existing panel |
| Public pages | custom Pages | public-panel-ux | 11 | Public UI | Filament Pages |
| Search/list | Livewire Table | search-and-filters | 11 | Public UI | Filament Table |
| Cards | ViewColumn/Blade | public-panel-ux | 11 | Public UI | Card grid |
| Item page | Blade/Livewire | public-panel-ux | 12 | Item page | Custom |
| Result layout | card grid | public-panel-ux | 11 | Public UI | First version |
| RTL | Hebrew-first | public-panel-ux | 11 | Public UI | Layout safe |
| Accessibility | required | public-panel-ux | 11 | Public UI | Keyboard labels |

## Media and Embeds

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Original URL | store | media-embed | 12 | Media | Required |
| Embed URL | store | media-embed | 12 | Media | Admin paste |
| Raw iframe | avoid | media-embed | 12 | Security | Render controlled |
| Spotify | provider | media-embed | 12 | Media | Allowlist |
| YouTube | provider | media-embed | 12 | Media | Allowlist |
| Apple Podcasts | provider | media-embed | 12 | Media | Allowlist |
| SoundCloud | provider | media-embed | 12 | Media | Allowlist |
| Generic iframe | admin-only | media-embed | 12 | Media | Strict validation |
| Metadata fields | planned | media-embed | 08 | Domain | Store fields |
| Auto extraction | next version | media-embed | Later | Service | Form action |
| Open source | required | public-panel-ux | 12 | Item page | External link |
| Safe iframe | required | media-embed | 12 | Security | HTTPS only |

## Item Page

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Page unit | `ContentItem` | public-panel-ux | 12 | Item page | One item |
| Default transcript | effective | public-panel-ux | 12 | Item page | Main tab |
| Transcript tabs | published only | transcriptions-model | 12 | Item page | Multi transcripts |
| Desktop layout | header/player/text | public-panel-ux | 12 | Item page | Sticky player |
| Mobile layout | sticky player/top | public-panel-ux | 12 | Item page | Transcript below |
| Reading time | show | public-panel-ux | 12 | Item page | Estimate |
| Audio duration | show | public-panel-ux | 12 | Item page | Seconds format |
| Transcript length | show | public-panel-ux | 12 | Item page | Words/chars |
| Author link | show | public-panel-ux | 12 | Item page | Profile |
| Tags/categories | show | public-panel-ux | 12 | Item page | Links |
| Copy item link | show | public-panel-ux | 12 | Item page | Clipboard |
| Share buttons | show | public-panel-ux | 12 | Item page | Basic |
| Empty suggestions | show | public-panel-ux | 12 | Item page | Helpful state |
| Request episode | later | public-panel-ux | Later | Requests | Not now |
| Report correction | later | public-panel-ux | Later | Feedback | Not now |

## Transcript Parser and Viewer

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Timestamp parse | now | viewer-studio | 12 | Item page | If present |
| Speaker parse | now | viewer-studio | 12 | Item page | If present |
| Parser model | `Transcription` | transcriptions-model | 07 | Domain | Not item |
| Canonical content | Markdown | transcriptions-model | 07 | Domain | Parser derived |
| Timestamp format | `[00:01:23] Speaker:` | viewer-studio | 14 | Future | Human-friendly |
| Hide speakers | viewer option | public-panel-ux | 12 | Item page | localStorage |
| Hide timestamps | viewer option | public-panel-ux | 12 | Item page | localStorage |
| Sync viewer | later | viewer-studio | 14 | Future | Not now |
| Highlight current | future | viewer-studio | 14 | Future | If timed |
| Auto-scroll | future | viewer-studio | 14 | Future | If timed |
| Auto-advance | future | viewer-studio | 14 | Future | No timings |

## Transcription Studio

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Studio timing | future | viewer-studio | 14 | Future | Plan only |
| External player | supported | viewer-studio | 14 | Future | Limited control |
| Direct audio | preferred when available | viewer-studio | 14 | Future | Better control |
| Speed control | future | viewer-studio | 14 | Future | Studio |
| Speaker select | future | viewer-studio | 14 | Future | Quick insert |
| Timestamp insert | future | viewer-studio | 14 | Future | Shortcut |
| Speakers | per transcription | transcriptions-model | 07 | Domain | Not global |
| Alpine.js | planned | viewer-studio | 14 | Future | Shortcuts/UI |
| Autosave | later | viewer-studio | 14 | Future | Needs failure flow |

## Permissions

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Admin-only now | yes | feature-map | 09 | Admin UI | Management |
| Moderators | later | feature-map | Later | Shield | Not now |
| Shield | later | feature-map | Later | Auth | Do not install |
| Pin ability | planned | feature-map | Later | Shield | Future gate |
| Tag ability | planned | taxonomy-tags | Later | Shield | Future gate |
| Category ability | planned | taxonomy-tags | Later | Shield | Future gate |
| Media ability | planned | media-embed | Later | Shield | Future gate |
| Featured transcript | planned | transcriptions-model | Later | Shield | Future gate |
| Settings ability | planned | homepage-settings | Later | Shield | Future gate |

## Import and Export

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Native Filament | use | import-export | 10 | Import/export | Importer/Exporter |
| Export scope | all fields | import-export | 10 | Export | Selectable |
| Bulk export | support | import-export | 10 | Export | Bulk action |
| Groups import | support | import-export | 10 | Import | reference_key |
| Items import | support | import-export | 10 | Import | reference_key |
| Authors import | support | import-export | 10 | Import | reference_key |
| Categories import | support | import-export | 10 | Import | slug/path |
| Tags import | support | import-export | 10 | Import | slug/type |
| Transcriptions import | support | import-export | 10 | Import | child model |
| MD/TXT files | optional | import-export | 10 | Import | Creates transcript |
| Numeric IDs | avoid | import-export | 10 | Import | Portable CSV |
| Existing updates | by key | import-export | 10 | Import | Validate |
| Dry run | later | import-export | Later | Import | Plan |

## Dashboards and Metrics

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Published items | widget | dashboard-metrics | 13 | Dashboard | Count |
| Draft items | widget | dashboard-metrics | 13 | Dashboard | Count |
| Pinned items | widget | dashboard-metrics | 13 | Dashboard | Count |
| Multi transcripts | widget | dashboard-metrics | 13 | Dashboard | Count |
| Missing main transcript | widget | dashboard-metrics | 13 | Dashboard | Count |
| Content groups | widget | dashboard-metrics | 13 | Dashboard | Count |
| Authors | widget | dashboard-metrics | 13 | Dashboard | Count |
| Categories | widget | dashboard-metrics | 13 | Dashboard | Count |
| Tags | widget | dashboard-metrics | 13 | Dashboard | Count |
| Recently published | widget | dashboard-metrics | 13 | Dashboard | List |
| Missing embed URL | widget | dashboard-metrics | 13 | Dashboard | Warning |
| Missing transcript | widget | dashboard-metrics | 13 | Dashboard | Warning |
| Items without category | widget | dashboard-metrics | 13 | Dashboard | Warning |
| Transcriptions by author | widget | dashboard-metrics | 13 | Dashboard | Count |

## Public Extras

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| Clear filters | required | search-and-filters | 11 | Public UI | Button |
| Result count | required | public-panel-ux | 11 | Public UI | Above grid |
| Sort dropdown | required | search-and-filters | 11 | Public UI | Options |
| Copy item link | required | public-panel-ux | 12 | Item page | Clipboard |
| Copy timestamp | later | viewer-studio | Later | Viewer | Anchors first |
| Share buttons | required | public-panel-ux | 12 | Item page | Basic |
| Reading time | required | public-panel-ux | 12 | Item page | Estimate |
| Duration | required | public-panel-ux | 12 | Item page | Media field |
| Transcript length | required | public-panel-ux | 12 | Item page | From transcript |
| Original source | required | media-embed | 12 | Item page | External URL |
| Author profiles | required | public-panel-ux | 12 | Public UI | Links |
| Category pages | required | public-panel-ux | 11 | Public UI | Landing |
| Tag pages | required | public-panel-ux | 11 | Public UI | Landing |
| Empty suggestions | required | public-panel-ux | 11 | Public UI | Empty state |
| Request episode | later | public-panel-ux | Later | Requests | Not now |
| Report correction | later | public-panel-ux | Later | Feedback | Not now |

## MCP Research and Planning Output

| Topic | Decision | Spec | Prompt | Phase | Notes |
|---|---|---|---|---|---|
| MCP research | required | research file | 06 | Planning | Use configured MCP |
| Local research file | commit | research file | 06 | Planning | No token |
| Example name | include | research file | 06 | Planning | Required field |
| Why relevant | include | research file | 06 | Planning | Required field |
| Files/classes | include | research file | 06 | Planning | Required field |
| Copy pattern | include | research file | 06 | Planning | Required field |
| Avoid pattern | include | research file | 06 | Planning | Required field |
| Adaptation notes | include | research file | 06 | Planning | Required field |
| Prompt refs | include | research file | 06 | Planning | Required field |
| Prompt pack | create | feature-map | 06 | Planning | Next phases |
| No implementation | required | feature-map | 06 | Planning | Docs only |

## Future Prompt Coverage

| Prompt | Main Subject | Depends On | Commit Mode | Notes |
|---|---|---|---|---|
| 06 | research/specs | phase 01 done | commit docs | MCP required |
| 07 | transcriptions model | research/specs | review first | Domain change |
| 08 | taxonomy/tags/pinning/settings | 07 | review first | Packages later |
| 09 | admin management | 08 | review first | Resources |
| 10 | import/export | 08-09 | review first | Native Filament |
| 11 | homepage/search | 08-09 | review first | Public UI |
| 12 | media/item page | 07-11 | review first | Player/transcript |
| 13 | dashboard metrics | 08-12 | review first | Widgets |
| 14 | viewer/studio plan | 12 | docs only | Future |

## Required Prompt 06 Check

Prompt 06 must require Codex to:

- use the configured `filament-examples` MCP server;
- keep the bearer token out of files;
- inspect current project state;
- write `docs/research/filament-examples-phase-02.md`;
- write all Phase 02 specs;
- write this coverage matrix;
- write Phase 02 guideline files;
- write future implementation prompts;
- run `git diff --check`;
- commit docs only if safe.

## Required Final Validation

Before Phase 02 planning is considered complete, confirm:

- Every row above has a spec.
- Every row above has a prompt.
- Public listing semantics are item-based.
- Pinning is item-only.
- Effective/main transcription sorting is defined.
- Spatie tags and custom categories are separate.
- Media embeds avoid raw iframe HTML.
- Transcript full-text search is deferred.
- Future studio is planned, not implemented.
- Search logging remains out of scope.
- No implementation files changed.
- No secrets are present.
