# Livewire 4 Performance

## Public State and Hydration

Public properties are serialized between requests. Flag large collections,
whole settings payloads, duplicated option maps, and state that can be derived
from stable scalar identities. Protected properties are not sent to the client
but do not persist between requests.

Computed properties memoize within one request by default. `persist: true` and
`cache: true` cross request or component boundaries; require explicit expiry,
keys, tenant/user scope, invalidation, and stale-data analysis before suggesting
them.

## Update Frequency

Livewire 4 can run live updates in parallel. Prefer `live(onBlur: true)` or a
change boundary for text fields unless per-keystroke behavior is required. When
parallel requests can mutate shared state, review ordering and last-response
behavior instead of assuming sequential saves.

Polling is non-blocking in Livewire 4 but still produces server and network
load. Disable or lengthen it when freshness does not justify the cost.

## Islands, Lazy, and Deferred Loading

Islands isolate independently updateable regions. Lazy islands/components load
when visible; deferred ones load after the initial page. Multiple lazy/deferred
components run independently unless deliberately bundled.

Recommend these only when measurement identifies an expensive, sufficiently
independent region. Count initial and aggregate requests/bytes and watch for
state races. Islands cannot depend on parent template loop/conditional locals,
and moving work to another request is not the same as removing work.

For Filament-managed pages, verify that the schema/component integration
supports the chosen boundary before proposing an island as a generic fix.

## Browser Evidence

Livewire component-test HTML does not include all hydrated browser behavior or
teleported modal DOM. Use browser tests or a controlled browser profile for DOM,
listeners, heap, console, navigation, modal, and Back-warning claims. Keep
wall-clock caps on a fixed runner and preserve deterministic component/query
caps in the ordinary suite.
