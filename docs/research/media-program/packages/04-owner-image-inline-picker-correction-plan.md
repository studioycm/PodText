# Package 4 Correction Plan — Inline Owner Picker Tabs

## Approval

- Audit: `LS-20260724-PODTEXT-MEDIA-OWNER-PICKER-CORRECTIONS-01`
- Selected option: `MEDIA-OWNER-CORR-O3-INLINE-PICKER-TABS`
- Status: complete locally; implementation hash is recorded in the correction
  handoff
- Stage 2 approval date: 2026-07-24
- Starting baseline: clean `main` at
  `75249da2c6de7dcdc82cd938d2a722449d87aa47`, 25 commits ahead of
  `origin/main`
- Scope: the approved Package 4 correction and its standalone Media Library
  batch-upload visibility
- Excluded: dependencies, Package 5, broader Storage discovery, production
  and push

## Mini-task 1 — activate the already-committed local schema

Use the separately authorized backup-first local MySQL runbook.

1. Confirm local environment and the `podtext` target without printing
   credentials.
2. Create and verify a recoverable backup.
3. Stop unless the only pending migrations are:
   - `database/settings/2026_07_23_000000_add_media_acquisition_admin_ux_settings.php`;
   - `database/migrations/2026_07_23_000000_create_media_asset_kernel_tables.php`;
   - `database/migrations/2026_07_23_000001_add_media_asset_id_to_media_attachments_table.php`.
4. Apply only those files in migration order.
5. Run the Curator conversion report, apply only after a normal report, then
   rerun the report and verify settlement.

This mini-task activates committed Package 3 work only. It creates no source
diff and performs no media-file mutation.

## Mini-task 2 — test the tab and selection contract

Add failing focused Pest coverage before application changes:

1. the owner action schema contains first/default Replace Image and second
   Details and Effective Image tabs;
2. `modalContent()` no longer owns the detail view;
3. the first tab contains the existing trusted owner identity field and one
   schema-owned `MediaPickerPanel`;
4. inline owner mode has no picker-launch action or picker Close control;
5. inline single gallery choice updates only pending outer action state;
6. cancelling preserves the current attachment;
7. stale replacement, repair and fallback evidence remain protected.

## Mini-task 3 — inline the completed picker

Modify `ContentImageActions` and the smallest reusable picker boundary.

- Build the two schema Tabs in the shared action factory.
- Keep hidden fingerprint, expected Media ID/path and action capability state
  outside presentation-only markup.
- Configure `MediaPickerField` for inline owner presentation while retaining
  its trusted `updateState()` bridge and selected-item summary.
- Mount `MediaPickerPanel` directly as a Filament schema Livewire component
  in the first tab.
- Pass current trusted selected Media IDs, purpose, single owner cardinality
  and inline-owner context.
- Handle `insert-media` on the schema component wrapper by calling the
  existing exposed trusted field method. Do not unmount the outer action.
- Hide only the inline field's launcher and picker-only Close control.
- Move the existing detail Blade view into the second schema tab.
- Keep the configured modal/slide-over choice and existing root footer
  actions.

Affected Package 4 surfaces remain:

1. podcast list;
2. episode list;
3. podcast Episodes relation manager;
4. podcast edit;
5. classic episode edit;
6. episode workspace.

## Mini-task 4 — acquisition-only owner batch upload

Extend `MediaPickerPanel` with a locked inline-owner context.

- Keep `isMultiple=false` for owner selection.
- Allow its Upload FileUpload to accept multiple files only in the approved
  inline-owner context, bounded by the existing upload batch limit and two
  parallel transfers.
- Reuse `MediaAcquisitionManager::acquireUploads()` and current bulk-upload
  authorization.
- For one successful upload, retain the current pending-selection behavior.
- For more than one successful upload, admit every success permanently,
  reload the gallery, preserve single owner cardinality and do not
  automatically select an arbitrary acquired row.
- Use accurate translated success/partial copy telling the operator to choose
  exactly one owner image.
- Preserve partial successes, input cleanup, busy/offline guards, HTTP/image
  validation and permanent-acquisition cancellation semantics.

## Mini-task 5 — expose standalone batch upload clearly

Keep the existing Media Resource implementation and clarify its entry point.

- Give the list header action an explicit translated Upload Images label and
  semantic upload icon.
- Give the create FileUpload explicit translated one-or-many label/helper
  copy.
- Preserve purpose selection, metadata fields, batch limit, authorization,
  partial-success behavior and shared admission.

## Mini-task 6 — focused and browser verification

Focused Pest coverage:

- schema topology and tab order;
- trusted single owner state;
- one-file auto-selection;
- multi-file permanent acquisition without arbitrary owner selection;
- partial batch success;
- standalone Media Resource action/field visibility;
- Storage unconfigured, empty, search-empty, candidate and failure states;
- Hebrew/English key parity;
- existing Package 2/3/4 selection, cancellation, stale-write, fallback,
  authorization and resource regressions.

Browser coverage at wide and narrow viewports in Hebrew and English:

1. one owner action dialog/slide-over;
2. Replace Image is first and shows Gallery/Upload/URL/Storage directly;
3. switching to Details shows the effective image and metadata without
   opening another owner modal;
4. single gallery selection and single acquisition update pending state;
5. multi-file upload adds all successes, asks for one explicit owner choice
   and survives outer cancellation;
6. no nested HTML form, duplicate action-modal partial, focus loss,
   horizontal overflow or JavaScript error;
7. busy/offline and picker-owned child-action behavior remain stable;
8. Storage shows its bounded configured-root candidates or an explicit state,
   not a silent blank area.

Use isolated test databases, `Storage::fake()`, committed image fixtures and
`Http::preventStrayRequests()`. Do not use the local database or real storage
for tests.

## Mini-task 7 — documentation, gates and closeout

Reconcile the requirements registry, master plan, Package 4 active research/
plan, supersession map, Media Program context, current state and ledger. Add a
correction handoff with:

- requirement classification;
- database backup path and non-secret command/results summary;
- files/tests changed;
- every verification command and result;
- numbered manual operator steps;
- exclusions and deferred Storage discovery;
- pending implementation hash.

Run the final gates serially in repository order:

1. requirements/scope/drift sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full `php artisan test` last.

After any later file change, restart from Pint. Then create the canonical
implementation commit and immediately create the docs-only hash-stamp commit.
Do not push.

## Forecast and boundaries

Approved forecast: 13–18 hours, seven sequential mini-tasks.

No new migration or dependency is required. Existing local migrations were
activated only under the separate approval. Package 5 Files Discovery,
physical lifecycle work, dependency updates, production operations and push
remain excluded.
