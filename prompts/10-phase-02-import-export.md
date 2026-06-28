# Prompt 10: Phase 02 Import Export

Work sequentially after Prompt 09 is complete and committed.

Extend the existing Filament-native import/export layer only.

Required:

- import/export transcriptions as child records;
- import/export categories and typed tags;
- import/export pinning fields and media metadata fields;
- preserve reference-key upsert behavior;
- use portable identifiers, not numeric IDs;
- support approved `.md`/`.txt` transcript file references if implemented;
- keep failed-row output and formula-injection protection.

Do not build custom CSV controllers, retry dashboards, or remote media fetching.

Tests must cover create/update imports, relationship resolution, failed rows, transcript imports, category/tag imports, export columns, bulk export, and authorization.

Run the project quality gate required by `AGENTS.md` before committing.
