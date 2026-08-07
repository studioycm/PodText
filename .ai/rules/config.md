---
paths:
  - config/boost.php
---

# Config

## Re-verify the guideline exclusion after every Boost upgrade
`boost.guidelines.exclude` suppresses the two FilaCheck vendor guidelines, which tell
agents to auto-fix with the raw binary. Boost matches these entries with a strict
`in_array`, so the exclusion is coupled to Boost's internal guideline key names and
fails open — a rename drops the exclusion silently and the unsafe text reappears in
CLAUDE.md on the next `boost:install`.

This already happened once: Boost 2.5 suffixed package guideline keys with `/core`,
which un-excluded both packages. After any Boost upgrade, run `boost:install` and
then `php artisan test --filter=FilacheckAgentModeGuard`. List every spelling you
need; leaving the old keys in place costs nothing.
