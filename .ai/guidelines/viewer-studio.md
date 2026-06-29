# Viewer and Studio Guideline

## Purpose

Separate Prompt 12 parse-only public viewer work from Prompt 14 future sync/studio planning.

## Preferred architecture

Prompt 12 implements parse-only public viewer behavior. Prompt 14 plans future sync/studio only.

## Do

- Parse timestamps/speakers from `Transcription::transcript_markdown`.
- Keep Markdown canonical.
- Use Alpine/localStorage for show/hide preferences.
- Plan future studio prerequisites before implementation.

## Do not

- Do not implement player sync in Prompt 12.
- Do not implement studio UI in Prompt 14.
- Do not add autosave without failure/recovery design.

## Testing rules

- Parser single-line and multi-line formats.
- Fallback safe Markdown.
- Draft transcription hidden.
- Viewer controls do not persist server state.

## Security rules

- Parser output must be escaped/sanitized.
- Timestamp anchors must not expose unpublished transcripts.

## FilaCheck / FilaCheck Pro notes

- Avoid Blade query work.
- Keep Livewire component state explicit and tested.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields, where present in related admin forms, should auto-generate from title/name fields but allow manual override.
- Technical fields must have helper text, hints, or descriptions in admin forms.
- Date/date-time and timestamp UI should use Hebrew/Israel locale behavior where dates are shown: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available viewer/transcription editorial metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/transcript-viewer-and-studio-future-plan.md`
- `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md`
- `docs/phase-02/blueprints/14-viewer-studio-future-plan-blueprint.md`
- `docs/research/filament-examples-phase-02.md`
