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
