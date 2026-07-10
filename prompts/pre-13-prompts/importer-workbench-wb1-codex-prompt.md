# Codex Prompt — Step WB1: Importer Connections Foundation (Workbench Track Opener)

Work in the current local clone of `studioycm/PodText`.

This run OPENS the Importer Workbench parallel track defined in
`docs/phase-02/importer-workbench-plan.md` (commit that plan doc alongside this prompt
if not present). All standing runner rules apply: one implementation step per run, full
quality gate, no push unless asked, no `filacheck --fix`, no `model:show`, fixture-owned
tests, translations en+he, RTL-safe UI.

## Gate preflight

```bash
git status --short --branch
git log --oneline --decorate -20
php artisan migrate:status
```

Confirm: clean tree; Step 10R-S2 AND Step 10R-S1 are complete in the ledger (the WB
track must not open before settings import/export exists — D-WB16). Confirm
`docs/phase-02/importer-workbench-plan.md` exists and read it fully — it is the binding
spec. If S1 is not complete or the plan doc is absent, STOP and report.

## Job 1 — Ledger amendment (docs step of this run)

Add a new section to `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`:
`## Importer Workbench Track` with rows WB1-WB7 (scopes/commits from the plan doc) and
guardrails: "WB opened after S1 per D-WB16; WB runs interleave with P2/P3/AX/SL at
Yoni's per-run selection; main-queue guardrails unaffected; WB never uses Filament
ImportAction/Importer classes; credentials never in tracked files/logs." Refresh the
sequence doc with one line pointing to the WB section. Then implement WB1.

## Job 2 — WB1 implementation

NEW DEPENDENCIES (approved for WB1 only): `google/apiclient`, `laravel/socialite`.
No other new packages.

### Schema

- `import_connections`: id, name, provider (`google_drive|spotify|manual`), auth_type
  (`service_account|oauth|client_credentials|none`), `credentials` (encrypted cast,
  json), `settings` json (default spreadsheet_id, default folder_id, etc.),
  `status`/`last_tested_at`, timestamps. MySQL+SQLite compatible. Factory + seeder-safe
  (no real credentials anywhere; tests use fakes).

### Services (app-owned boundaries, `app/Support/Importer/...`)

- `GoogleDriveConnector`: builds an authorized Google client per connection
  (service-account JSON or OAuth token with refresh); methods: `listSpreadsheetTabs`,
  `readSheetRange` (values by tab + A1 range), `listFolderFiles`, `exportDocMarkdown`
  (Docs export as text/markdown), `downloadFile`. Throttle-aware (sleep/backoff hook).
- `SpotifyConnector`: client-credentials token + `fetchEpisode(spotifyId)` returning
  title/show/duration/release_date/thumbnail. Minimal in WB1 (used by WB7).
- OAuth flow: Socialite Google driver with Drive/Sheets scopes + offline access;
  callback stores refresh token encrypted on the connection; token refresh handled in
  the connector. Redirect/consent setup instructions go in the handoff (Google Cloud
  console: SA creation + key JSON; OAuth consent screen + client), NOT in tracked env.

### Admin UI (Filament, custom — no ImportAction anywhere)

- New nav group "ייבוא" (translated label) positioned after Settings via the existing
  `AdminNavigationOrder` map (extend the map + completeness test).
- `Importer Settings` page: connections management (list, create, edit, delete,
  test-connection button showing tab list / profile ping as proof). PROGRESSIVE
  DISCLOSURE (D-WB2): choosing provider reveals only that provider's fields; choosing
  auth_type reveals only that auth's fields (SA json upload/textarea vs OAuth connect
  button); defaults section collapsed. Reuse V1a upload constraint patterns for any
  file field; helper text on every technical field (house rule).

### Probe command

- `php artisan importer:probe-formats {connection} {--ids=} {--file=} {--limit=20}`:
  fetches the given Google Doc ids (or a JSON/CSV list file) via `exportDocMarkdown`,
  saves raw samples to `storage/app/importer/probe/` (untracked), and writes an
  analysis summary to `docs/research/importer/01-transcript-format-probe.md`
  (tracked): per-doc structure signals (timestamp patterns, speaker-label patterns,
  headings, bold usage, opener/closer blocks), grouped into candidate format profiles
  for WB4. The command must be resumable and throttled. Yoni will supply the 20-doc
  stratified sample list.

### Tests

- Connection model: credentials round-trip encrypted (raw DB value is not plaintext);
  provider/auth validation; settings normalization.
- Connector unit tests with faked Google/Spotify clients (no network): tab listing,
  range reading maps to rows, doc export path, token refresh branch, throttle hook.
- Settings page: progressive disclosure (fields hidden until provider/auth chosen),
  test-connection action wired (faked), nav order includes the new group
  (AdminNavigationOrder completeness test stays green).
- Probe command: with faked connector, writes samples + findings doc skeleton.
- Full gate: `vendor/bin/pint --dirty --format agent`, `php artisan test`,
  `vendor/bin/pint --test`, `vendor/bin/filacheck`, `npm run build`,
  `git diff --check`. Bounded public harness stays green (WB touches no public path).

## Explicit out of scope

Run/step/dataset schema and studio pages (WB2); resolvers/dictionaries (WB3); transcript
fetching at scale + profiles (WB4); apply/journal (WB5); Spotify source step + media
fetch (WB7); any public-front change; any Filament Importer/ImportAction usage.

## Docs and handoff

Update ledger (WB section + WB1 row), `current-project-state.md` (WB track opened; WB1
complete; next WB step WB2; main queue position unchanged), and create
`docs/phase-02/importer-workbench-wb1-handoff.md` with the standard sections PLUS:
`## Google Cloud setup instructions` (SA + OAuth consent, share-with-SA steps for the
sheet and images folder), `## Commit hash`, and `## Local Front Check Report` —
numbered admin checks: open ייבוא group, create a service-account connection with the
real shared sheet, press test and see the tab list including `פרקים שעלו לאוויר`,
verify credentials are not readable in the DB row, run the probe command with the
sample list and open the findings doc. Hebrew RTL + light/dark noted.

## Commit

If and only if all gates pass:

```text
feat: add importer connections foundation
```

Do not push unless explicitly asked. End with exactly:

```text
Importer Workbench mini-step WB1 is complete. Waiting for Yoni review before continuing.
```
