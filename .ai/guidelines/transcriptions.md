# Transcriptions Guideline

## Purpose

Keep transcript content in `Transcription` child records while public listings remain `ContentItem` based.

## Preferred architecture

`Transcription` child model with canonical Markdown transcript content. Public listings remain `ContentItem` based.

## Do

- Add `ContentItem::transcriptions()`.
- Add `Transcription::author()`.
- Implement effective/main transcription resolution.
- Hide items without an effective/main published transcription.
- Keep parser output derived.
- When admin management is implemented, make transcript body management available from the owning item admin page through a relation manager.
- Keep standalone `TranscriptionResource` useful for global transcript search, filtering, and maintenance.

## Do not

- Do not keep writing new transcript content to legacy `content_items.transcript_markdown`.
- Do not reintroduce the legacy item transcript field in item admin forms.
- Do not expose draft transcriptions publicly.
- Do not pin transcriptions.
- Do not use a Repeater for full transcript Markdown editing.

## Admin management rules

- Item-scoped transcript editing should prefer `ContentItemResource`'s transcriptions relation manager.
- The relation manager should create/edit `Transcription` records in the owning item context and should not expose `content_item_id` as a normal form field.
- Standalone `TranscriptionResource` is for global discovery and maintenance, not the only transcript editing path.
- If transcript management later needs a larger workspace, a dedicated relation page is a future option, not the default Prompt 09 approach.

## Testing rules

- Relationships and casts.
- Backfill.
- Effective/main resolution.
- Same-item featured validation.
- Draft hiding.
- XSS regression.

## Security rules

- Render Markdown through `SafeMarkdownRenderer`.
- Validate featured transcription ownership and publication state.

## FilaCheck / FilaCheck Pro notes

- Enum columns shown in Filament should use label/color contracts.
- Avoid N+1 when listing effective transcription metadata.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields, where present, should auto-generate from title/name fields but allow manual override.
- Technical fields such as reference keys, featured transcription selectors, language codes, parser JSON, and derived counts must have helper text, hints, or descriptions.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available editorial transcription metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/transcriptions-model-spec.md`
- `docs/phase-02/blueprints/07-transcriptions-model-revision-blueprint.md`
- `docs/research/filament-examples-phase-02.md`
