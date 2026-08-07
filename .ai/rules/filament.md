---
paths:
  - 'app/Filament/**'
---

# Filament

## Run FilaCheck only through `composer filacheck`
Never run `vendor/bin/filacheck`, with or without `--fix`. The binary asks
`laravel/agent-detector` whether it is running inside an AI agent and, when the
answer is yes, force-enables `--fix` and rewrites source files unasked. It also
swaps the exit code from "any violation" to "unfixable violations only", so an
agent's gate passes on work a human's gate would fail.

Use `composer filacheck`, which sets the `FILACHECK_DISABLE_AGENT_MODE=1` opt-out.
`composer filacheck -- --dirty` for local iteration; no arguments for the final
gate. `composer filacheck:fix` is the only approved way to write fixes and still
needs explicit approval.

Clearing `CLAUDECODE`/`CLAUDE_CODE` does not disable detection — `AI_AGENT` is read
first and Claude Code exports it too. `tests/Feature/FilacheckAgentModeGuardTest.php`
pins this contract.
