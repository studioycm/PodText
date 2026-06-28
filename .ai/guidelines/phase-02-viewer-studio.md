# Phase 02 Viewer and Studio Guideline

- Parser/viewer work uses `Transcription` records.
- First parser target is `[00:01:23] Speaker: Text`.
- Parser output is derived and must fall back to safe Markdown rendering.
- Viewer local preferences may use Alpine/localStorage.
- Sync viewer and transcription studio are future work unless a later prompt explicitly implements them.
- Do not add autosave, keyboard-heavy editor flows, or studio permissions before the studio phase is approved.
