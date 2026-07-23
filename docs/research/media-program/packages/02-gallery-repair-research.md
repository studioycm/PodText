# Package 2 Forecast Research — Inventory and Repair

Status: implemented locally under audit
`LS-20260723-PODTEXT-MEDIA-P2-INVENTORY-PICKER-REPLACE-01`, option
`MEDIA-P2-O1-REUSE-PICKER-SAME-PAGE-REPLACE`.

Committed `MediaRecordScope`, the app-owned Resource and picker currently hide
rows based on key, disk/visibility, root, nesting, MIME/extension, filename,
size and dimensions. `MediaAttachmentIdentityResolver` can also veto an
authoritative attachment when its legacy path disagrees.

The corrected direction is to query all Curator rows for admin inventory and
calculate actionable diagnostics separately. Needs Repair remains a filter.
Delivery and public fallback retain only missing row/file, audience access and
unsanitized-inline-SVG boundaries. Attachment `media_id` wins and stale mirrors
are repaired/reported.

The picker starts with a logical context filter but All Media clears it. Rows
that cannot be selected remain visible with an exact reason/action. Existing
selection does not mutate bytes or paths.

The implemented same-page extension reuses the existing Gallery/Upload picker
from podcast and episode list/edit surfaces. The action remains visible when an
image exists, shows the current image, and treats cancellation of staged Gallery
selection as a no-op. The reused picker's explicit Upload command remains an
immediate library write; staging uploads until owner save belongs to Package 3.
Package 3 acquisition, Packages 4-5 expansion/lifecycle and live actions remain
outside this result.
