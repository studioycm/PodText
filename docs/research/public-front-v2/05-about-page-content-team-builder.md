# Public Front v2 Research: About Page Content and Team Builder

## Purpose

Plan a JSON-first About page and team profile content builder for the public site.

## Topic Scope

About page blocks, rich/Markdown content, image upload, team profiles, safe rendering, and admin editing ergonomics.

## Exact Search Terms Used

- Boost: "Filament RichEditor JSON RichContentRenderer sanitize"
- Boost: "Filament MarkdownEditor toolbar uploads accepted file types"
- Boost: "Filament FileUpload accepted file types disk directory visibility max size image editor"
- FilamentExamples MCP: "Filament CMS page builder Builder RichEditor FileUpload team profiles"
- FilamentExamples MCP: "Filament blog CMS frontend theme content builder"
- FilamentExamples MCP: "Filament repeater image profile cards"
- External: "site:laraveldaily.com Filament Repeater Builder RichEditor FileUpload"

## Boost Docs Used

- RichEditor security docs: raw HTML must be sanitized; JSON content can be rendered through `RichContentRenderer`.
- MarkdownEditor docs for toolbar/uploads.
- FileUpload docs: default accepts any file type, so accepted types, max size, disk, directory, and visibility must be explicit.

## FilamentExamples MCP Examples Found

- `v4/full-projects/cms-blog-system-shield/app/Filament/Resources/Posts/Schemas/PostForm.php`: `RichEditor`, grouped form columns, media upload, excerpt action.
- `v4/full-projects/schedule-for-doctors/app/Filament/Resources/Doctors/Schemas/DoctorForm.php`: image upload with `directory('doctors')`, `disk('public')`, searchable relationships.
- `v4/forms/edit-profile-custom-forms/app/Filament/Pages/EditProfile.php`: multiple custom forms on one page.

## Actual Files, Classes, and Snippets Observed

- Local: safe Markdown rendering is already a public-content requirement and used by item/contributor pages.
- Local: public page shell patterns exist under `app/Filament/Public/Pages`.
- Local: public Blade components already handle reusable public cards and safe media.

## GitHub/Source Files Inspected

- LaravelDaily form/layout articles and menu demo public shell. Full FilamentExamples source was not available beyond MCP snippets.

## Pattern To Copy

- Use Builder for heterogeneous page blocks.
- Use Repeater or Builder for team member entries depending on admin ergonomics.
- Use RichEditor JSON only if rendered by a safe content renderer.
- Use FileUpload image constraints every time.

## Pattern To Avoid

- Do not create `AboutPage`, `AboutPageBlock`, or `TeamProfile` models by default.
- Do not store unsanitized HTML.
- Do not confuse team profiles with Laravel teams or tenancy.

## PodText Adaptation Notes

This should be a public Filament Page for "מי אנחנו" backed by settings JSON. Team profiles are content settings, not users, authors, or tenancy records.

## JSON-First Settings Recommendation

Store:

- `about_page.enabled`
- `about_page.title`
- `about_page.content_blocks`
- `about_page.team.enabled`
- `about_page.team.profiles`

Content blocks can include `markdown`, `rich_text_json`, `image`, `callout`, and `team_section`. Team profiles include image path, title, name, description, sort order, and visibility.

## Model/Table Considered

Rejected: `AboutPage`, `AboutPageBlock`, `TeamProfile`. Content is low-volume and edited as site configuration. No independent workflow, relationships, or queries are required in v1.

## Recommended Model/Schema Options

No model. Uploaded image paths live in JSON and files live under a controlled `team/` directory on the configured public disk.

## Recommended Filament Patterns

- Add About section to existing settings page or a dedicated public settings page if the form grows.
- `Filament\Forms\Components\Builder` for content blocks.
- `Filament\Forms\Components\Repeater` with grid/table layout for team profiles; switch to Builder if image+description is cramped.
- `FileUpload::make('image')->image()->directory('team')->disk('public')->visibility('public')->acceptedFileTypes([...])->maxSize(...)`.

## Public Livewire/Blade Implications

Create a public About Page class and Blade view. Render blocks through an `AboutPageConfigReader` and safe block renderer. Use existing safe Markdown renderer where possible and Filament rich content renderer only for JSON rich content.

## Tests

- Guest can view enabled about page.
- Hidden profiles and disabled blocks do not render.
- Markdown/rich text XSS is sanitized.
- FileUpload configuration is present in admin schema smoke tests.
- Missing image paths fall back safely.

## Security Notes

RichEditor HTML is not safe unless sanitized. File uploads must restrict image MIME types and size. JSON cannot contain raw Blade paths, classes, or HTML block passthrough.

## Open Questions

- Should v1 support RichEditor JSON, Markdown only, or both with a per-block toggle?
- Should About page route be hard-coded `/about` while the label is admin-configurable?
- Should team profiles link to real `Author` records later?
