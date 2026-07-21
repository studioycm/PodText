# Bounded Dependency Refresh Research

**Date:** 2026-07-21

**Audit:** `LS-20260721-DEPENDENCY-REFRESH-01`

**Approved option:** `DEP-UPGRADE-O1-BOUNDED-FULL-REFRESH`

## Scope

Refresh the complete Composer and npm dependency graphs only as far as the
constraints already declared in `composer.json` and `package.json` allow. The
manifests must remain unchanged. The initial execution result remained local
and uncommitted as requested. After review, the operator explicitly instructed
"then commit", authorizing the canonical local commit closeout. Nothing is
pushed.

This work does not authorize a major-version upgrade, schema or migration
change, production action, local development database probe, cache mutation,
worker restart, or application feature/refactor work outside a compatibility
correction directly required by the approved refresh.

## Baseline

- Checkout: `/Users/studioycm/Herd/PodText`
- Branch: `main`
- HEAD: `996a900c87583ee5c8b6d32329a39a25dd8f2f0b`
- `origin/main`: `7c55dca4012ce48779b32b2e3c4d2076d9198807`
- Ahead/behind: ahead 4, behind 0
- Worktree: clean before Stage 2
- Runtime: PHP 8.4.23, Node.js 22.23.1, npm 10.9.8

## Existing constraints and expected resolution

| Package family | Locked baseline | Expected in-range result | Notes |
| --- | ---: | ---: | --- |
| Laravel framework | 13.19.0 | 13.21.1 | Allowed by `^13.8`; queue, mail, cache, filesystem, and testing changes require regression coverage. |
| Filament and Filament Spatie plugins | 5.6.7 | 5.7.1 | Allowed by `~5.0`; review custom Builder, modal/action, upload/picker, and import/export paths. |
| Livewire | 4.3.3 | 4.3.3 | Already current within `^4.3`. |
| Laravel Horizon | 5.47.2 | 5.48.1 | Allowed by `^5.47`; inspect configuration compatibility without starting or restarting workers. |
| Awcodes Curator | 5.1.2 | 5.1.2 | Already current within the existing constraint. |
| Pest | 4.7.4 | 4.7.5 | Patch refresh only. |
| Laravel Boost | 2.4.11 | 2.4.13 | Patch refresh only. |
| Laravel Socialite | current lock | 5.29.0 | In-range refresh. |
| Resend PHP | current lock | 1.6.0 | In-range refresh. |
| Collision | current lock | 8.9.5 | In-range refresh. |
| Tailwind CSS and Vite integration | 4.3.2 | 4.3.3 | In-range npm refresh. |
| Vite | 8.0.x | 8.1.5 | In-range npm refresh. |
| Laravel Vite plugin | 3.1.x | 3.1.3 | In-range npm refresh. |
| Playwright | 1.61.1 | 1.61.1 | Already current within the manifest. |

The resolver is authoritative. These target versions are the public registry
state observed during the Stage 1 audit; an actual resolution may be lower when
transitive constraints require it.

## Explicit exclusions

- `spatie/laravel-permission` 8 is excluded. Filament Shield 4.2.0 currently
  permits permission 6 or 7, and the manifest pins 7.3.0.
- `concurrently` 10 is excluded by the existing `^9.0.1` constraint.
- No constraint widening, package replacement, or package removal.
- No automated security upgrade that changes a manifest or crosses a major
  boundary.

## Compatibility risks

Filament 5.7 includes changes around cached component discovery, nested
modal/action behavior, uploads, and rendering performance. PodText has custom
Builder previews, slide-over actions, an app-owned Curator picker/resource, and
native Filament import/export flows, so those paths receive focused coverage.

Laravel 13.20-13.21 changes touch framework services used by queues, mail,
caching, filesystems, and testing. The repository's existing queue/import/export
configuration tests and the complete test suite are the primary compatibility
evidence.

Horizon is refreshed only at the package/configuration level. No daemon,
supervisor, worker, or production process action is part of this task.

Composer's Filament upgrade script may republish tracked files beneath
`public/css/filament`, `public/js/filament`, and the Filament font assets. Such
diffs are expected only when they match the resolved Filament packages and pass
the repository gates.

Private Composer repositories may require credentials already configured on the
operator's machine. Credentials must never be displayed or written to tracked
files; missing authentication is a stop condition for operator action.

## Verification contract

1. Validate both resolvers with dry runs and reject major or manifest drift.
2. Refresh Composer and npm graphs without changing manifest constraints.
3. Run Composer and npm security audits without force-fixing across constraints.
4. Inspect the complete diff for migrations, schema/security-boundary drift,
   unexpected application changes, and published-asset scope.
5. Run focused Filament, Livewire, Curator, Card Template, import/export, public
   forms, and settings tests serially.
6. Run the final gates in repository order: requirements sweep, Pint,
   FilaCheck, Vite build, then the complete Laravel test suite last.

## Resolved compatibility findings

- Filament 5.7 renamed the `unmountAction()` boolean parameter. PodText now
  passes positional `false`, preserving the existing one-action-at-a-time
  cleanup behavior without coupling to that parameter name.
- Filament 5.7 dismisses a visible tooltip on the first Escape keypress. The
  Card Template browser regression now sends a second Escape only when the
  preview modal remains open, then verifies focus returns to its opener.
- Composer's standard `filament:upgrade` post-update hook republished the
  registered Filament/Curator asset set. Eighteen tracked core Filament outputs
  changed relative to the starting commit, and all 18 match the installed
  Filament 5.7.1 distribution files byte-for-byte. The hook also cleared local
  framework configuration, route, and compiled view caches. It did not touch
  production state or the development database.
- Composer and npm security audits reported no advisories after resolution.
- npm 10 reports optional WASM helper packages as extraneous on this Darwin
  install even after `npm prune`; the packages are recorded as transitive
  optional dependencies in both npm lockfiles and the dependency command exits
  successfully without invalid peer dependencies.

## Sources

- Laravel framework releases: <https://github.com/laravel/framework/releases/tag/v13.21.1>
- Filament 5.7.0 release: <https://github.com/filamentphp/filament/releases/tag/v5.7.0>
- Filament 5.7.1 release: <https://github.com/filamentphp/filament/releases/tag/v5.7.1>
- Laravel Horizon release: <https://github.com/laravel/horizon/releases/tag/v5.48.1>
- Packagist and npm registry metadata queried during the Stage 1 audit
