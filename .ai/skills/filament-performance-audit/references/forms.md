# Forms, Selects, Repeaters, and Uploads

## Large Option Lists

Static option arrays are fine for small enums and fixed choices. They become a performance and usability problem when they represent database-sized data.

Flag:

- `Select`, `CheckboxList`, or `Radio` with large inline arrays.
- `options()` closures that query on every hydration or render.
- Relationship selects without `searchable()` when the related table can grow.
- `preload()` on relationship selects backed by large tables.

Recommended fixes:

- Use relationship-backed `Select::make(...)->relationship(...)->searchable()`.
- Use `preload()` only for genuinely small datasets.
- Limit searchable fields to useful columns, for example `->searchable(['name', 'email'])`.
- Cache fixed option lists with `Cache::remember()` or `once()` when values rarely change.

## Reactive and Livewire Hydration Cost

Filament forms run inside Livewire. Large state trees and overly eager updates can dominate response time.

Flag:

- Many fields using `live()` when only blur-level updates are needed.
- Text inputs with `live()` instead of `live(onBlur: true)` for slug or derived-field updates.
- Nested repeaters with relationship data and many fields per item.
- Expensive `visible()`, `hidden()`, `disabled()`, `options()`, validation, or label callbacks.
- Large schemas whose measured HTML or serialized state grows with every field,
  Builder block, Repeater row, or hidden branch.

Recommended fixes:

- Use `live(onBlur: true)` for text fields unless per-keystroke behavior is required.
- Move repeated expensive lookups outside callbacks or cache them per request.
- Use Sections or Tabs for UX organization, but do not claim they reduce schema
  construction or Livewire state unless the implementation actually mounts or
  renders less and measurements prove it.
- Prefer focused pages, modal editors, or selected-item editors when a whole-list
  Builder/Repeater causes measured state or DOM growth.
- Avoid unnecessary relationship repeater nesting for large child collections.

## File Uploads

File uploads have both performance and safety costs.

Flag:

- Missing `acceptedFileTypes()` when uploads should be constrained.
- Missing `maxSize()` for uploads.
- Image processing or transformations in the request path when they should be queued.
- Public visibility assumptions. Filament file visibility is private unless configured otherwise.

Recommended fixes:

- Add explicit accepted MIME types and max size.
- Queue heavy image processing or conversion work.
- Use public visibility only when public access is actually required.
- On public Livewire components using Filament schemas, check whether file-upload
  RPC methods should be restricted to schema upload components.

## Form Findings

When reporting a form issue, include whether the risk is initial render cost, hydration payload size, query repetition, user searchability, upload safety, or all of these.
