# Phase 02 Transcript Viewer and Studio Future Plan

## Parser

Parse timestamps and speakers from `Transcription::transcript_markdown` when present. Markdown remains canonical.

Supported first-pass pattern:

```text
[00:01:23] Speaker: Transcript text
```

Derived parser output may include segment start time, speaker label, and text. Parser failures should fall back to sanitized Markdown rendering.

## Public Viewer

Public viewer options:

- show/hide timestamps
- show/hide speakers
- timestamp anchors
- copy timestamp link later

Viewer preferences can use local browser storage through Alpine because they are not authoritative server state.

## Future Sync Viewer

Defer:

- current-line highlighting
- auto-scroll
- auto-advance
- player-time synchronization

These depend on reliable timing data and player-control support.

## Future Studio

Studio implementation is not part of the Phase 02 docs task. Plan it after the transcription model and public item page are stable.

Future studio subjects:

- direct audio player where available
- external player fallback
- speed control
- keyboard shortcuts
- speaker quick insert
- timestamp insert
- draft/autosave failure handling
- permission gates

## Tests Required Later

- Parser recognizes supported timestamp/speaker lines.
- Parser preserves safe Markdown fallback.
- Viewer hides draft transcriptions.
- Local display toggles do not change server state.
