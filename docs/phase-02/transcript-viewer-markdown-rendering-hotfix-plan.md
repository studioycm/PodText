# Transcript Viewer Markdown Rendering Hotfix Plan

## 1. Exact root cause of long transcript truncation

The transcript body is not being truncated by storage or Filament input. The active `transcriptions.transcript_markdown` column is `longText` in `database/migrations/2026_06_29_134855_create_transcriptions_table.php`, and the active Transcription admin form uses `MarkdownEditor::make('transcript_markdown')` without a transcript body `maxLength()`.

The truncation happens during public Markdown rendering:

- `SafeMarkdownRenderer::toHtml()` converts Markdown with `Str::markdown(...)`.
- It then sends the generated HTML through Symfony `HtmlSanitizer`.
- Installed Symfony HtmlSanitizer has a default `maxInputLength` of `20_000` bytes.
- `vendor/symfony/html-sanitizer/HtmlSanitizer.php` truncates input before sanitizing when the configured max is not `-1`.

That byte cap explains the observed cutoff around 11,041 UTF-8 characters / about 2,000 Hebrew words. The amendment also identifies a second long-input risk: `SafeMarkdownRenderer::removeExecutableBlocks()` currently returns `preg_replace(...) ?? ''`, so a PCRE failure on a very long transcript can wipe the whole transcript to an empty string.

## 2. Exact root cause or likely cause of "all in one block"

CommonMark's default renderer treats soft breaks as plain newlines. In browser HTML, those newlines collapse to spaces, so imported transcripts that use single line breaks can appear as one unreadable paragraph unless blank-line paragraphs are present.

Vendor evidence:

- `vendor/league/commonmark/src/Renderer/Inline/NewlineRenderer.php` reads `renderer/soft_break`.
- `vendor/league/commonmark/src/Environment/Environment.php` defaults `renderer.soft_break` to `"\n"`.
- Laravel `Str::markdown()` accepts CommonMark options including `html_input` and `allow_unsafe_links`.

The transcript viewer currently uses the same Markdown path as general public content, so transcript-specific soft-break behavior is missing.

## 3. Current renderer paths

Parsed segment path:

- `ContentItemTranscriptViewer::render()` parses the active transcription through `TranscriptSegmentParser`.
- `resources/views/livewire/public/content-item-transcript-viewer.blade.php` renders each segment with `{!! $renderer->toHtml($segment['markdown']) !!}`.
- This path currently hits the Symfony sanitizer cap and uses default soft breaks.

Fallback full Markdown path:

- When the parser returns no segments, the viewer renders `<x-public.markdown-content :markdown="$activeTranscription->transcript_markdown" />`.
- `resources/views/components/public/markdown-content.blade.php` uses `SafeMarkdownRenderer::toHtml()`.
- This path also hits the Symfony sanitizer cap and uses default soft breaks.

## 4. Current viewer Blade classes

Parsed segment text currently uses an inline class list:

`space-y-4 leading-7 text-gray-700 [&_a]:font-medium [&_a]:text-primary-700 [&_a]:underline [&_a]:underline-offset-4 [&_li]:ms-5 [&_li]:list-disc [&_ol_li]:list-decimal dark:text-gray-300 dark:[&_a]:text-primary-300`

Fallback full Markdown uses `SafeMarkdownRenderer::publicContentClasses()`, which is appropriate for normal public rich content but does not encode transcript-specific soft-break/readability expectations.

## 5. Chosen fix

Add a transcript-specific renderer method:

`SafeMarkdownRenderer::toTranscriptHtml(?string $markdown): string`

The transcript renderer will:

- normalize CRLF/CR to LF and trim only outer whitespace;
- remove executable `<script>` / `<style>` blocks using the existing prefilter but never return `''` on PCRE failure;
- render through `Str::markdown()` with `html_input => 'strip'`, `allow_unsafe_links => false`, and `renderer.soft_break => "<br>\n"`;
- skip the Symfony HtmlSanitizer pass entirely for Markdown output, because raw HTML is stripped before CommonMark output and unsafe Markdown links are blocked by CommonMark;
- preserve the previous HTTPS-only image posture with a narrow app-owned post-filter that removes generated Markdown `<img>` tags whose `src` is not `https://`;
- expose fixed transcript wrapper classes through `SafeMarkdownRenderer::publicTranscriptClasses()`.

General `SafeMarkdownRenderer::toHtml()` will also stop passing CommonMark-generated Markdown HTML through Symfony HtmlSanitizer. This removes the 20,000-byte sanitizer cap from the central Markdown path. Rich HTML content keeps its own sanitizer path in `PublicAboutPageRenderer`; this hotfix does not touch that renderer.

The fallback `withMaxInputLength(-1)` option is not chosen because the primary fix removes the redundant sanitizer pass. Installed Symfony v8.1.1 does support `-1` as unlimited; vendor source verifies `sanitize()` skips substring truncation when `-1 === getMaxInputLength()`.

## 6. Tests to add/update

Add `tests/Feature/SafeMarkdownRendererTest.php` for renderer-level behavior:

- long Hebrew/multibyte transcript over 20,000 bytes renders the final token;
- about 300 KB Hebrew transcript renders non-empty and includes the final token;
- bold, italic, and bold+italic render semantically;
- blank-line paragraphs render as paragraphs;
- single soft breaks render as visible `<br>`;
- XSS matrix: `<script>`, `<style>`, `<iframe>`, inline `onclick`/`onerror`, `[x](javascript:...)`, `![x](javascript:...)`, and HTML entities;
- safe HTTPS links survive;
- non-HTTPS Markdown images are removed.

Update public transcript viewer tests:

- parsed segment Markdown uses the transcript renderer and preserves final long-token output;
- parsed segment Markdown supports styling and visible soft breaks;
- fallback full transcript Markdown uses the transcript renderer and preserves styling, paragraphs, soft breaks, and long final-token output;
- timestamp/speaker controls and direction-safe anchors remain.

Run the existing M4 bounded query-count harness to confirm this hotfix does not regress public query behavior.

## 7. Files to change

- `app/Support/Markdown/SafeMarkdownRenderer.php`
- `resources/views/livewire/public/content-item-transcript-viewer.blade.php`
- `tests/Feature/SafeMarkdownRendererTest.php`
- `tests/Feature/PublicTranscriptRenderingTest.php`
- `tests/Feature/PublicItemPageMediaParserTest.php`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-performance-efficiency-audit.md`

## 8. Out-of-scope list

- Stored `parsed_segments` generation/backfill.
- Parsed segment cache.
- Transcript search.
- Player sync.
- Studio/autosave/timestamp editing.
- Card-template rows, labels, icons, or M5 work.
- Multi-transcriber policy changes.
- Full P3 transcript-rendering economy.
- Prompt 13 dashboard metrics.

## 9. Stop conditions

Stop before coding if the transcript viewer does not use `SafeMarkdownRenderer`; it does use it today in both parsed and fallback paths.

Stop before committing if any focused transcript test, the M4 query-count harness, the full test suite, Pint, FilaCheck, build, or `git diff --check` fails.

## Verified not-causes

- `transcriptions.transcript_markdown` is `longText`, not a fixed varchar/text cap.
- `TranscriptionForm` applies `maxLength()` only to `title` and `language_code`; it does not cap `transcript_markdown`.
- `TranscriptionImporter` maps `transcript_markdown` without a length validation rule.
- The parser preserves long segment bodies; the observed cutoff appears after Markdown conversion/sanitization, not during parsing.
