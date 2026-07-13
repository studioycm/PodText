# Codex Prompt — MAIL1: Mail Foundation + Email OTP Form Verification

Prompt version: v1 — 2026-07-13. (Standing rule, new: every prompt file
carries a version line; if the working tree's prompt differs from the one
Yoni names in the kickoff, stop and ask.)

Work in the current local clone of `studioycm/PodText`.

ONE run: stand up the transactional-mail foundation (Resend-first,
provider-agnostic), add an email OTP verification layer for public form
submissions with a per-form option and a global force flag, harden the
public-forms pipeline, and carry four small items from the FIX1 v2 prompt
race. Standing runner rules: read
`docs/phase-02/ai-development-lessons.md` IN FULL during preflight;
research note + implementation plan docs BEFORE code; no push unless
asked; no `filacheck --fix`; fixture-owned tests; `Mail::fake()` /
`Http::preventStrayRequests()` — no live mail or network in tests; en+he
translations for every new label and mail text.

COMPOSER: exactly ONE package addition is approved — `resend/resend-php`
(plus its own transitive requirements) for Laravel 13's native `resend`
mail transport. Nothing else. No npm changes.

CANONICAL RUN ENDING (standing): implementation commit (code + docs +
handoff, `## Commit hash` pending) → immediately a docs-only commit
stamping the hash into handoff + ledger
(`docs: backfill forms mail mail1 hash`).

Handoff: `docs/phase-02/forms-mail-mail1-handoff.md`, gate outcomes
written in before committing; Local Front Check Report = numbered MANUAL
OPERATOR STEPS (imperative).

FINAL GATE ORDER (standing): requirements sweep → `vendor/bin/pint --test`
→ `vendor/bin/filacheck` → `npm run build` → FULL `php artisan test` LAST
(once = once GREEN on final state; re-enter from Pint after any change;
record every run, including failures).

## Preflight

```bash
git status --short --branch
git log --oneline -5
```

Expect FIX1 `700de7f` + backfill `c66ca3f` at or near HEAD. Stop on
unexpected app-code dirt.

## Job 0 — carried items (from the FIX1 v2 prompt race) + lessons

Context: Fable delivered a v2 of the FIX1 prompt, but v1 was committed and
executed — correctly, per its own contract. These four v2 items carry
here:

1. **Import failure clarity** (Yoni field report: Filament's failed-row
   message is ambiguous). Investigate the current failure surfaces of the
   generic importers (completion notification body + failed-rows CSV
   download) and make causes explicit: the failed-rows CSV must carry the
   actual per-column validation messages; the completion notification
   summarizes distinct failure causes (e.g. "3 rows: missing podcast
   reference key"), he+en. Failed-row download authorization stays exactly
   as is. Test: a deliberately broken row fails WITH its actual cause
   readable in the failed-rows output and reflected in the notification.
2. **Admin navigation last item = public homepage link**, through the
   central navigation map: external URL via the public route helper, new
   tab, enum icon, he+en labels (Hebrew like "לאתר הציבורי"). Test: it
   registers last and points at the homepage URL.
3. **Workspace: the Spotify link + fetch entry moves to be the FIRST
   thing on the episode workspace form** (operator flow: paste link →
   fetch → everything populates). Only the fetch entry point moves; full
   reordering is ADM1's job.
4. **Tiered podcast recognition in the workspace fetch modal**: (a) show
   id match → auto-link when the option is on; (b) exact title match
   (case/whitespace-insensitive) → offered PRE-CHECKED in the modal;
   (c) close-name match (normalized contains / high `similar_text`) → top
   candidate shown UNCHECKED as a suggestion. Never silently link below
   tier (a). The FIX1 resolver already has a title fallback — surface it
   through these modal tiers. Tests per tier.
5. **Lesson** (append to ai-development-lessons.md): prompt files carry a
   version line; when Fable delivers an updated prompt after a version was
   already committed, the kickoff names the version and the runner stops
   on mismatch. Dropped-in-transit requirements are carried by the next
   run's Job 0, never silently lost.

## Job 1 — mail foundation (Resend-first, provider-agnostic)

Decision (Yoni + Fable, researched): **Resend** is the recommended
provider — free tier 3,000/month with 100/day, one verified domain, no
provider branding on emails, and a first-party Laravel 13 transport.
Brevo's free tier (300/day) stamps "Sent with Brevo" on every mail —
wrong look for verification codes. The wiring must still be
provider-agnostic so switching is an env swap.

- Add `resend/resend-php` (the ONE approved package) and configure the
  native `resend` transport per Laravel 13 docs (verify exact mailer
  config via Boost `search-docs` — do not guess keys).
- `.env.example` gets a commented mail block: Option A (recommended)
  `MAIL_MAILER=resend` + `RESEND_KEY=`; Option B (zero-package
  fallback, also documented for Brevo) generic SMTP keys
  (`MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION`) with one-line comments
  for Resend SMTP (`smtp.resend.com`, user `resend`, password = API key)
  and Brevo SMTP. Local default stays `MAIL_MAILER=log`.
- From-address/name from env (`MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
  defaulting to the site name). All OTP mail is QUEUED (existing Horizon
  `default` queue — no Horizon config changes).
- Deploy notes (maintenance/deploy doc): production env keys to set,
  Resend domain verification steps (SPF/DKIM DNS records on
  `podtext.co.il`), and the log-mailer local workflow.
- No mail is sent anywhere in tests: `Mail::fake()` everywhere.

## Job 2 — verification core (channel-extensible, email now)

- Migration `form_verification_codes`: channel (string enum backing:
  `email` now; `phone` later), address, code_hash (never the plain code),
  form key, guest token binding, expires_at, attempts, verified_at,
  consumed_at, timestamps; index on (channel, address, form key).
- `FormVerificationManager` service: `send()` (generates a 6-digit code,
  invalidates prior active codes for the same channel+address+form,
  stores hash, queues the mailable), `verify()` (constant-time hash
  check, expiry 10 minutes, max 5 attempts then the code dies,
  `verified_at` set), `consume()` (single-use at submission). Channel
  contract (interface) with an email driver now — phone/SMS/WhatsApp are
  a later driver, DESIGN the seam only.
- Throttles: 60s resend cooldown per address+form; hourly cap per address
  and per IP (named rate limiters). Localized he/en Markdown mailable:
  prominent code, site name from settings, RTL-correct for Hebrew.
- Unit tests: send/invalidate-previous, verify happy path, wrong code
  attempts exhaust, expiry, cooldown, consume single-use.

## Job 3 — form integration (no unverified path anywhere)

- Form builder (`ManagePublicForms`): per-form option on the submitter
  email field — verification `off | email_otp` — with helper text; plus a
  GLOBAL flag in the same settings area: "require email verification on
  every form that has a submitter email field". Global ON overrides
  per-form OFF. he+en.
- Public Livewire form flow: when required — email entered → "send code"
  action (cooldown-aware feedback) → code input appears → verify → submit
  enabled. The SERVER is the authority: the shared submission pipeline
  (`PublicFormSubmitter`) REFUSES any required-but-unverified submission
  regardless of client state, and `consume()`s the verification at
  submit. Submission records store channel + verified_at; the admin
  submissions table shows a verified badge (he+en).
- Maintenance plain-POST fallback (MP2) must enforce the SAME rule — the
  client uses it today. Two-step plain-POST: a send-code POST route
  (honeypot + throttled, redirects back with a signed token), then the
  form submits with the code; server verifies exactly like the Livewire
  path. No unverified submission path may exist when verification is
  required. Keep the existing honeypot and CSRF/419 grace behavior.
- Tests: Livewire verified path; unverified refused server-side; global
  flag forces per-form-off forms; plain-POST two-step path; badge
  rendering; Mail::fake asserts the localized mailable.

## Job 4 — forms hardening sweep

Research first, then tighten the public submission pipeline: named rate
limiter per IP+form on submissions (and the send-code route); strict
email validation (`email:rfc` — no DNS lookups in validation); sensible
max lengths on all public form fields; oversized-payload rejection;
verify the honeypot still covers both surfaces. Enumerate found gaps in
the research doc; fix the cheap ones; classify anything bigger. Tests for
the throttle and length limits.

## Tests

Per job; existing forms/maintenance/fetcher suites stay green. Full gate
per header order.

## Docs and handoff

Ledger row `MAIL1 - Mail foundation and email OTP form verification`;
`current-project-state.md`; research/plan docs BEFORE code
(`docs/research/forms-mail/00-mail1-research.md`,
`00-mail1-implementation-plan.md` — research includes the current
failure-surface findings for Job 0.1 and the forms-gap enumeration for
Job 4); handoff per header rules. Local Front Check Report (operator
steps): with the log mailer, enable OTP on one form → public form asks
for the code → copy it from `storage/logs/laravel.log` → verify → submit
succeeds; try submitting unverified → refused; flip the global flag →
a form with per-form OFF now requires the code; run the maintenance
fallback two-step flow; import a CSV with one broken row → the actual
cause is readable; check the admin nav ends with the public-homepage
link; open the workspace → Spotify entry is first; fetch an episode from
an existing podcast typed slightly differently → the close-name
suggestion appears unchecked.

Commit: `feat: add mail foundation and email otp form verification`
Then the canonical docs-only backfill commit (see header).

End with exactly:

```text
Forms mail MAIL1 is complete. Waiting for Yoni review before continuing.
```
