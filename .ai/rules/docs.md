---
paths:
  - 'docs/**'
---

# Docs

## Editing a doc's status claim re-verifies its siblings
Before committing an edit that closes or ships any claim a doc makes, sweep that WHOLE doc's present-tense status assertions (not dated measurements, not history, not analysis) and re-verify each against HEAD: still-true → leave; falsified → fix in place citing the proving commit (strike-through + "since closed (SHA)", never silent rewrites of history); unverifiable → register for the Documentation Architecture audit. A doc's status claims were verified against one tree snapshot — falsifying one means the rest carry equally old verification. Proven 3× on 2026-08-11 (the gitignore proposal echoed at three sites; the retired pin-chain claim; the register's own stale copy).

## Living cross-session ledgers: rolling custodian + evidence-gated writes
Applies to living cross-session ledgers (defect-cause-patterns.md, open-findings-triage.md, consolidated-open-findings.md and their kind) — not to ordinary docs. Ownership follows freshest knowledge, not creation: whoever last performs a sanctioned merge IS the current custodian, recorded in the file's header at each merge. EVERY write, custodian's included, passes the same gate: evidence attached, narrow pathspec, independent session re-audits the diff. A dormant custodian gets a merge-or-transfer deadline via wake message; silence past it transfers custodianship to the evidenced requester, recorded in the file. Replaces one-owner-forever (operator decision 2026-08-11, after defect-cause-patterns.md sat stale a week under a dormant creator-owner).
