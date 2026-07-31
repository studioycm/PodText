# Local research task — Filament/Livewire hardening (findings 1–4)

**Run this on the Mac, not in a cloud session.** It needs MCP servers that only exist locally:
`laravel-boost` (`search-docs`) and `filament-examples` (`search_examples`). A cloud session has
neither — it only has WebSearch/WebFetch, and both `filamentphp.com` and `livewire.laravel.com`
return HTTP 403 to automated fetches.

Do not change any code while running this task. Research only. The output is a briefing.

---

## Context you need

Installed versions, from `composer.lock`:

| Package | Version |
|---|---|
| `filament/filament` | v5.7.3 |
| `livewire/livewire` | v4.3.3 |
| `laravel/framework` | v13.21.1 |

Two panels:

- **Admin panel** — `app/Providers/Filament/AdminPanelProvider.php`, applies `Authenticate` via
  `->authMiddleware()`. `User::canAccessPanel()` requires panel id `admin` plus
  `hasRoleAtLeast(UserRole::Admin)`.
- **Public panel** — `app/Providers/Filament/PublicPanelProvider.php`, mounted at `->path('')`
  (site root). No `->authMiddleware()`, no `Authenticate`. Ten guest-reachable pages, all
  extending `Filament\Pages\Page`.

---

## Finding 1 — Two Livewire components with no authorization

`app/Livewire/Admin/SettingsImportWizard.php` and `SettingsImportLocksManager.php` extend
`app/Livewire/Admin/SettingsLifecycleSelectionTable.php`, which is a plain `Livewire\Component`.
No `Gate::`, `authorize()`, `abort_if`/`abort_unless`, `policy()`, `can()`, or `mount()` guard in
any of the three. `applyImport()` passes `$user instanceof User ? $user : null` onward, so a null
actor is accepted. `public array $packageArray` has no `#[Locked]`.

Research:

- `search-docs` with queries like `livewire security authorization`, `livewire locked property`,
  `livewire persistent middleware`, `livewire snapshot checksum`.
- Confirm which middleware runs on the global `POST /livewire/update` route, and confirm that a
  Filament panel's `authMiddleware` does **not** run there.
- Establish whether Filament's own Page classes are protected on that route by `CanAuthorizeAccess`
  re-authorizing inside the Livewire lifecycle, and confirm that a bare `Livewire\Component` has no
  equivalent hook.
- `search_examples` for: `livewire component authorization`, `livewire mount authorize`,
  `filament custom livewire admin`, `livewire locked properties`.

Deliverable: the idiomatic Livewire 4 fix — `mount()` authorization, per-action authorization, and
a ruling on whether `#[Locked]` on `$packageArray` is warranted.

---

## Finding 2 — Guest panel exposes Livewire's upload RPC

`RestrictsFileUploadsToSchemaComponents` is applied in five admin files
(`MediaPickerPanel`, `PublicContentSettingsSubjectPage`, `ListMedia`, `ReviewMediaIssues`,
`CreateMedia`) and zero public ones. No `config/livewire.php` exists, so Livewire's default
temporary-upload rules apply. `AppServiceProvider.php:69` pins the temp disk to `local`, rooted at
`storage_path('app/private')`.

Relevant advisory: **CVE-2026-48500**, "Unauthenticated temporary file upload on auth pages",
fixed in 3.3.52 / 4.11.5 / 5.6.5. This app is on 5.7.3, so the patch is present — the open question
is whether the trait is applied automatically to *custom* Page subclasses or must be added by hand.

Research:

- `search-docs` for `filament security`, `restricts file uploads schema components`,
  `livewire temporary file upload rules`, `filament panel guest access`.
- Confirm from the docs whether the trait is opt-in for developer-authored pages.
- Establish the default `temporary_file_upload.rules` when no `config/livewire.php` is published,
  and the full set of documented options (`rules`, `disk`, `directory`, `middleware`,
  `preserve_filenames`).
- `search_examples` for: `filament public panel`, `filament guest panel`, `filament page file
  upload`, `livewire file upload validation`.

Deliverable: exact code to secure the ten guest pages, plus the `config/livewire.php` stanza adding
a MIME allowlist and size cap.

---

## Finding 3 — `'serve' => true` on the `local` disk

```php
'local' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'serve' => true,
    ...
],
```

This is the same directory Livewire writes temporary uploads into.

Research:

- `search-docs` for `filesystem serve local disk`, `temporary url local driver`, `signed url`.
- Determine which route `serve => true` registers, its name and URL pattern, the handling class,
  and exactly when a valid signature is required.
- **Establish whether this is the Laravel 13 skeleton default** or something this app opted into —
  that determines whether turning it off is a revert or a deviation.
- Check advisory **GHSA-crmm-hgp2-wgrp** ("Temporary Signed URL Path Confusion", affects
  `>=13.0.0 <13.12.0`) and assess whether it would have let a written file be served back.
- Check whether anything in the Filament/Curator media setup relies on
  `Storage::disk('local')->temporaryUrl()` before recommending `serve => false`.

---

## Finding 4 — Exact version pins on the authorization packages

```json
"bezhansalleh/filament-shield": "4.2.0",
"spatie/laravel-permission": "7.3.0",
```

Both pinned exactly, so neither can receive a patch release. Meanwhile `filament/filament` is
`~5.0`, an unusually loose constraint for the most security-relevant dependency.

Research:

- Whether any advisory exists for either package at any version (GitHub Security Advisories, GitLab
  advisory DB, FriendsOfPHP/security-advisories). Report honestly if there are none.
- Current latest release of each, and how far behind `4.2.0` / `7.3.0` are.
- Community guidance on exact pins vs caret constraints for application projects, given that
  `composer.lock` already provides reproducibility, and how exact pins interact with
  `composer audit` and Dependabot.
- Whether the shield pin constrains which `filament/filament` versions are resolvable — i.e.
  whether it could have blocked the 5.6.5 security release from arriving via `composer update`.

---

## Output format

One briefing, four sections. Per finding: what the official docs say, what applies to the admin
panel, what applies to the public panel, and the exact fix as code. Cite every claim with a doc
path or URL. Record which MCP tools were actually available and which queries returned nothing —
per `.ai/tooling-quality`, do not claim Boost or FilamentExamples was used if the calls failed.

Do not run `filacheck --fix`. Do not commit. Do not push.
