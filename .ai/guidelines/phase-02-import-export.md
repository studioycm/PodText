# Phase 02 Import Export Guideline

- Keep Filament-native Importer/Exporter classes.
- Use portable keys instead of numeric database IDs.
- Extend existing reference-key upsert behavior.
- Imports may create/update transcriptions from inline Markdown or approved `.md`/`.txt` file references.
- Imports must not write transcript text to legacy `ContentItem` transcript fields.
- Keep formula-injection protection and failed-row behavior.
- Do not build custom queue dashboards or retry managers.
