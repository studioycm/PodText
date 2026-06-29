# Acceptance Checklist — Bootstrap Slice 0

Use this checklist after all automated tests pass.

## Environment

- [ ] The application starts from documented commands.
- [x] A fresh database migrates successfully.
- [x] The demo seeder completes successfully.
- [x] The frontend production build completes successfully.
- [x] The database queue worker starts successfully.
- [x] No worktree or parallel-checkout setup is required.

## Admin authentication

- [x] A guest visiting `/admin` is redirected or denied.
- [ ] An administrator can sign in.
- [ ] An administrator can sign out.
- [x] The Public panel remains available to logged-out visitors.

## Authors

- [x] An administrator can create an author.
- [x] An administrator can edit an author.
- [x] The author receives a stable reference key.
- [x] The author biography accepts Hebrew Markdown.
- [x] Author list search works.

## Content groups

- [x] An administrator can create a content group.
- [x] The default group labels are Podcast/Podcasts.
- [x] The default item labels are Episode/Episodes.
- [x] An administrator can replace those labels.
- [x] An administrator can upload a cover.
- [x] The description accepts Hebrew Markdown.
- [x] The original language defaults to Hebrew.
- [x] Draft/published status can be set.
- [x] Publication timestamp can be set.
- [x] Group list search and status filter work.

## Content items

- [x] An administrator can create a content item under a group.
- [x] An item inherits the group's item label.
- [x] An administrator can override the item singular label.
- [x] An administrator can attach multiple authors.
- [x] An administrator can add the original media URL.
- [x] An administrator can add an approved HTTPS embed URL.
- [x] An unapproved or non-HTTPS embed URL is rejected.
- [x] An administrator can set duration.
- [x] An administrator can paste a long multiline Hebrew Markdown transcript.
- [x] The transcript editor does not upload attachments unless that behavior was explicitly added and tested.
- [x] Draft/published status can be set.
- [x] Item list search and filters work.

## Public browse page

- [x] A logged-out visitor can open `/`.
- [x] Published groups appear in a responsive grid.
- [x] Draft groups do not appear.
- [x] Search by title works.
- [x] Sorting works.
- [x] Pagination works.
- [x] Search/sort query parameters survive refresh.
- [x] Empty state is understandable.
- [x] Long titles remain readable.

## Public group page

- [x] A visitor can open a published group by slug.
- [x] A draft group URL returns not found.
- [x] The cover, title, type label, and description appear.
- [x] Only published items appear.
- [x] Item sorting works.
- [x] Author names and duration appear where available.

## Public item page

- [x] A visitor can open a published item by slug.
- [x] A draft item URL returns not found.
- [x] An item under a draft group returns not found.
- [x] Parent group information appears.
- [x] The effective item label appears.
- [x] Authors appear.
- [x] Description appears safely.
- [x] Approved media embed renders in an application-owned iframe.
- [x] Unapproved embed does not render.
- [x] Original media link remains available.
- [x] Transcript appears as formatted HTML.
- [x] Malicious scripts, event attributes, and unsafe links do not execute or appear as executable markup.

## Localization, RTL, and typography

- [x] Hebrew is the default locale.
- [x] Hebrew pages use RTL direction.
- [x] English pages use LTR direction.
- [x] Interface labels are translated through language files.
- [x] Hebrew titles and transcript text render correctly.
- [x] Hebrew diacritics render correctly in the chosen font.
- [x] Forms remain usable in RTL.
- [x] Mobile layout remains usable in RTL.

## Author import/export

- [ ] The administrator can download an author example CSV.
- [x] A UTF-8 author CSV imports successfully.
- [x] An existing author updates by reference key.
- [x] A missing reference key is generated for a new row.
- [x] Invalid rows appear in a failed-row file.
- [x] Author CSV export works.
- [ ] Author XLSX export works where supported.

## Content group import/export

- [ ] The administrator can download a group example CSV.
- [x] A group imports with default type labels when those fields are blank.
- [x] Hebrew Markdown survives import.
- [x] An existing group updates by reference key.
- [x] Invalid publication status fails the row.
- [x] Import does not attempt to fetch remote covers.
- [x] Group CSV export works.
- [ ] Group XLSX export works where supported.

## Content item import/export

- [ ] The administrator can download an item example CSV.
- [x] An item resolves its group by group reference key.
- [x] An item resolves multiple authors separated by `|`.
- [x] An unresolved group fails the row.
- [x] An unresolved author fails the row.
- [x] Multiline transcript Markdown survives import.
- [x] Existing item updates by reference key.
- [x] Invalid embed URL fails the row.
- [x] Valid rows import when another row fails.
- [ ] Failed-row CSV is downloadable.
- [x] Item CSV export works.
- [ ] Item XLSX export works where supported.
- [x] Transcript is disabled in the default export column selection.
- [ ] Transcript can be explicitly selected for export.
- [x] Exported relationship keys can be re-imported.

## Queue and notifications

- [x] Imports do not remain pending when the queue worker is running.
- [x] Exports do not remain pending when the queue worker is running.
- [ ] Import completion notification appears in the Admin panel.
- [ ] Export completion notification appears in the Admin panel.
- [ ] Failed-row download link is accessible only to the authorized administrator.
- [ ] Export download link is accessible only to the authorized administrator.

## Quality and scope

- [x] `php artisan migrate:fresh --seed` passes.
- [x] `php artisan test` passes.
- [x] `vendor/bin/pint --test` passes.
- [x] `npm run build` passes.
- [x] No `Podcast` model exists.
- [x] No `Episode` model exists.
- [x] No Filament Shield or role matrix was added.
- [x] No approval workflow was added.
- [x] No provider-driver engine was added.
- [x] No custom transcription studio was added.
- [x] No activity/audit/operations subsystem was added.
- [x] No broad speculative service/repository layer was added.
- [x] Documentation reflects the final implementation accurately.
