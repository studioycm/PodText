# Acceptance Checklist — Bootstrap Slice 0

Use this checklist after all automated tests pass.

## Environment

- [ ] The application starts from documented commands.
- [ ] A fresh database migrates successfully.
- [ ] The demo seeder completes successfully.
- [ ] The frontend production build completes successfully.
- [ ] The database queue worker starts successfully.
- [ ] No worktree or parallel-checkout setup is required.

## Admin authentication

- [ ] A guest visiting `/admin` is redirected or denied.
- [ ] An administrator can sign in.
- [ ] An administrator can sign out.
- [ ] The Public panel remains available to logged-out visitors.

## Authors

- [ ] An administrator can create an author.
- [ ] An administrator can edit an author.
- [ ] The author receives a stable reference key.
- [ ] The author biography accepts Hebrew Markdown.
- [ ] Author list search works.

## Content groups

- [ ] An administrator can create a content group.
- [ ] The default group labels are Podcast/Podcasts.
- [ ] The default item labels are Episode/Episodes.
- [ ] An administrator can replace those labels.
- [ ] An administrator can upload a cover.
- [ ] The description accepts Hebrew Markdown.
- [ ] The original language defaults to Hebrew.
- [ ] Draft/published status can be set.
- [ ] Publication timestamp can be set.
- [ ] Group list search and status filter work.

## Content items

- [ ] An administrator can create a content item under a group.
- [ ] An item inherits the group's item label.
- [ ] An administrator can override the item singular label.
- [ ] An administrator can attach multiple authors.
- [ ] An administrator can add the original media URL.
- [ ] An administrator can add an approved HTTPS embed URL.
- [ ] An unapproved or non-HTTPS embed URL is rejected.
- [ ] An administrator can set duration.
- [ ] An administrator can paste a long multiline Hebrew Markdown transcript.
- [ ] The transcript editor does not upload attachments unless that behavior was explicitly added and tested.
- [ ] Draft/published status can be set.
- [ ] Item list search and filters work.

## Public browse page

- [ ] A logged-out visitor can open `/`.
- [ ] Published groups appear in a responsive grid.
- [ ] Draft groups do not appear.
- [ ] Search by title works.
- [ ] Sorting works.
- [ ] Pagination works.
- [ ] Search/sort query parameters survive refresh.
- [ ] Empty state is understandable.
- [ ] Long titles remain readable.

## Public group page

- [ ] A visitor can open a published group by slug.
- [ ] A draft group URL returns not found.
- [ ] The cover, title, type label, and description appear.
- [ ] Only published items appear.
- [ ] Item sorting works.
- [ ] Author names and duration appear where available.

## Public item page

- [ ] A visitor can open a published item by slug.
- [ ] A draft item URL returns not found.
- [ ] An item under a draft group returns not found.
- [ ] Parent group information appears.
- [ ] The effective item label appears.
- [ ] Authors appear.
- [ ] Description appears safely.
- [ ] Approved media embed renders in an application-owned iframe.
- [ ] Unapproved embed does not render.
- [ ] Original media link remains available.
- [ ] Transcript appears as formatted HTML.
- [ ] Malicious scripts, event attributes, and unsafe links do not execute or appear as executable markup.

## Localization, RTL, and typography

- [ ] Hebrew is the default locale.
- [ ] Hebrew pages use RTL direction.
- [ ] English pages use LTR direction.
- [ ] Interface labels are translated through language files.
- [ ] Hebrew titles and transcript text render correctly.
- [ ] Hebrew diacritics render correctly in the chosen font.
- [ ] Forms remain usable in RTL.
- [ ] Mobile layout remains usable in RTL.

## Author import/export

- [ ] The administrator can download an author example CSV.
- [ ] A UTF-8 author CSV imports successfully.
- [ ] An existing author updates by reference key.
- [ ] A missing reference key is generated for a new row.
- [ ] Invalid rows appear in a failed-row file.
- [ ] Author CSV export works.
- [ ] Author XLSX export works where supported.

## Content group import/export

- [ ] The administrator can download a group example CSV.
- [ ] A group imports with default type labels when those fields are blank.
- [ ] Hebrew Markdown survives import.
- [ ] An existing group updates by reference key.
- [ ] Invalid publication status fails the row.
- [ ] Import does not attempt to fetch remote covers.
- [ ] Group CSV export works.
- [ ] Group XLSX export works where supported.

## Content item import/export

- [ ] The administrator can download an item example CSV.
- [ ] An item resolves its group by group reference key.
- [ ] An item resolves multiple authors separated by `|`.
- [ ] An unresolved group fails the row.
- [ ] An unresolved author fails the row.
- [ ] Multiline transcript Markdown survives import.
- [ ] Existing item updates by reference key.
- [ ] Invalid embed URL fails the row.
- [ ] Valid rows import when another row fails.
- [ ] Failed-row CSV is downloadable.
- [ ] Item CSV export works.
- [ ] Item XLSX export works where supported.
- [ ] Transcript is disabled in the default export column selection.
- [ ] Transcript can be explicitly selected for export.
- [ ] Exported relationship keys can be re-imported.

## Queue and notifications

- [ ] Imports do not remain pending when the queue worker is running.
- [ ] Exports do not remain pending when the queue worker is running.
- [ ] Import completion notification appears in the Admin panel.
- [ ] Export completion notification appears in the Admin panel.
- [ ] Failed-row download link is accessible only to the authorized administrator.
- [ ] Export download link is accessible only to the authorized administrator.

## Quality and scope

- [ ] `php artisan migrate:fresh --seed` passes.
- [ ] `php artisan test` passes.
- [ ] `vendor/bin/pint --test` passes.
- [ ] `npm run build` passes.
- [ ] No `Podcast` model exists.
- [ ] No `Episode` model exists.
- [ ] No Filament Shield or role matrix was added.
- [ ] No approval workflow was added.
- [ ] No provider-driver engine was added.
- [ ] No custom transcription studio was added.
- [ ] No activity/audit/operations subsystem was added.
- [ ] No broad speculative service/repository layer was added.
- [ ] Documentation reflects the final implementation accurately.
