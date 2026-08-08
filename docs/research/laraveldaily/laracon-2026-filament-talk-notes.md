# Povilas Korop @ Laracon US 2026 — "Filament: Advanced Practical Examples" — notes

- **Source**: [Laracon US 2026, Day 2 livestream](https://www.youtube.com/watch?v=vii6P0vJhTw&t=7517s)
  (Laravel channel, 2026-07-30), segment **2:05:17 → 2:28:49**; talk proper ≈ 2:05:04–2:25:50,
  then host Q&A.
- **Captured**: 2026-08-08 — full ASR transcript + frame-verified slide code, archived in
  `raw/youtube-vii6P0vJhTw-laracon-filament-talk.md` (gitignored). Extraction method: README
  §3b-ter.
- **Verified against**: installed `filament/filament 5.7.x`, this repo's panel providers and
  Livewire components.
- **Staleness verdict**: **as fresh as Filament material gets** — nine days old at capture,
  explicitly about Filament v5, delivered on Laravel's own stage. This is the current-Filament
  material the whole LaravelDaily catalogue lacks (its one Filament course is Aug 2025,
  v4-era).

**Headline: the talk's thesis — "build apps, not admin panels" — is a public, on-stage
validation of PodText's architecture.** Its 13 demos map almost one-to-one onto patterns this
repo already runs, including the one the talk presents as its myth-busting finale.

---

## 1. The talk in one paragraph

Filament (31k GitHub stars / 31M downloads, v5, TALL-stack — "a layer on top of Livewire";
explicitly **not** for Inertia/Vue) is presented not as an admin-panel builder but as an
app-building toolkit. Thirteen demos in four groups: custom **grids** (attendance checkboxes,
availability colors, GitHub-style heatmap, table-as-grid), **visual customization** (render
hooks for a sidebar profile card, a full custom theme he advises *against*, moving the search
into the sidebar), **complex forms** (invoice Repeater with `->relationship()`,
sections/tabs, Wizard with a custom summary step), and **Filament outside the panel** (World
Cup standings page, a public table via the table trait in a plain Livewire component, a
public booking form). Close: "let's build more apps, not admin panels" — and, on the AI
moment: "we build more things, more complex things, challenge ourselves."

## 2. Claims verified against the installed stack

| talk claim | verification |
| --- | --- |
| "around 100 hooks last time I checked" | **103+ measured**: `PanelsRenderHook` has 83 constants, `TablesRenderHook` 20, plus Widgets/Actions/Schemas hook classes — installed Filament 5.7 |
| `renderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn (): string => view(…)->render())` (slide 5/13) | exact live API; **PodText already uses this pattern 5×** across `AdminPanelProvider` (4 hooks) and `PublicPanelProvider` (imports `PanelsRenderHook`) |
| Custom pages are "Livewire components with your own Blade" | PodText: 6+ custom `app/Filament/Pages/*` (settings pages, dashboard) |
| Widgets embed anywhere, Blade free-form inside | PodText: **15 widgets** |
| Repeater + `->relationship()` for master–detail (slide 8/13) | current Filament 5 API (matches the CLAUDE.md Filament guidance verbatim) |
| Public table = Livewire component using the table trait, `{{ $this->table }}` in Blade (slide 12/13) | current API. PodText's public Livewire components render Filament tables on the public side — same mechanism |
| "Table-as-grid is not 101… docs page is very long" | honest; matches the house experience that grid-layout tables are the complex end |

Two transcript slips worth flagging so nobody quotes them: "screenshot taken like 10 **years**
ago" (context says 10 *days*), and ASR renders `--generate` as "d-generate", `trait` as
"trade", Filament as "filling it".

## 3. Where PodText stands relative to the talk

The talk's finale — Demo 12/13, "you can use Filament outside of Filament, on public pages,
without any authentication" — is presented as the myth-buster. **PodText's public site goes
one architectural step further than the demo**: not just Filament components in a Blade
layout, but a whole **guest Filament panel** (`PublicPanelProvider`, id `public`), which is
exactly what `.ai/public-panel` mandates. The talk validates the direction from Laravel's own
stage; PodText's implementation is the stronger form of it.

The other demos land as already-practiced (render hooks, custom pages, widgets, repeaters,
tabs/wizards) or deliberately-avoided in agreement with the speaker (full theme overrides —
his verdict "I wouldn't advise you to do that" matches the house position that custom CSS
mountains are the wrong trade).

**No proposals.** The value here is corroboration with a very fresh timestamp, plus the
archive: a current-Filament reference the LaravelDaily catalogue cannot supply.

## 4. Small notable facts

- Filament's own messaging shift, per the speaker: "apps and admin panels — apps being the
  first word".
- His channel taxonomy now: Laravel Daily, **Filament Daily** (300+ videos), AI Coding Daily,
  NativePHP — Filament Daily is the deep-Filament firehose if a specific pattern needs a
  worked example.
- Host Q&A soundbite that matches this project's whole premise: *"big SaaS is dying but
  internal applications are thriving"* (host), and Povilas: internal apps are "probably one of
  the best" Filament use cases.
- Official demo he points at: `demo.filamentphp.com`.

## 5. Sources

- Segment transcript + 3 frame-verified slides + deck-structure reconstruction:
  `raw/youtube-vii6P0vJhTw-laracon-filament-talk.md`.
- Installed Filament 5.7 (`PanelsRenderHook`/`TablesRenderHook` constant counts via
  reflection); `app/Providers/Filament/*` renderHook usage; `app/Filament/Pages` and
  Widgets counts.
- Extraction: YouTube ASR via the player's pot-bearing timedtext request; slides via seek +
  player screenshots (README §3b-ter, including why canvas grabs fail on far seeks of long
  VODs).

### What I could not obtain

- Slides 1–4, 6–7, 9–11, 13's exact code (only 5, 8, 12 were frame-captured after the canvas
  pipeline produced blanks and the operator signalled to wrap; the transcript describes each
  demo's code shape, and the deck-structure section records what each shows). The talk says
  all demos exist as videos on Filament Daily — the durable source if any single demo needs
  full code.
- The deck itself (not published at capture time, or not found).
- The larastan session's YouTube method notes — asked, no reply yet; my pot-interception
  route worked without it, and whatever they send back can only refine README §3b-ter.
