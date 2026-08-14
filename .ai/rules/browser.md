---
paths:
  - 'tests/Browser/**'
---

# Browser

## Express a browser precondition as an assertion, never as a delay
pest-plugin-browser ships 64 auto-waiting assertions, and AwaitableWebpage::__call retries every one through Execution::waitForExpectation for the whole browser timeout. So an assertion fails fast and NAMES itself, while a blind ->wait(N) in front of an action does not fail fast: it burns the full 30s and reports "Timeout 30000ms exceeded", naming neither the action nor the element. That is R4 row 8 canary 4's entire failure mode (reproduced 2026-08-14 at 64 spinners on 8 cores, both datasets). Substitutions: navigation -> assertPathIs/assertUrlIs/assertRoute; visibility -> assertVisible/assertPresent/assertMissing; text -> assertSee/assertSeeIn; query string -> assertQueryStringHas; arbitrary JS -> assertScript; form state -> assertValue/assertChecked/assertSelected/assertDisabled. Rewriting waits as assertions took one canary set from 169 to 197 counted assertions for the same steps.

COROLLARY, which cost three wrong conditions in one session: a wait condition that does not name the expected VALUE is satisfied by the state you are waiting to leave. After a reset-view click, `search === '' && hash === ''` returned while Livewire had not re-rendered the tab strip; adding `.fi-tabs-item.fi-active` still passed against the STALE strip highlighting the previous tab. Only naming the tab worked: `querySelectorAll('.fi-tabs-item')[0]?.classList.contains('fi-active')`.

CARVE-OUT: keep a ->wait() only where nothing observable expresses the settle, and say why at the site. The test is: if you can name the state you are waiting for, it is an assertion; if you genuinely cannot, it is a wait. (A viewport resize is the case that passes this test today; the next person's hard case will not be a viewport resize, so use the principle, not the example.)

Also: never use ->script(...); use ->page()->evaluate(...). script() routes through a retry wrapper with a hardcoded 1s per-attempt cap (pestphp/pest#1852) which silently re-runs page scripts and returns measurements from a mutated page.
