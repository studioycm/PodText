# Package 2 Forecast Research — Inventory and Repair

Status: future-only; fresh Simplifier audit required after Package 1.

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
