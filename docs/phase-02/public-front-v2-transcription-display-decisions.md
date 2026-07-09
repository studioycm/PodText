# Public Front v2 Transcription Display Decisions

Created in Step 10R-M4.

These decisions are the default product behavior for the multi-transcription public front until Yoni overrides them in review.

## D1 - Episode Cards

Episode/content-item cards do not render full multi-transcription lists.

Cards show the selected/effective transcription's title, transcribers, read time, word count, and date where the active template asks for those parts. Cards may also show an "N transcriptions" metadata badge when `transcription_policy.count_mode` is `all_published`, the item has more than one public transcription, and the surface `transcription_display` setting is `effective_plus_count`.

## D2 - Item Page

The item page groups by transcription.

The header shows effective transcription transcribers and transcription-count metadata. The transcript viewer lists all public transcriptions as tabs only when `transcription_policy.show_multiple_transcriptions_on_item_page` is true. Each tab shows that transcription's ordered transcriber names.

No merged-across-transcriptions transcriber list is rendered in Step 10R-M4.

## D3 - Contributor Context

Contributor-context episode cards show only transcriptions involving the selected contributor.

This applies to contributor directory previews, contributor detail item grids, and top-transcriber previews. If the contributor-specific transcription differs from the globally effective transcription, the card uses the contributor-specific transcription title/transcribers for transcription-backed parts.

## D4 - Podcast Counts

Podcast/content-group cards default to public episode count plus total read time.

Transcription count and distinct transcriber count are available as template attributes. Podcast detail headers show public episode count, total read time, public transcription count, distinct transcriber count, and latest transcription date when data exists.

All counts follow `transcription_policy.count_mode`.

## D5 - All-Published Scope

`all_published` affects counts, item-page viewer tabs, and the optional card count badge.

It does not turn cards, filters, or listings into multi-transcription lists. Filters match all published transcription transcribers in `all_published` mode and effective transcription transcribers in `featured_only` mode.

## D6 - Per-Surface Settings

Keep settings minimal.

Surfaces that render item cards get one finite token:

- `effective_only`
- `effective_plus_count`

The token is named `transcription_display` and defaults to `effective_only`.

Item-page grouping remains controlled by `transcription_policy.show_multiple_transcriptions_on_item_page`.

## D7 - Template Data

Grouped transcription data in card templates is expressed only through registered finite sources and attributes.

From Step 10R-M5 onward, grouped rows use the validated `part_group` mechanism. Step 10R-M4 only expands finite attributes.

## D8 - Labels And Icons

Label rendering, icon rendering, and nested row/group parts remain Step 10R-M5 scope.

Step 10R-M4 does not partially implement those features.
