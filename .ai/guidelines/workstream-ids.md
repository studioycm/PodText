# Workstream ID Naming

## Purpose

A task or step ID must stay unambiguous when it leaves the file that defines it.

## Format

`TOKEN-localid` — `PICKER-M2`, `FRONT-S2`, `SUITE-B4`, `AUTHZ-1c`.

`TOKEN` comes from the table below. `localid` continues that workstream's own
existing sequence; a workstream with no sequence yet starts at `1`.

## Rules

- An ID is issued once and never renamed. Kind (plan, fix, run, finding) and
  status are columns in the owning doc's table, never part of the name.
  Renaming after artifacts exist leaves two live names rather than retiring
  one — preserved logs keep the old label in their filenames.
- A bare ID is fine inside its own owning file. The moment it leaves — another
  doc, a commit subject, a code docblock, a cross-session brief, an agent
  memory file — it must carry its token.
- Nothing existing is retrofitted. An old bare ID resolves by looking up which
  token owns the file it lives in.
- Claim a new token by adding a row below, then run `composer boost:sync`. A
  token not composed into `CLAUDE.md` and `AGENTS.md` is not claimed.

## Tokens

| token | owns |
| --- | --- |
| `AUTHZ` | `docs/phase-02/authz*` |
| `CURATOR` | `docs/research/curator-g1/` |
| `FRONT` | `docs/research/public-front-v2/`, `docs/phase-02/public-front-v2-*` |
| `MEDIA` | `docs/research/media-program/` |
| `MEDIAOPS` | `docs/research/media-operations-ux3/` |
| `PICKER` | `docs/research/images-media/`, `docs/research/media-picker-*` |
| `SETPERF` | `docs/research/settings-performance/` |
| `SUITE` | `docs/research/test-suite/`, `docs/research/test-suite-rethink-notes.md` |

`ARCH1` and `AUTHZ1` are already globally unique. Leave them exactly as they
are and do not reuse those strings.

## What this does not do

This convention is not enforced outside the repo. Session briefs under
`~/.cache/podtext-coord/` are ID-issuing surfaces that no guard here can reach —
the `E0-E6` collision of `0464bbb` was created in one. The only thing governing
an out-of-repo brief is the convention the agent is already carrying.
