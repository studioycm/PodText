# Media Operations UX3 Mini-task 3 Media Issue Review Implementation Plan

> Execute only
> `MEDIA-OPS-UX3-M3-O1-ROUTE-FIRST-ISSUE-REVIEW-NO-RECHECK` from audit
> `LS-20260725-PODTEXT-MEDIA-OPERATIONS-UX3-M3-01`. Stop before the first new
> Media repair mutation, Recheck/Retry, Package 5, and Mini-task 4.

## 1. Execution and command sequence

No Artisan generator, migration, Composer command, npm dependency command,
database/storage/cache probe, branch/worktree command, production command, or
push is authorized or required.

Execute sequentially in the current checkout:

1. run the focused existing Media details/task tests as the clean baseline;
2. add focused Pest feature expectations for the approved outcome;
3. run them and record the expected RED failures;
4. implement the minimum presenter, Resource page, context/query extensions,
   schema summary, Blade views, and translations below;
5. rerun focused tests to GREEN;
6. run affected Media authority, mutation, owner-image, and performance
   regressions;
7. extend and run the Media browser file serially for Hebrew, English, desktop,
   390-pixel, keyboard, focus, context-return, and accessibility proof;
8. capture local screenshots for outcome review without committing generated
   browser artifacts;
9. run PhpStorm inspections on changed PHP files when callable;
10. perform requirements, Laravel Simplifier, forms UX, security, performance,
    localization, and diff reviews;
11. after the final file change, run final gates in mandatory order:
    requirements sweep, `vendor/bin/pint --test`, `vendor/bin/filacheck`,
    `npm run build`, then full serial `php artisan test` last;
12. create the implementation commit with the handoff hash pending;
13. immediately stamp that hash into the handoff and ledger in a docs-only
    commit.

Any post-gate edit restarts at Pint. Never parallelize browser tests or the
full suite.

## 2. Persistence, models, dependencies, and authority

No table, column, index, migration, cast, model event, relationship, setting,
cache, queue, journal, task snapshot, package, or dependency change.

Existing authority remains:

- `App\Models\Media`;
- `App\Models\MediaAttachment`;
- `App\Support\Media\MediaRecordScope`;
- `App\Support\Media\MediaInventoryDiagnostics`;
- `App\Support\Media\PublicMediaDelivery`;
- `App\Support\Media\MediaReferenceFinder`;
- `App\Support\Media\MediaLibraryTaskQuery`;
- `App\Support\Media\MediaLibraryContext`;
- `App\Policies\CuratorMediaPolicy`;
- `ContentGroupResource` and `ContentItemResource` update authorization;
- `AdminMediaFileController`;
- every existing admission, attachment, mutation, and filesystem coordinator.

If implementation reveals a migration, dependency, new ability, new durable
state, new mutation, or materially broader class set, stop for an amended
Stage 1.

## 3. Read-only presenter

### Class

- **Class:** `App\Support\Media\MediaIssueReviewPresenter`
- **Location:** `app/Support/Media/MediaIssueReviewPresenter.php`
- **Dependencies:**
  - `MediaInventoryDiagnostics`
  - `PublicMediaDelivery`
  - `MediaReferenceFinder`
- **Registration:** normal container autowiring; no durable or cross-request
  state

### Stable details projection

`details(Media $media): array` returns only view-ready scalars and arrays:

- stable display identity using the current card fallback order:
  descriptive title, original filename, stored basename, Curator name, then
  `Media #<id>`;
- original and stored filenames;
- reference key;
- disk, directory, path, MIME, extension, dimensions, byte-size display;
- safe preview URL or null;
- existing authorized View URL or null;
- existing authorized Download URL or null;
- exact diagnostic reasons in existing order;
- primary localized reason;
- additional-reason count;
- healthy/attention state.

It calls the existing diagnostic and delivery services. It does not duplicate
their issue decisions.

### Full review projection

`review(Media $media): array` adds:

- one issue card per current reason:
  - stable reason value;
  - localized label;
  - localized cause;
  - localized consequence;
  - localized evidence-limit copy;
  - current relevant technical facts;
- canonical owner entries:
  - type and role;
  - owner key and title;
  - Resource-authorized URL when supported;
  - missing-file route availability;
- unsupported/unresolved direct-attachment count;
- bounded legacy/settings evidence strings from
  `MediaReferenceFinder::nonAttachmentReferencesForMedia()`;
- explicit evidence-source and absence-limit copy;
- explicit no-current-Media-repair blocker whenever current reasons exist;
- file-route non-repair copy.

Owner records are loaded with:

1. one attachment query limited to the current `media_id`;
2. at most one `ContentGroup` query;
3. at most one `ContentItem` query.

Only these route pairs are supported:

- `content_group` plus `MediaAttachmentRole::Cover`;
- `content_item` plus `MediaAttachmentRole::PrimaryImage`.

The presenter calls `ContentGroupResource::canEdit()` or
`ContentItemResource::canEdit()` before emitting a URL. Owner links are
actionable only for the `missing_file` reason and are explicitly labelled as
owner-presentation routes, not Media repair.

The presenter calls the existing Gate abilities before emitting file URLs.
Inline View additionally requires the existing safe preview decision.
Download requires a configured disk and current file existence.

No model instance, callable, untrusted HTML, raw storage URL, mutation token,
or ability name is returned to Blade.

## 4. Context continuation

### Existing class

- **Class:** `App\Support\Media\MediaLibraryContext`
- **Location:** `app/Support/Media/MediaLibraryContext.php`

### New behavior

Extract the exact version-1 validation into one private parser while keeping
the public `fromInput()` contract unchanged. Add:

- `continuationToken(array $state): string`;
- `fromContinuationToken(mixed $token, int $fallbackFocus): array`;
- `continuationParameters(array $state): array`.

The token methods:

- use Laravel's existing `Crypt` service and JSON support;
- encrypt only the same exact normalized eight-key version-1 state;
- decrypt and fully revalidate every task, MIME, reason, search, sort, page,
  and positive integer focus;
- preserve focus only after successful authenticated decryption and complete
  validation;
- catch malformed ciphertext and JSON and fall back to
  All/page 1/current-record focus;
- never accept or emit a raw URL, path, host, route, referrer, action, ability,
  next-record key, or mutation state.

`fromInput()` continues to overwrite focus with the actual current Media key.
Normal Mini-task 2 Library-to-details behavior and tests remain unchanged.
`continuationParameters()` emits one opaque `origin` value and no raw return
URL.

## 5. Deterministic next issue

### Existing class

- **Class:** `App\Support\Media\MediaLibraryTaskQuery`
- **Location:** `app/Support/Media/MediaLibraryTaskQuery.php`

### Method

`nextIssue(Media $current, array $context): ?Media`

Behavior:

1. start from `MediaRecordScope::inventoryQuery()`;
2. parse the already normalized task enum;
3. apply the existing task predicate;
4. apply the allowlisted MIME equality when present;
5. apply the exact existing diagnostic reason when present;
6. otherwise require the exact diagnostic union unless the selected task
   already requires it;
7. apply the same grouped title/name predicate and installed Filament
   split-term/quoted-phrase extraction as the Media Library;
8. derive `asc` only from `created_at:asc`; all other normalized states use
   default `desc`;
9. constrain the tuple after the current `(created_at, id)` in that direction,
   explicitly matching current database null placement:
   - ascending: null timestamps first;
   - descending: null timestamps last;
10. order by `created_at` and then qualified Media key in the same direction;
11. return the first row;
12. never wrap, mutate, persist, cache durably, or use page number as queue
    authority.

The original context is passed unchanged to the next Review URL. Its page and
focus remain the exact Library return context.

## 6. Resource page

### Resource registration

- **Resource:** `App\Filament\Resources\Media\MediaResource`
- **Location:** `app/Filament/Resources/Media/MediaResource.php`
- **Page key:** `review-issues`
- **Route:** `/{record}/review-issues`

### Page class

- **Class:**
  `App\Filament\Resources\Media\Pages\ReviewMediaIssues`
- **Location:**
  `app/Filament/Resources/Media/Pages/ReviewMediaIssues.php`
- **Base:** `Filament\Resources\Pages\Page`
- **Traits:**
  - `InteractsWithRecord`
  - `RestrictsFileUploadsToSchemaComponents`
- **View:**
  `filament.resources.media.pages.review-media-issues`

Mount:

1. resolve the record through `MediaResource`;
2. authorize `MediaResource::canView($record)`;
3. normalize and lock the origin context with
   `fromContinuationToken()`; absent or invalid tokens fall back safely to the
   current record.

Hydration reauthorizes view access.

Page methods:

- localized title, heading, subheading, and breadcrumb;
- `detailsUrl()` for Close, using the encrypted continuation token;
- `mediaLibraryReturnUrl()` using only `indexParameters()` plus the focused
  card fragment;
- `nextIssueUrl()` using `MediaLibraryTaskQuery::nextIssue()` and the unchanged
  origin context;
- `getViewData()` returning:
  - prepared presenter output;
  - Close URL;
  - exact Library return URL;
  - optional Next issue URL;
  - `Heroicon` enum values, including a direction-aware Next icon.

The page defines no form, submit, save, repair, retry, recheck, or generic fix
action.

## 7. Media details hierarchy

### Existing form schema

- **Class:** `App\Filament\Resources\Media\Schemas\MediaForm`
- **Location:** `app/Filament/Resources/Media/Schemas/MediaForm.php`

Prepend an edit-only schema view:

- **View:**
  `filament.resources.media.schemas.media-details-summary`
- **Data:**
  - `MediaIssueReviewPresenter::details($record)`;
  - `EditMedia::issueReviewUrl()`.

The summary renders:

- safe preview or explicit unavailable state;
- stable display identity;
- stored and original filenames;
- reference key and concise file/location facts;
- Ready or Needs attention state;
- primary reason and additional-reason count;
- **Review issues** link only when reasons exist.

Rename the descriptive section in this Resource form to localized **Describe
Media** copy and add an explicit description:

> These fields change title, alternative text, caption, and description only.
> Saving them does not repair stored-file diagnostics or the broken Media
> record.

The fields and save whitelist remain exactly:

- `title`;
- `alt`;
- `caption`;
- `description`.

### Existing edit page

- **Class:** `App\Filament\Resources\Media\Pages\EditMedia`
- **Location:** `app/Filament/Resources/Media/Pages/EditMedia.php`

Add:

- `issueReviewUrl()` using the current record and locked context;
- continuation-token parsing for Review-generated details URLs, with normal
  `fromInput()` as the unchanged fallback;
- unchanged normal Back, Cancel, Save, form fill, and save whitelists.

## 8. Blade views

### Details summary

- **Location:**
  `resources/views/filament/resources/media/schemas/media-details-summary.blade.php`

Use semantic sections/headings and native Filament buttons. Do not query,
authorize, translate dynamic user content, or call storage from Blade.

### Issue Review

- **Location:**
  `resources/views/filament/resources/media/pages/review-media-issues.blade.php`

Layout:

1. identity and preview section;
2. issue summary;
3. one cause/consequence/evidence card per reason;
4. bounded known impact:
   - canonical direct owners;
   - other known compatibility/settings evidence;
   - unsupported/unresolved count;
   - explicit evidence limits;
5. current routes:
   - owner-presentation links only when truthful;
   - safe View/Download file links;
   - explicit non-repair copy;
6. blocker;
7. action row:
   - Close review;
   - Next issue when available, otherwise localized end-of-cohort copy;
   - Return to Media Library.

Owner and View links use a new tab with `noopener noreferrer`; Download remains
a normal authorized download link.

Responsive and accessibility requirements:

- single column by default;
- multi-column only from `md`;
- no fixed-width content that overflows 390 pixels;
- `min-w-0`, wrapping, and LTR technical values;
- semantic headings and lists;
- visible labels for every action;
- logical keyboard order and visible native focus treatment;
- informative image `alt`, or explicit unavailable text;
- Hebrew page direction remains RTL; English remains LTR.

## 9. Reference evidence extension

### Existing class

- **Class:** `App\Support\Media\MediaReferenceFinder`
- **Location:** `app/Support/Media/MediaReferenceFinder.php`

Add:

`nonAttachmentReferencesForMedia(Media $media): array`

It returns unique translated context strings from:

- supported public legacy owner path matches, when the Media disk is public;
- existing settings path or reference-key matches for the bounded settings
  families.

It does not include `media_attachments`, does not emit owner routes, does not
claim completeness, and does not change delete/mutation authority.

Refactor `referencesForMedia()` to reuse this projection while preserving its
existing public-disk behavior and tests.

## 10. Localization

Add matching `he` and `en` keys under a focused
`admin.media_issue_review` namespace for:

- details hierarchy and Describe copy;
- Review route/page headings;
- Ready/attention summary;
- primary/additional issues;
- identity and file facts;
- cause, consequence, and evidence limit for all six reasons;
- bounded impact headings and evidence-source limitations;
- canonical podcast/episode owner labels;
- unsupported/unresolved direct attachment copy;
- owner-route non-repair warning;
- file-route non-repair warning;
- blocker;
- Close, Next issue, Return to Library, and end-of-cohort copy;
- no-current-issues state.

Do not translate user-entered titles, filenames, paths, MIME values, reference
keys, or owner titles.

## 11. Pest feature tests

Add:

- **File:** `tests/Feature/MediaIssueReviewTest.php`

Cover:

1. route registration and existing Media view authorization;
2. stable identity and concise issue summary on details;
3. explicit Review issues URL with canonical origin context;
4. healthy details with no false issue route;
5. all six reason cards with cause, consequence, and technical evidence;
6. metadata copy proving descriptive Save is not repair;
7. descriptive Save changes only four fields and leaves a technical diagnostic
   unresolved;
8. safe View/Download visibility and unavailable-file omission;
9. canonical group/item owner route authorization and exact Resource URLs;
10. owner-route copy proving presentation-only scope;
11. legacy/settings evidence separated from attachment authority;
12. zero known evidence never called unused or deletion-safe;
13. explicit blocker and absence of Fix/Recheck/Retry;
14. exact origin context through Details → Review → Next → Return;
15. Close to the current details page while retaining original Library focus;
16. malformed continuation fallback;
17. deterministic next issue for task, MIME, reason, search, sort, and
    same-timestamp key tie;
18. no-wrap end state;
19. GET/render remains mutation-free;
20. bounded owner queries across increasing attachment counts.

Update existing focused tests only where shared Mini-task 2 invariants need a
direct regression.

## 12. Browser coverage and visual evidence

Extend:

- `tests/Browser/MediaResourceGalleryBrowserTest.php`

Run serially and prove:

- Hebrew RTL Library → Details → Review issues;
- English LTR Review issues;
- 1280-pixel and 390-pixel layouts have no horizontal overflow;
- stable identity, all issue explanations, impact limits, blocker, and visible
  actions;
- owner/file links are labelled and do not masquerade as repair;
- keyboard focus can reach Close, Next, and Return;
- Next issue remains in the same task cohort;
- Return reconstructs task/filter/search/sort/page and originating card focus;
- no Recheck, Retry, generic Fix, or Package 5 control;
- no smoke, console, JavaScript, or serious accessibility issue.

Capture Hebrew desktop, Hebrew 390-pixel, and English desktop screenshots in
the ignored browser screenshot directory for the final outcome review.

## 13. Ordered quality gates

After the final implementation/doc change:

1. Requirements sweep against every item in this plan and the binding R4
   outcome.
2. `vendor/bin/pint --test`
3. `vendor/bin/filacheck`
4. `npm run build`
5. full serial `php artisan test`

FilaCheck `--fix` is not authorized.

## 14. Documentation and canonical commits

Before the implementation commit:

- add
  `docs/phase-02/media-operations-ux3-mini3-media-issue-review-handoff.md`;
- update `docs/phase-02/current-project-state.md`;
- update the head row of
  `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`;
- classify every meaningful requirement as Implemented, Already existed,
  Deferred, Not applicable, or Blocked;
- record every command and failure;
- include numbered imperative Local Front Check steps;
- leave `## Commit hash` pending.

Then:

1. create one local implementation commit with an imperative allowed prefix;
2. stamp its exact hash into the handoff and ledger;
3. create the immediate docs-only commit:
   `docs: backfill Media Operations UX3 Mini-task 3 hash`;
4. confirm only the six pre-existing operator-owned untracked skill paths
   remain;
5. do not push.
