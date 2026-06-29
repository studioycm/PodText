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

## Do not

- Do not keep writing new transcript content to legacy `content_items.transcript_markdown`.
- Do not expose draft transcriptions publicly.
- Do not pin transcriptions.

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

## Related active docs

- `docs/phase-02/transcriptions-model-spec.md`
- `docs/phase-02/blueprints/07-transcriptions-model-revision-blueprint.md`
- `docs/research/filament-examples-phase-02.md`
