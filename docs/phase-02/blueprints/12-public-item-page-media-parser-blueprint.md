# Prompt 12 Blueprint: Public Item Page, Media, and Parser

## Commands

- `php artisan make:class Support/Transcripts/TranscriptSegmentParser --no-interaction`
- `php artisan make:test --pest PublicItemPageMediaParserTest --no-interaction`

## Item Page

Update `App\Filament\Public\Pages\ShowContentItem`.

## Prior Prompt Baseline

Prompt 12 requires the post-Prompt-11 public homepage/search baseline; verify prompt state in `docs/phase-02/current-project-state.md`. Preserve Prompt 10 import/export behavior, Prompt 11 public listing behavior, Prompt 11R custom Livewire + Blade public listing UI, and Prompt 11B contributor discovery routes/sections if present while implementing the item page, media component, and parse-only viewer.

Guardrails:

- reuse existing public card and link conventions where the item page surfaces related content;
- preserve existing author/contributor links where they are available;
- do not modify the public homepage/search implementation except for shared components required by the item page;
- do not implement admin relationship UX, contributor directory/discovery, dashboard widgets, studio, or sync behavior.

Rules:

- resolve published group;
- resolve published item;
- require effective/main published transcription;
- load other published transcriptions;
- draft transcriptions hidden.

Layout defaults:

- Desktop: header/meta at the top, sticky player in a side/top area, transcript in a readable main column.
- Mobile: sticky player at the top, transcript below.
- Public date/date-time displays use Hebrew/Israel day-first formatting and `Asia/Jerusalem` UI timezone.

## Media Component

Update existing Blade component `resources/views/components/public/media-embed.blade.php`.

Rules:

- render iframe only from allowlisted HTTPS `embed_url`;
- fallback to `media_url`;
- never render raw embed HTML;
- show provider/source metadata where available.

## Parser

Class: `App\Support\Transcripts\TranscriptSegmentParser`.

Input: Markdown string.

Supported patterns:

```text
[00:01:23] Speaker: Transcript text
[00:01:23] Speaker:
Transcript text...
```

Output: array of segments with:

- `seconds`
- `timestamp`
- `speaker`
- `markdown`
- `anchor`

Fallback: safe Markdown output when no parseable segments.

## Viewer

Use Blade and Alpine for local-only controls:

- hide/show timestamps;
- hide/show speakers;
- timestamp anchors;
- copy link/share feedback.

No player sync.

Copy link to timestamp, request this episode, and report correction are later work unless explicitly added by a future prompt.

## Tests

- Approved embed rendered.
- Rejected embed fallback.
- Draft/no-effective transcript item returns not found.
- Effective transcription default.
- Other published transcription tabs/selector.
- Parser single-line and multi-line patterns.
- Parse `[00:01:23] Speaker: Transcript text`.
- Parse `[00:01:23] Speaker:\nTranscript text...`.
- Fallback to safe Markdown if parsing fails.
- Render timestamp anchors.
- Show/hide timestamp preference.
- Show/hide speaker preference.
- Confirm no player sync is implemented.
- Timestamp displays are direction-safe in Hebrew RTL layout.
- XSS safe transcript rendering.
- Viewer controls render with RTL-safe markup.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Prompt 06S Section Alignment

This alignment block preserves the implementation scope above while exposing the exact headings required by the active AI-context prompt.

## Goal

Implement only the prompt-specific objective described in this blueprint title and body.

## Dependencies

Complete prior prompts in sequence and read `AGENTS.md`, relevant specs, durable guidelines, and this blueprint before implementation.

## Models and migrations

Use the model and schema notes above. If this prompt is documentation-only, do not create migrations.

## Relationships and casts

Use the relationship, cast, and enum notes above; keep public visibility rules queryable and tested.

## Indexes and constraints

Add indexes, unique constraints, and foreign keys only for fields created in this prompt and queries described above.

## Filament Resources / Pages / Relation Managers / Actions

Use Filament 5 Resources, Pages, Actions, Importers, Exporters, or Widgets only where this prompt scope requires them.

## Public UI / Livewire / Blade where relevant

Use public Filament Pages, class-based Livewire, Blade components, and local Alpine only where this prompt scope requires public UI.

## Forms / tables / filters / actions

Use full Filament component namespaces, searchable relationship selects, useful filters, indicators, and Resource URL helpers.

## Import/export where relevant

Use native Filament import/export only for schema fields created by earlier prompts; never build custom CSV controllers.

## Settings/widgets where relevant

Use approved Spatie Settings for global options and simple editorial widgets only where this prompt scope requires them.

## Security

Preserve admin-only access, public draft hiding, safe Markdown rendering, HTTPS allowlisted embeds, and import formula protection.

## Out of scope

Do not implement work assigned to later prompts, install unrelated packages, run migrations in planning tasks, or add speculative infrastructure.
