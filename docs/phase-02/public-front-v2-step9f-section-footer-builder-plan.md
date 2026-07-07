# Public Front v2 Step 9F / 10F Section and Footer Builder Plan

## Purpose

This plan captures the requested richer homepage section and footer-builder work without implementing it during Step 9R. The goal is to keep Step 9R focused on menu/header repairs while preserving a clear path for a JSON-first section/footer builder before demo seed cleanup.

## Recommended Sequence

Recommended order:

1. Step 9R Menu/Header UX fixes.
2. Step 10 Contributors and Top Transcribers UX.
3. Step 9F or Step 10F Footer + Rich Section Builder foundation.
4. Step 11 Seeders, Demo Data, Assets, and Cleanup.
5. Prompt 13 Dashboard Metrics.

Run the footer/section builder after Step 10 and before Step 11. Step 10 may introduce contributor-specific homepage layout needs that should inform the final section-builder schema. Step 11 seeders should then seed stable demo footer/section content only after the schema is settled.

## Smallest Safe Next Step

Build a foundation prompt rather than a full CMS:

- Extend existing `HomepageSection` JSON config and public-front settings.
- Add a constrained `rich_columns` section type.
- Add a constrained `footer_config` JSON settings group.
- Render through app-owned Blade components and existing safe Markdown/RichEditor renderers.
- Do not add public page routing, generic page models, or footer settings-only models.

## Step 10 Update

Public Front v2 Step 10 implemented contributor/top-transcriber UX without adding footer or rich-section builder code. The actual Step 10 needs that should inform Step 9F/10F are:

- top-transcriber homepage sections use a selector plus preview pattern, owned by a focused Livewire component;
- preview grids and contributor item grids use semantic column and gap settings rather than raw classes;
- grouped related content can render one `ContentItem` card with supporting nested metadata below it;
- future rich sections should support semantic grid controls and optional preview-like regions, but should avoid turning every section into a generic CMS surface until there is a second concrete use case;
- the footer/rich section builder should still run after Step 10 and before Step 11 seeders if approved, so Step 11 can seed stable footer/rich-section demo content.

Recommended next decision after Step 10: run Step 9F/10F before Step 11 if footer/rich sections are needed for the demo baseline; otherwise proceed directly to Step 11 and keep this plan as the future foundation contract.

## Post-Step-10R Audit Update

The Step 10R rendering/settings/transcriber audit changes the recommended next implementation order. Step 9F / 10F should wait until the Step 10R implementation pass is complete.

Required Step 10R dependencies:

- a request-scoped `PublicFrontRenderContext` so footer, menu, sections, card templates, public forms, and route labels read one normalized settings snapshot;
- real card-template part rendering for content item, content group, and contributor cards, so rich sections do not inherit compatibility-only card behavior;
- a safe card/layout token map for equal rows, image ratios, title/description clamps, metadata regions, and duplicate group thumbnail policy;
- corrected transcription-author attribution, so contributor and rich-section card surfaces do not repeat item-author/transcriber ambiguity;
- a clear boundary for public renderers to receive prepared data from Livewire/support classes instead of reading settings in Blade.

Updated recommendation:

1. Step 10R-A settings snapshot/render context.
2. Step 10R-B card-template rendering.
3. Step 10R-C attribution and layout consistency.
4. Step 9F / 10F footer plus rich section builder.
5. Step 11 seeders/demo data/assets/cleanup.

Proposed renderer interfaces after Step 10R:

- `PublicFrontRenderContext` exposes normalized settings groups, route labels, public form definitions, and template maps.
- `PublicRichSectionRenderer` receives a `HomepageSection` display config plus the render context and returns safe view data for columns and blocks.
- `PublicFooterRenderer` receives `footer_config` plus the render context and returns safe view data for footer sections, form CTA mounts, and bottom bar.
- `PublicRichBlockRenderer` renders finite block types only: heading, Markdown, RichEditor JSON, smart rich content, link, link group, form CTA, image, and callout.
- Renderer outputs should be arrays/DTOs with semantic tokens already mapped or ready for fixed Blade class maps; Blade should not validate arbitrary JSON.

Schema proposal updates:

- Keep `rich_columns` as a constrained `HomepageSection` source/display type; do not add a generic page builder.
- Keep `footer_config` inside `PublicContentSettings`; do not add `FooterSection` or `PublicFooter` models.
- Add optional semantic layout tokens only if Step 10R-B/C has established the shared names:
  - `height_policy`: `content`, `balanced`, `equal_row`
  - `image_ratio`: `square`, `wide`, `portrait`, `none`
  - `metadata_policy`: `hide_empty`, `reserve_one_line`, `reserve_two_lines`
- Continue to reject raw Tailwind, raw CSS, raw Blade, PHP class names, iframes, scripts, and arbitrary HTML in JSON.
- Footer form sections should mount only enabled Step 6 public forms and should reuse the same form CTA resolver as rich columns.

## Post-Step-10R Livewire/Blade/Support Audit Update

The Livewire/Blade/support-class audit confirmed that Step 9F should wait for Step 10R-A/B/C implementation. The blocker is not data availability; it is the public rendering boundary. Current public pages still read settings through several independent paths, card templates do not yet part-render across all card families, and some Blade views still prepare config/model presentation data directly.

Required Step 10R dependencies before Step 9F:

- `PublicFrontRenderContext` or equivalent request-scoped settings snapshot must replace direct public Blade/settings-reader calls.
- Content item, content group, and contributor card templates must use one controlled renderer, not only `data-card-template-*` compatibility attributes.
- Transcriber attribution must be resolved before rich sections can safely surface item/contributor cards.
- Shared card/grid layout tokens must exist for equal rows, image ratios, title/description clamps, metadata regions, and duplicate group thumbnail behavior.
- Public form CTA resolution must be available through the render context so rich columns and footer CTAs do not duplicate the form modal lookup path.

Renderer interfaces Step 9F should reuse:

- `PublicFrontRenderContext`: normalized settings groups, route labels, form definitions, template maps, page configs, and future footer config.
- `PublicCardPresentationFactory`: card-family view data for item, group, contributor, and any card previews inside rich sections.
- `PublicCardPartRenderer`: finite part renderer for safe card-template output.
- `PublicRichSectionRenderer`: receives a `HomepageSection`, its normalized `display_config`, and the render context; returns safe view data.
- `PublicRichBlockRenderer`: renders only finite block types such as heading, Markdown, RichEditor JSON, smart rich content, link group, form CTA, image, and callout.
- `PublicFooterRenderer`: receives `footer_config` and the render context; returns safe footer sections, form CTA mounts, and bottom-bar data.

Schema proposal refinements:

- `rich_columns` blocks may reference card layouts, but should reference semantic card/template keys rather than copy card rendering JSON.
- `footer_config` should use the same route-label and form CTA resolvers as menu/header/about surfaces.
- Layout tokens should align with Step 10R names where available: `height_policy`, `image_ratio`, `title_lines`, `description_lines`, `metadata_policy`, `grid_columns`, `grid_gap`, and `thumbnail_policy`.
- Keep `rich_columns` and `footer_config` JSON-safe: no raw classes, raw CSS, Blade view names, PHP class names, iframe HTML, script tags, or arbitrary HTML.

Updated sequence remains:

1. Step 10R-A settings snapshot/render context.
2. Step 10R-B card-template rendering.
3. Step 10R-C transcriber attribution and card layout.
4. Step 9F / 10F footer plus rich section builder.
5. Step 11 seeders/demo data/assets/cleanup.

Step 9F should still run before Step 11 if footer/rich sections are needed for the demo baseline; otherwise Step 11 should not seed placeholder footer/rich-section content.

## Homepage Rich Section Requirements

Requested capabilities:

- Section type with layout settings for one or several columns.
- Smart responsive column template settings.
- Admin can add columns.
- Each column has Builder blocks.
- Blocks can include heading, Markdown, RichEditor JSON, smart rich content, links/actions, public form CTA, and compact media/image where allowed.
- Responsive behavior must be semantic, not raw Tailwind classes from JSON.

## Column And Block JSON Proposal

Example `HomepageSection.display_config`:

```json
{
  "heading": "Support public transcription",
  "intro": "Choose an action below.",
  "layout": {
    "column_template": "responsive_2",
    "gap": "comfortable",
    "vertical_alignment": "start"
  },
  "columns": [
    {
      "key": "request",
      "width": "auto",
      "blocks": [
        {
          "type": "heading",
          "text": "Request a transcription",
          "level": "h2"
        },
        {
          "type": "markdown",
          "body": "Ask for a podcast or episode transcription."
        },
        {
          "type": "form_cta",
          "label": "Request transcription",
          "form_key": "request_transcription",
          "display_mode": "modal"
        }
      ]
    }
  ]
}
```

Allowed section layout tokens:

- `single`
- `responsive_2`
- `responsive_3`
- `sidebar_start`
- `sidebar_end`
- `balanced`

Allowed gap tokens:

- `compact`
- `comfortable`
- `spacious`

Allowed block types:

- `heading`
- `markdown`
- `rich_content`
- `smart_rich_content`
- `link`
- `link_group`
- `form_cta`
- `image`
- `callout`

## Footer Manager Requirements

Requested capabilities:

- Footer manager with section builder.
- Footer sections can use the same constrained block/column renderer as homepage rich sections.
- Footer form section can display a configured public form inline or as modal/CTA.
- Bottom bar section supports height/background/content/alignment settings.

## Footer JSON Proposal

Add `public_content.footer_config`:

```json
{
  "enabled": true,
  "sections": [
    {
      "key": "main",
      "visible": true,
      "sort": 10,
      "layout": {
        "column_template": "responsive_3",
        "gap": "comfortable"
      },
      "columns": []
    },
    {
      "key": "form",
      "visible": true,
      "sort": 20,
      "type": "form",
      "form_key": "request_transcription",
      "display_mode": "inline"
    }
  ],
  "bottom_bar": {
    "enabled": true,
    "height": "compact",
    "background": "default",
    "alignment": "between",
    "content": "© PodText"
  }
}
```

Allowed footer form display modes:

- `inline`
- `modal_cta`
- `slide_over_cta`

Allowed bottom bar tokens:

- height: `compact`, `comfortable`
- background: `default`, `muted`, `accent`, `dark`
- alignment: `start`, `center`, `end`, `between`

## Filament Builder / Repeater Approach

- Use the existing `PublicContentSettings` page.
- Add a future `Footer / Sections` tab only when implementing the feature.
- Use `Builder` for column blocks and `Repeater` for columns/sections.
- Keep main settings sections full-width and collapsible.
- Use fieldsets inside repeaters for identity, layout, and block content.
- Offer route/form selectors from existing registries.
- Do not store raw Tailwind classes, Blade paths, PHP classes, arbitrary HTML, SQL fragments, or scripts.

## Public Renderer Approach

- Add focused renderer classes under `App\Support\PublicFront\Sections` and `App\Support\PublicFront\Footer`.
- Use existing `SafeMarkdownRenderer` for Markdown.
- Use the same RichEditor rendering and sanitizer boundary used by the About page.
- Use fixed class maps for column templates, gaps, alignment, image fit/radius, and bottom bar styles.
- Mount Step 6 public forms only for enabled configured forms.
- Keep Alpine limited to local open/close interactions; Livewire owns forms.

## Tests

Future prompt should cover:

- Settings normalization accepts allowed section/footer tokens and rejects raw classes/unsafe values.
- Admin settings page renders full-width tabs/sections and repeaters/builders.
- Public homepage renders rich columns in responsive semantic layouts.
- Selector/preview regions, if generalized, use explicit semantic layout tokens and do not expose arbitrary Livewire classes or Blade paths.
- Related-card grids reuse the Step 10 pattern of semantic columns/gaps and app-owned supporting metadata components.
- Markdown/RichEditor blocks are sanitized and keep H1-H6 hierarchy.
- Link/form CTA blocks resolve only known routes and enabled public forms.
- Footer renders sections and bottom bar when enabled.
- Disabled/footer invalid form targets are skipped server-side.
- No `FooterSection`, `PublicFooter`, `PublicMenu`, `PublicMenuItem`, `Podcast`, or `Episode` model is created.
- No public Filament Tables are introduced.

## Security Rules

- JSON stores semantic tokens only.
- External links must be HTTPS.
- Public form keys must resolve to enabled Step 6 definitions.
- Markdown/RichEditor output must use app-owned sanitization.
- Raw iframe/script/style HTML is rejected.
- Uploaded images must be storage-managed and extension/type constrained.
- Public rendering must preserve existing content visibility rules.

## Out Of Scope For The Future Foundation Prompt

- Generic CMS/page management.
- Arbitrary route creation.
- Public page model/table.
- Analytics/search logging.
- Prompt 13 dashboard widgets.
- Step 2 transcription publication policy.
- Seed/demo content cleanup, except after the feature lands in Step 11.
