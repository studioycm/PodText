---
paths:
  - 'tests/**'
---

# Tests

## expectsOutputToContain matches one needle per output line
Laravel's PendingCommand registers a Mockery expectation per needle against doWrite, and the FIRST matching expectation consumes the call. Two expectsOutputToContain() needles that both match the SAME printed line therefore fail — the second is reported as "Output does not contain X" even though it plainly was printed. Assert one needle per line, or split across tests. Cost a debug cycle on 2026-08-12 (lane run-lock work); the command output was correct the whole time.
