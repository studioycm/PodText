# Codex Prompt — MAIL2: Inline Email-Verification UX (micro-run)

Prompt version: v1 — 2026-07-14. Standing rule: stop and ask on a version
mismatch with the kickoff.

Work in the current local clone of `studioycm/PodText`.

ONE small run: re-place the MAIL1 email-verification UI so it lives WITH
the email field instead of elsewhere in the form. Yoni's spec, verbatim
in spirit: the verification belongs to the email field — filling the
email opens the verification UNDER the email input; the email field gets
a "send verification code" SUFFIX action; the code field sits
immediately under it with "verify" as its SUFFIX action. Mechanics of
MAIL1 (codes, hashing, cooldowns, server-side refusal, consume-on-submit)
are untouched — this is placement and interaction only.

Standing runner rules: read `docs/phase-02/ai-development-lessons.md` IN
FULL during preflight; short research/plan notes BEFORE code (micro-run
scale is fine); no push unless asked; no `filacheck --fix`;
fixture-owned tests; `Mail::fake()` everywhere; en+he translations for
the two action labels; NO Composer/npm changes.

CANONICAL RUN ENDING (standing): implementation commit (`## Commit hash`
pending) → immediately a docs-only commit stamping the hash into handoff
+ ledger (`docs: backfill forms otp ux mail2 hash`).

Handoff: `docs/phase-02/forms-otp-ux-mail2-handoff.md`; gate outcomes
written in before committing; Local Front Check Report = numbered MANUAL
OPERATOR STEPS. Kickoff corrections: enumerated or "none".

FINAL GATE ORDER (standing): requirements sweep → `vendor/bin/pint --test`
→ `vendor/bin/filacheck` → `npm run build` → FULL `php artisan test` LAST
(once = once GREEN on final state; re-enter from Pint after any change).

## Preflight

```bash
git status --short --branch
git log --oneline -5
```

Stop on unexpected app-code dirt. This run may land either side of SP3B —
both touch disjoint files; verify no overlap dirt exists.

## The one job

**Livewire public form (`PublicFormModal` + its blade):**

1. When a form requires email verification, the verification UI renders
   as part of the email field group, DIRECTLY UNDER the email input —
   never at the end of the form.
2. The email input gets a SUFFIX action button "שלח קוד אימות" / "Send
   verification code": disabled until the email value is valid; on click
   it sends the code (existing manager) and shows the cooldown state on
   the button itself (disabled with countdown feedback until resend is
   allowed).
3. After a code is sent, the CODE input appears immediately under the
   email input, with its own SUFFIX action "אמת" / "Verify". Wrong code
   and exhausted-attempts errors render inline under the code field.
4. On success: a clear verified state on the email field group
   (check/verified badge), the code input collapses or locks.
5. EDITING THE EMAIL AFTER VERIFICATION resets the client verified state
   — and the server must already treat the changed address as
   unverified (verification is bound to channel+address; confirm with a
   test that submitting with a changed email is refused until
   re-verified).
6. RTL correctness: "suffix" placement follows the existing public-form
   RTL conventions (visual start in Hebrew); match existing public form
   styling and focus/keyboard behavior (Enter in code field triggers
   verify, not form submit).

**Maintenance plain-POST fallback:** mechanics unchanged (two-step POST,
signed token, verification at submit). Only the LAYOUT aligns: the
send-code control sits with the email field and the code input renders
directly under the email input, styled consistently with the fallback
container. No JS added to the 503 shell.

## Tests

Livewire: suffix send action sends and starts cooldown; code field
appears under the email group; verify suffix marks verified; changed
email resets verified client state AND the server refuses submit until
re-verified; unverified submit still refused; verified + consumed submit
passes. Maintenance: rendered order — the code input follows the email
input inside the form markup; two-step flow still passes. Existing MAIL1
suites stay green. Full gate per header order.

## Docs and handoff

Ledger row `MAIL2 - Inline email verification UX`;
`current-project-state.md`; brief research/plan notes
(`docs/research/forms-mail/01-mail2-notes.md` is sufficient at this
scale); handoff per header rules. Local Front Check Report (operator
steps): open a verification-required public form → type an invalid email
→ send button disabled; valid email → click send → cooldown countdown on
the button and code field opens under the email; enter wrong code →
inline error; correct code → verified badge; edit the email → verified
state clears and submit refuses until re-verified; complete a submission;
repeat the flow on the maintenance fallback page and confirm the code
field sits directly under the email field.

Commit: `feat: attach email verification inline to the email field`
Then the canonical docs-only backfill commit (see header).

End with exactly:

```text
Forms OTP UX MAIL2 is complete. Waiting for Yoni review before continuing.
```
