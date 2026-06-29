# Phase 02 Transcript Viewer and Studio Future Plan

## Parser Now

Prompt 12 implements parse-only transcript viewer behavior.

Support:

```text
[00:01:23] Speaker: Transcript text
```

and:

```text
[00:01:23] Speaker:
Transcript text...
```

Parser output is derived from `Transcription::transcript_markdown`. Markdown remains canonical. Parser failure falls back to safe Markdown rendering.

Required parser/viewer tests:

- parse `[00:01:23] Speaker: Transcript text`;
- parse `[00:01:23] Speaker:\nTranscript text...`;
- fallback to safe Markdown if parsing fails;
- render timestamp anchors;
- show/hide timestamp preference;
- show/hide speaker preference;
- confirm no player sync is implemented.
- ensure timestamp displays are direction-safe in Hebrew RTL layout.

## Viewer Now

Prompt 12 may add:

- show/hide timestamps;
- show/hide speakers;
- timestamp anchors;
- local preference storage;
- no player sync.

## Future Plan Only

Prompt 14 plans but does not implement:

- synced public viewer;
- transcription studio;
- embedded external player limitations;
- direct audio URL benefits;
- speed control;
- shortcuts;
- speaker quick insert;
- timestamp injection;
- autosave/failure prerequisites;
- future permissions.

## Blueprint

See `docs/phase-02/blueprints/12-public-item-page-media-parser-blueprint.md` and `docs/phase-02/blueprints/14-viewer-studio-future-plan-blueprint.md`.
