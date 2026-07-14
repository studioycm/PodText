# LENS1 Implementation Plan

## Contract controls

- Preserve every existing multi-mode branch, query semantic, and base
  translation value. Single mode selects variants or adds a narrower query;
  it never rewrites stored multi settings, card templates, or multi strings.
- Make no Composer or npm dependency changes.
- Keep public aggregation in correlated SQL subqueries and presentation in
  presenters/helpers, never in Blade queries.
- Use the shared model lifecycle as the creation guard. The workspace fresh
  replacement receives only a transient per-model exemption; no global event
  suppression or process-global bypass is allowed.
- Finish all code, tests, state docs, ledger, and handoff before entering the
  final gate. After any change, re-enter the gate from Pint.

## Implementation sequence

1. Add `TranscriptionModeLabel`, a small translation resolver. In multi mode it
   always returns the supplied base key. In single mode it uses the supplied
   `public.labels.single.*` / `admin.*.single.*` variant when that key exists,
   otherwise it falls back to the base key.
2. Clamp `PublicTranscriptionPolicy` display/count modes to effective-only in
   single mode. Preserve its current stored-mode return values in multi.
3. Change only the single branches of aggregate subselects: contributors and
   podcasts count distinct `content_items.id` after the existing published and
   effective predicates. Preserve current multi `count(*)` and
   `count(distinct transcriptions.id)` expressions exactly.
4. Route card and full-surface strings through the helper. Add hard
   presenter-level suppression for an episode transcription-count part in
   single mode, and prove the public viewer cannot expose multiple rows.
5. Add the shared second-row validation in `Transcription::creating()`. Mark a
   model produced by `startFreshWorkspaceTranscription()` as a sanctioned
   replacement before saving, then adopt it. Retain the existing first-row
   auto-feature callback unchanged.
6. Add a reusable admin-current-row query predicate: valid featured row first,
   then latest published row, then latest row. This deliberately mirrors the
   workspace/edit target and includes a newly featured draft; a public-only
   effective predicate would incorrectly omit draft-only episodes. Apply it to
   the standalone resource default in single mode. The standalone super-admin
   history filter removes the named scope; admins cannot see the filter. Multi
   tables retain today's all-row query.
7. Use episode-language variants for the standalone resource/forms and hide
   its single-mode featured action. Preserve the episode relation manager and
   item/group admin table columns exactly as clarified by the operator. Hide
   only the workspace alternate-row hint outside those intentional surfaces.
8. Add behavioral coverage, repair existing multi-row fixtures by explicitly
   selecting multi mode before creation (then switch to single for leak tests),
   and run targeted suites.
9. Complete the requirement sweep and committed handoff, then run the final
   gate in the mandated order.

## Admin current-row decision

For public counts, “effective” remains the published featured row followed by
the latest published row. For the admin Transcriptions resource, the one row
shown in single mode is the episode's current editable/workspace transcript:
featured row regardless of publication state, then latest published row, then
latest row. This distinction is required so an episode with only a draft still
appears and a freshly replaced draft becomes the visible current row while the
replaced row moves to history. This is flagged for Yoni review in the handoff.

## Leak-audit implementation checklist

| Surface | Planned control | Verification |
| --- | --- | --- |
| Public policy | Single returns effective-only modes | Unit policy test with stored `all_published` |
| Contributor and podcast aggregates | Distinct effective episode IDs in single | Stray-row single/multi aggregate tests |
| Contributor/podcast cards | Short episode variants | Presenter/rendered assertions in he/en |
| Contributor/podcast fuller surfaces | Transcribed-episode/full variants | Page and Livewire rendered assertions |
| Episode card custom count part | Hard presenter suppression | Stored-template regression test |
| Episode detail count | Policy clamp plus explicit absence assertion | Public item rendering test |
| Public viewer | Effective-only collection in single | No switcher and forged old-key normalization test |
| Standalone Transcriptions table | Current row scope + super-admin history removal | Role/mode table record and filter assertions |
| Standalone featured action | Single hidden | Action visibility assertion |
| Episode relation manager | Intentional operational history surface; no change | Existing roles/admin regression |
| Relation-manager tab badge | Intentional raw operational count; no change | Existing relation-manager regression |
| Item admin table | Intentional featured/context columns; no change | Existing admin resource regression |
| Group-items admin table | Intentional featured/count columns; no change | Existing relation-manager regression |
| Classic item form | Existing multi gate retained | Roles-gates regression |
| Workspace alternate hint | Single suppression; multi unchanged | Workspace render assertion |
| Workspace visibility wording | Episode-transcript variant | Translation/render assertion |
| Dashboard | No transcription-count widget found | Documented no-change audit |
| Navigation | No numeric badge found; resource noun varies | Resource label assertion |
| Transcription CSV/import/export | Row-level technical schema unchanged | Import/export regression; guard failure test |
| Content-item CSV/import/export | Featured reference header unchanged and technical | Import/export regression |
| Template editor | ROLES1 save/visibility gate retained | Roles-gates regression |
| Stored template rendering | Runtime count suppression | Presenter regression |

## Complete label inventory

This table is the exhaustive catalogue scan of every `public.php` and
`admin.php` user-facing key whose key or either locale contains
“transcription”, “transcript”, or “תמל”, plus `admin.fields.content_item`
because it labels the required leading episode column. Base values are the
multi-mode values and remain byte-identical. “Keep” reproduces the listed base
English/Hebrew values in single mode. “Variant” adds and selects a new key;
“suppressed” renders no label or surface in single mode. The dedicated fuller
count call sites select the full “transcribed episodes” variant while cards
select the short “episodes” variant.

| Translation key | Multi/before English | Multi/before Hebrew | Single treatment | Single/after English | Single/after Hebrew |
| --- | --- | --- | --- | --- | --- |
| `public.actions.view_contributor` | View contributor | דף מתמלל | Keep | View contributor | דף מתמלל |
| `public.dates.transcription_date_long` | Transcription published | התמלול פורסם | Keep | Transcription published | התמלול פורסם |
| `public.dates.transcription_date_short` | Transcript | תמלול | Keep | Transcript | תמלול |
| `public.empty.contributor_items` | No public items are available for this contributor yet. | עדיין אין פרקים מפורסמים למתמלל הזה. | Keep | No public items are available for this contributor yet. | עדיין אין פרקים מפורסמים למתמלל הזה. |
| `public.empty.contributor_preview` | Select a contributor to preview related public items. | בחרו מתמלל כדי לראות תצוגה מקדימה של פרקים מפורסמים קשורים. | Keep | Select a contributor to preview related public items. | בחרו מתמלל כדי לראות תצוגה מקדימה של פרקים מפורסמים קשורים. |
| `public.empty.contributors` | No public contributors are available yet. | עדיין אין מתמללים. | Keep | No public contributors are available yet. | עדיין אין מתמללים. |
| `public.filters.effective_date` | Transcription date | תאריך תמלול | Keep | Transcription date | תאריך תמלול |
| `public.filters.effective_from` | Transcription from | תמלול מתאריך | Keep | Transcription from | תמלול מתאריך |
| `public.filters.effective_until` | Transcription until | תמלול עד תאריך | Keep | Transcription until | תמלול עד תאריך |
| `public.filters.search_contributors_placeholder` | Search contributors by name | חיפוש מתמללים לפי שם | Keep | Search contributors by name | חיפוש מתמללים לפי שם |
| `public.filters.search_related_items_placeholder` | Filter this contributor preview | סינון תצוגת המתמלל | Keep | Filter this contributor preview | סינון תצוגת המתמלל |
| `public.filters.transcriber` | Transcriber | מתמלל | Keep | Transcriber | מתמלל |
| `public.item_page.info_fields.transcribers_long` | Transcribers | מתמללים | Keep | Transcribers | מתמללים |
| `public.item_page.info_fields.transcribers_short` | By | מאת | Keep | By | מאת |
| `public.item_page.info_fields.transcription_count_long` | Transcriptions | תמלולים | Suppressed in single | — | — |
| `public.item_page.info_fields.transcription_count_short` | Transcripts | תמלולים | Suppressed in single | — | — |
| `public.labels.authors` | Authors | מתמללים | Keep | Authors | מתמללים |
| `public.labels.contributor` | Contributor | מתמלל | Keep | Contributor | מתמלל |
| `public.labels.contributor_transcriptions` | Contributor transcriptions | תמלולים של המתמלל | Variant | Transcribed episodes | פרקים שתומללו |
| `public.labels.contributors` | Contributors | מתמללים | Keep | Contributors | מתמללים |
| `public.labels.public_group_latest_transcription_date` | Latest transcription :date | תמלול אחרון :date | Variant | latest episode :date | פרק אחרון :date |
| `public.labels.public_transcribers_count` | {0} No public transcribers\|{1} 1 public transcriber\|[2,*] :count public transcribers | {0} אין מתמללים\|{1} מתמלל אחד\|[2,*] :count מתמללים | Keep | {0} No public transcribers\|{1} 1 public transcriber\|[2,*] :count public transcribers | {0} אין מתמללים\|{1} מתמלל אחד\|[2,*] :count מתמללים |
| `public.labels.public_transcriptions_count` | {0} No public transcriptions\|{1} 1 public transcription\|[2,*] :count public transcriptions | {0} אין תמלולים\|{1} תמלול אחד\|[2,*] :count תמלולים | Variant by surface | Cards: {0} No episodes / {1} 1 episode / [2,*] :count episodes; Full: {0} No transcribed episodes / {1} 1 transcribed episode / [2,*] :count transcribed episodes | Cards: {0} אין פרקים / {1} פרק אחד / [2,*] :count פרקים; Full: {0} אין פרקים מתומללים / {1} פרק מתומלל אחד / [2,*] :count פרקים מתומללים |
| `public.labels.transcriber` | Transcriber | מתמלל | Keep | Transcriber | מתמלל |
| `public.labels.transcribers` | Transcribers | מתמללים | Keep | Transcribers | מתמללים |
| `public.labels.transcript_length` | Transcript length | אורך התמלול | Keep | Transcript length | אורך התמלול |
| `public.labels.transcript_words_count` | {0} No transcript words\|{1} 1 transcript word\|[2,*] :count transcript words | {0} אין מילים בתמלול\|{1} מילה אחת בתמלול\|[2,*] :count מילים בתמלול | Keep | {0} No transcript words\|{1} 1 transcript word\|[2,*] :count transcript words | {0} אין מילים בתמלול\|{1} מילה אחת בתמלול\|[2,*] :count מילים בתמלול |
| `public.labels.transcription` | Transcription | תמלול | Keep | Transcription | תמלול |
| `public.labels.transcriptions` | Transcriptions | תמלולים | Keep | Transcriptions | תמלולים |
| `public.labels.untitled_transcription` | Untitled transcription | תמלול ללא כותרת | Keep | Untitled transcription | תמלול ללא כותרת |
| `public.menu.forms.request_transcription` | Request transcription | בקשת תמלול | Keep | Request transcription | בקשת תמלול |
| `public.menu.forms.volunteer_transcriber` | Register as transcriber | הרשמה כמתמלל | Keep | Register as transcriber | הרשמה כמתמלל |
| `public.menu.routes.contributors` | Contributors | מתמללים | Keep | Contributors | מתמללים |
| `public.pages.browse.description` | Browse the latest published episodes with transcripts that are ready for public reading. | עיון בפרקים האחרונים שפורסמו עם תמלולים שמוכנים לקריאה. | Keep | Browse the latest published episodes with transcripts that are ready for public reading. | עיון בפרקים האחרונים שפורסמו עם תמלולים שמוכנים לקריאה. |
| `public.pages.contributor.items_heading` | Public items by this contributor | פרקים של המתמלל | Variant | Transcribed episodes | פרקים שתומללו |
| `public.pages.contributor.kicker` | Contributor | מתמלל | Keep | Contributor | מתמלל |
| `public.pages.contributors.description` | Find public contributors and preview the episodes connected to their published transcriptions. | איתור מתמללים ותצוגה מקדימה של פרקים שמחוברים לתמלולים שפורסמו. | Keep | Find public contributors and preview the episodes connected to their published transcriptions. | איתור מתמללים ותצוגה מקדימה של פרקים שמחוברים לתמלולים שפורסמו. |
| `public.pages.contributors.kicker` | Contributors | מתמללים | Keep | Contributors | מתמללים |
| `public.pages.contributors.preview_description` | A quick preview of public items connected through published transcriptions. | תצוגה מהירה של פרקים שמחוברים דרך תמלולים שפורסמו. | Keep | A quick preview of public items connected through published transcriptions. | תצוגה מהירה של פרקים שמחוברים דרך תמלולים שפורסמו. |
| `public.pages.contributors.preview_kicker` | Selected contributor | מתמלל נבחר | Keep | Selected contributor | מתמלל נבחר |
| `public.pages.contributors.title` | Contributors | מתמללים | Keep | Contributors | מתמללים |
| `public.pages.contributors.top_preview_kicker` | Top transcriber preview | תצוגת מתמלל מוביל | Keep | Top transcriber preview | תצוגת מתמלל מוביל |
| `public.pages.item.transcript_heading` | Transcript | תמלול | Keep | Transcript | תמלול |
| `public.pages.podcasts.description` | Browse podcasts with public episodes and published transcriptions. | עיון בפודקאסטים עם פרקים ותמלולים שפורסמו. | Keep | Browse podcasts with public episodes and published transcriptions. | עיון בפודקאסטים עם פרקים ותמלולים שפורסמו. |
| `public.pages.search.description` | Search all public content items with published transcriptions. | חיפוש בכל פרקי התוכן המפורסמים עם תמלולים שפורסמו. | Keep | Search all public content items with published transcriptions. | חיפוש בכל פרקי התוכן המפורסמים עם תמלולים שפורסמו. |
| `public.results.contributors_count` | {0} No public contributors\|{1} 1 public contributor\|[2,*] :count public contributors | {0} אין מתמללים\|{1} מתמלל אחד\|[2,*] :count מתמללים | Keep | {0} No public contributors\|{1} 1 public contributor\|[2,*] :count public contributors | {0} אין מתמללים\|{1} מתמלל אחד\|[2,*] :count מתמללים |
| `public.results.public_contributors_only` | Showing contributors with published transcriptions on public items. | מוצגים מתמללים עם תמלולים שפורסמו בפרקים. | Keep | Showing contributors with published transcriptions on public items. | מוצגים מתמללים עם תמלולים שפורסמו בפרקים. |
| `public.results.public_items_only` | Showing published items with published transcriptions. | מוצגים פרקים שפורסמו עם תמלולים שפורסמו. | Keep | Showing published items with published transcriptions. | מוצגים פרקים שפורסמו עם תמלולים שפורסמו. |
| `public.results.public_podcasts_only` | Showing podcasts with published episodes and published transcriptions. | מוצגים פודקאסטים עם פרקים ותמלולים שפורסמו. | Keep | Showing podcasts with published episodes and published transcriptions. | מוצגים פודקאסטים עם פרקים ותמלולים שפורסמו. |
| `public.sections.top_transcribers` | Top transcribers | המתמללים המובילים | Keep | Top transcribers | המתמללים המובילים |
| `public.sections.top_transcribers_target` | Contributors | מתמללים | Keep | Contributors | מתמללים |
| `public.sort.latest_transcription` | Latest transcription | התמלול החדש ביותר | Keep | Latest transcription | התמלול החדש ביותר |
| `public.sort.oldest_transcription` | Oldest transcription | התמלול הישן ביותר | Keep | Oldest transcription | התמלול הישן ביותר |
| `public.viewer.actions` | Transcript actions | פעולות תמלול | Keep | Transcript actions | פעולות תמלול |
| `admin.actions.add_transcription` | Add transcription | הוספת תמלול | Variant | Add episode transcript | הוספת תמלול לפרק |
| `admin.actions.edit_effective_transcription` | Edit transcription | עריכת תמלול | Keep | Edit transcription | עריכת תמלול |
| `admin.actions.open_transcription_resource` | Open full resource | פתיחת המשאב המלא | Variant | Open episode transcript | פתיחת תמלול הפרק |
| `admin.actions.replace_transcription` | Replace transcription | החלפת תמלול | Keep | Replace transcription | החלפת תמלול |
| `admin.actions.save_transcription` | Save transcription | שמירת תמלול | Variant | Save episode transcript | שמירת תמלול הפרק |
| `admin.actions.select_existing_transcription` | Select existing transcription | בחירת תמלול קיים | Suppressed in single | — | — |
| `admin.actions.set_featured_transcription` | Set featured | הגדרה כנבחר | Suppressed in single | — | — |
| `admin.actions.start_fresh_transcription` | Start fresh transcription | התחלת תמלול חדש | Keep | Start fresh transcription | התחלת תמלול חדש |
| `admin.card_template_attributes.author.transcription_count` | Public transcription count | מספר תמלולים | Variant | Episode count | מספר פרקים |
| `admin.card_template_attributes.author.url` | Contributor URL | כתובת מתמלל | Keep | Contributor URL | כתובת מתמלל |
| `admin.card_template_attributes.content_group.latest_transcription_date` | Latest transcription date | תאריך תמלול אחרון | Variant | Latest episode date | תאריך הפרק האחרון |
| `admin.card_template_attributes.content_group.transcriber_count` | Distinct transcriber count | מספר מתמללים ייחודיים | Keep | Distinct transcriber count | מספר מתמללים ייחודיים |
| `admin.card_template_attributes.content_group.transcription_count` | Public transcription count | מספר תמלולים ציבוריים | Variant | Episode count | מספר פרקים |
| `admin.card_template_attributes.content_item.effective_date` | Effective transcription date | תאריך תמלול פעיל | Keep | Effective transcription date | תאריך תמלול פעיל |
| `admin.card_template_attributes.content_item.effective_transcription_title` | Effective transcription title | כותרת התמלול האפקטיבי | Variant | Episode transcript title | כותרת תמלול הפרק |
| `admin.card_template_attributes.content_item.transcribers` | Transcribers | מתמללים | Keep | Transcribers | מתמללים |
| `admin.card_template_attributes.content_item.transcription_count` | Public transcription count | מספר תמלולים ציבוריים | Suppressed in single | — | — |
| `admin.card_template_attributes.contributor.transcription_count` | Public transcription count | מספר תמלולים ציבוריים | Variant | Episode count | מספר פרקים |
| `admin.card_template_attributes.contributor.url` | Contributor URL | כתובת מתמלל | Keep | Contributor URL | כתובת מתמלל |
| `admin.card_template_attributes.transcription.author_name` | Transcriber name | שם מתמלל | Keep | Transcriber name | שם מתמלל |
| `admin.card_template_attributes.transcription.published_at` | Publication date | תאריך פרסום | Keep | Publication date | תאריך פרסום |
| `admin.card_template_attributes.transcription.read_time` | Read time | זמן קריאה | Keep | Read time | זמן קריאה |
| `admin.card_template_attributes.transcription.reading_time` | Reading time | זמן קריאה | Keep | Reading time | זמן קריאה |
| `admin.card_template_attributes.transcription.title` | Title | כותרת | Keep | Title | כותרת |
| `admin.card_template_attributes.transcription.transcribers` | Transcribers | מתמללים | Keep | Transcribers | מתמללים |
| `admin.card_template_attributes.transcription.word_count` | Word count | מספר מילים | Keep | Word count | מספר מילים |
| `admin.card_template_families.contributor` | Contributor | מתמלל | Keep | Contributor | מתמלל |
| `admin.card_template_part_types.transcriber_line` | Transcriber line | שורת מתמלל | Keep | Transcriber line | שורת מתמלל |
| `admin.card_template_sources.contributor` | Contributor | מתמלל | Keep | Contributor | מתמלל |
| `admin.card_template_sources.transcription` | Transcription | תמלול | Keep | Transcription | תמלול |
| `admin.default_image_families.contributor` | Contributor cards and pages | כרטיסי ועמודי מתמללים | Keep | Contributor cards and pages | כרטיסי ועמודי מתמללים |
| `admin.descriptions.content_item_content` | Editorial content, taxonomy, and media URLs for this item. Episode transcribers are managed on transcriptions. | תוכן עריכתי, טקסונומיה וכתובות מדיה עבור פריט זה. מתמללי פרק מנוהלים בתמלולים. | Keep | Editorial content, taxonomy, and media URLs for this item. Episode transcribers are managed on transcriptions. | תוכן עריכתי, טקסונומיה וכתובות מדיה עבור פריט זה. מתמללי פרק מנוהלים בתמלולים. |
| `admin.descriptions.content_item_publication` | Publication controls for the item. Public visibility also requires a published parent group and a published effective transcription. | בקרות פרסום עבור הפריט. נראות מחייבת גם פודקאסט הורה מפורסם ותמלול פעיל. | Keep | Publication controls for the item. Public visibility also requires a published parent group and a published effective transcription. | בקרות פרסום עבור הפריט. נראות מחייבת גם פודקאסט הורה מפורסם ותמלול פעיל. |
| `admin.descriptions.episode_workspace` | Controls for the single-transcription episode editing workspace. | הגדרות לסביבת עריכת פרק עם תמלול יחיד. | Keep | Controls for the single-transcription episode editing workspace. | הגדרות לסביבת עריכת פרק עם תמלול יחיד. |
| `admin.descriptions.episode_workspace_transcription` | The workspace edits one selected transcription while keeping alternate transcriptions attached. | סביבת העבודה עורכת תמלול נבחר אחד ומשאירה תמלולים חלופיים מחוברים. | Variant | Edit the episode transcript together with its episode metadata. | עריכת התמלול של הפרק יחד עם נתוני הפרק. |
| `admin.descriptions.featured_transcription` | Choose the primary transcript only when more than one transcript exists. The first transcript is selected automatically. | בחרו את התמלול הראשי רק כאשר קיים יותר מתמלול אחד. התמלול הראשון נבחר אוטומטית. | Suppressed in single | — | — |
| `admin.descriptions.public_front_contributors_page` | Public contributor discovery and top-transcriber section settings. These change public labels, card controls, and item-list behavior only; internal records remain Author and ContentItem. | הגדרות גילוי מתמללים ומקטעי מתמללים מובילים בצד. הן משנות תוויות, כרטיסים והתנהגות רשימות בלבד; הרשומות הפנימיות נשארות Author ו-ContentItem. | Keep | Public contributor discovery and top-transcriber section settings. These change public labels, card controls, and item-list behavior only; internal records remain Author and ContentItem. | הגדרות גילוי מתמללים ומקטעי מתמללים מובילים בצד. הן משנות תוויות, כרטיסים והתנהגות רשימות בלבד; הרשומות הפנימיות נשארות Author ו-ContentItem. |
| `admin.descriptions.public_front_item_page_transcript_controls` | Controls the optional transcript actions menu. Browser reading preferences remain local and are not stored as settings. | קובע את תפריט פעולות התמלול האופציונלי. העדפות הקריאה בדפדפן נשארות מקומיות ואינן נשמרות בהגדרות. | Keep | Controls the optional transcript actions menu. Browser reading preferences remain local and are not stored as settings. | קובע את תפריט פעולות התמלול האופציונלי. העדפות הקריאה בדפדפן נשארות מקומיות ואינן נשמרות בהגדרות. |
| `admin.descriptions.public_transcription_policy` | Central public policy for selecting and counting transcriptions. Featured-only is the default public behavior; all-published is reserved for surfaces that explicitly support multiple transcriptions. | מדיניות מרכזית לבחירה ולספירה של תמלולים ציבוריים. ברירת המחדל היא תמלול נבחר בלבד; כל התמלולים שמור למשטחים שתומכים בכך במפורש. | Suppressed in single | — | — |
| `admin.descriptions.transcript_markdown` | Canonical transcript Markdown for this item. Public rendering uses the safe Markdown renderer. | Markdown התמלול הקנוני של פריט זה. הרינדור המשתמש במרנדר Markdown בטוח. | Variant | Canonical Markdown for the episode transcript. Public rendering uses the safe Markdown renderer. | Markdown קנוני של תמלול הפרק. התצוגה הציבורית משתמשת במרנדר Markdown בטוח. |
| `admin.descriptions.transcription_identity` | Transcript ownership metadata. The item is supplied by the relation manager context. | מטא-דאטה של בעלות על התמלול. הפריט מסופק מתוך הקשר מנהל הקשר. | Variant | Ownership metadata for the episode transcript. | מטא-דאטה של תמלול הפרק. |
| `admin.descriptions.transcription_publication` | Only published transcriptions can become publicly effective. Drafts remain admin-only. | רק תמלולים שפורסמו יכולים להפוך לפעילים. טיוטות נשארות למנהלים בלבד. | Variant | Only a published episode transcript can be public. Drafts remain admin-only. | רק תמלול פרק שפורסם יכול להיות ציבורי. טיוטות נשארות למנהלים בלבד. |
| `admin.descriptions.user_role` | Only super admins can assign roles. Admin-panel access begins at Admin; multi-transcription controls require the configured role gates and multi mode. | רק סופר-אדמינים יכולים לשנות תפקידים. גישת ניהול מתחילה בתפקיד מנהל; בקרות ריבוי תמלולים תלויות בתפקיד ובמצב ריבוי. | Keep | Only super admins can assign roles. Admin-panel access begins at Admin; multi-transcription controls require the configured role gates and multi mode. | רק סופר-אדמינים יכולים לשנות תפקידים. גישת ניהול מתחילה בתפקיד מנהל; בקרות ריבוי תמלולים תלויות בתפקיד ובמצב ריבוי. |
| `admin.fields.content_item` | Content item | פרק | Variant (review) | Episode | פרק |
| `admin.fields.contributor_page_default_sort` | Default contributor item sort | מיון פרקי מתמלל כברירת מחדל | Keep | Default contributor item sort | מיון פרקי מתמלל כברירת מחדל |
| `admin.fields.contributor_page_grid_columns` | Contributor item grid columns | עמודות גריד פרקי מתמלל | Keep | Contributor item grid columns | עמודות גריד פרקי מתמלל |
| `admin.fields.contributor_page_grid_gap` | Contributor item grid gap | ריווח גריד פרקי מתמלל | Keep | Contributor item grid gap | ריווח גריד פרקי מתמלל |
| `admin.fields.contributor_page_items_per_page` | Contributor items per page | פרקי מתמלל בעמוד | Keep | Contributor items per page | פרקי מתמלל בעמוד |
| `admin.fields.contributor_page_page_size_options` | Contributor item page-size options | אפשרויות גודל עמוד לפרקי מתמלל | Keep | Contributor item page-size options | אפשרויות גודל עמוד לפרקי מתמלל |
| `admin.fields.contributor_page_search_enabled` | Enable contributor item search | הפעלת חיפוש בפרקי מתמלל | Keep | Enable contributor item search | הפעלת חיפוש בפרקי מתמלל |
| `admin.fields.contributor_page_sort_options` | Contributor item sort options | אפשרויות מיון פרקי מתמלל | Keep | Contributor item sort options | אפשרויות מיון פרקי מתמלל |
| `admin.fields.contributors_directory_default_per_page` | Default contributors per page | מתמללים בעמוד כברירת מחדל | Keep | Default contributors per page | מתמללים בעמוד כברירת מחדל |
| `admin.fields.contributors_directory_default_sort` | Default contributor sort | מיון מתמללים כברירת מחדל | Keep | Default contributor sort | מיון מתמללים כברירת מחדל |
| `admin.fields.contributors_directory_per_page_options` | Contributor page-size options | אפשרויות גודל עמוד למתמללים | Keep | Contributor page-size options | אפשרויות גודל עמוד למתמללים |
| `admin.fields.contributors_directory_preview_items_per_page` | Preview items per contributor | פריטי תצוגה לכל מתמלל | Keep | Preview items per contributor | פריטי תצוגה לכל מתמלל |
| `admin.fields.contributors_directory_sort_options` | Contributor sort options | אפשרויות מיון מתמללים | Keep | Contributor sort options | אפשרויות מיון מתמללים |
| `admin.fields.contributors_page_description` | Contributors page description | תיאור דף המתמללים | Keep | Contributors page description | תיאור דף המתמללים |
| `admin.fields.contributors_page_enabled` | Enable contributors page | הפעלת דף מתמללים | Keep | Enable contributors page | הפעלת דף מתמללים |
| `admin.fields.contributors_page_label_plural` | Contributor plural label | תווית מתמללים ברבים | Keep | Contributor plural label | תווית מתמללים ברבים |
| `admin.fields.contributors_page_label_singular` | Contributor singular label | תווית מתמלל ביחיד | Keep | Contributor singular label | תווית מתמלל ביחיד |
| `admin.fields.contributors_page_title` | Contributors page title | כותרת דף המתמללים | Keep | Contributors page title | כותרת דף המתמללים |
| `admin.fields.effective_transcription` | Effective transcription | תמלול אפקטיבי | Keep (intentional admin tables) | Effective transcription | תמלול אפקטיבי |
| `admin.fields.existing_transcription` | Existing transcription | תמלול קיים | Suppressed in single | — | — |
| `admin.fields.featured_transcription` | Featured transcription | תמלול נבחר | Keep (intentional admin operations) | Featured transcription | תמלול נבחר |
| `admin.fields.homepage_show_effective_date` | Show transcription date | הצגת תאריך תמלול | Keep | Show transcription date | הצגת תאריך תמלול |
| `admin.fields.item_page_show_transcript_actions_menu` | Show transcript actions menu | הצגת תפריט פעולות תמלול | Keep | Show transcript actions menu | הצגת תפריט פעולות תמלול |
| `admin.fields.item_page_transcription_date_enabled` | Show transcription date | הצגת תאריך תמלול | Keep | Show transcription date | הצגת תאריך תמלול |
| `admin.fields.podcasts_group_page_show_episode_authors` | Show episode authors | הצגת מתמללים בפרקים | Keep | Show episode authors | הצגת מתמללים בפרקים |
| `admin.fields.podcasts_group_page_show_episode_effective_date` | Show episode transcription date | הצגת תאריך תמלול פרק | Keep | Show episode transcription date | הצגת תאריך תמלול פרק |
| `admin.fields.public_front_transcription_display` | Transcription display | תצוגת תמלול בכרטיסים | Keep | Transcription display | תצוגת תמלול בכרטיסים |
| `admin.fields.public_transcription_policy_count_mode` | Public count mode | מצב ספירה ציבורי | Suppressed in single | — | — |
| `admin.fields.public_transcription_policy_public_mode` | Public display mode | מצב תצוגה ציבורי | Suppressed in single | — | — |
| `admin.fields.public_transcription_policy_show_multiple_transcriptions_on_item_page` | Allow multiple transcript tabs on item pages | לאפשר כמה תמלולים בדף פרק | Suppressed in single | — | — |
| `admin.fields.show_episode_workspace_hint_line` | Show hidden-transcriptions hint | הצגת רמז על תמלולים מוסתרים | Suppressed in single | — | — |
| `admin.fields.top_transcribers_enabled` | Enable top transcribers sections | הפעלת מקטעי מתמללים מובילים | Keep | Enable top transcribers sections | הפעלת מקטעי מתמללים מובילים |
| `admin.fields.top_transcribers_layout` | Top transcribers layout | פריסת מתמללים מובילים | Keep | Top transcribers layout | פריסת מתמללים מובילים |
| `admin.fields.top_transcribers_limit` | Top transcribers limit | מגבלת מתמללים מובילים | Keep | Top transcribers limit | מגבלת מתמללים מובילים |
| `admin.fields.top_transcribers_preview_default_page_size` | Top preview default page size | גודל עמוד ברירת מחדל לתצוגת מוביל | Keep | Top preview default page size | גודל עמוד ברירת מחדל לתצוגת מוביל |
| `admin.fields.top_transcribers_preview_grid_columns` | Top preview grid columns | עמודות גריד בתצוגת מוביל | Keep | Top preview grid columns | עמודות גריד בתצוגת מוביל |
| `admin.fields.top_transcribers_preview_page_size_options` | Top preview page-size options | אפשרויות גודל עמוד לתצוגת מוביל | Keep | Top preview page-size options | אפשרויות גודל עמוד לתצוגת מוביל |
| `admin.fields.top_transcribers_show_count_badge` | Show count badge | הצגת תג ספירה | Keep | Show count badge | הצגת תג ספירה |
| `admin.fields.top_transcribers_show_full_page_link` | Show contributor page link | הצגת קישור לדף מתמלל | Keep | Show contributor page link | הצגת קישור לדף מתמלל |
| `admin.fields.transcribers` | Transcribers | מתמללים | Keep | Transcribers | מתמללים |
| `admin.fields.transcript_markdown` | Transcript | תמלול | Keep | Transcript | תמלול |
| `admin.fields.transcription_mode` | Transcription mode | מצב תמלול | Keep | Transcription mode | מצב תמלול |
| `admin.fields.transcription_presentation_mode` | Transcription presentation | תצוגת תמלול | Keep | Transcription presentation | תצוגת תמלול |
| `admin.helpers.content_item_status` | Draft items stay private. Published items still need a published group and effective transcription. | פרקים בטיוטה נשארים פרטיים. פרקים שפורסמו עדיין צריכים פודקאסט מפורסם ותמלול פעיל. | Keep | Draft items stay private. Published items still need a published group and effective transcription. | פרקים בטיוטה נשארים פרטיים. פרקים שפורסמו עדיין צריכים פודקאסט מפורסם ותמלול פעיל. |
| `admin.helpers.contributor_cards_compact_show_count` | Shows the compact public count badge on contributor selector cards. | מציג תג ספירה קומפקטי בכרטיסי בחירת מתמלל. | Keep | Shows the compact public count badge on contributor selector cards. | מציג תג ספירה קומפקטי בכרטיסי בחירת מתמלל. |
| `admin.helpers.contributor_cards_preview_show_bio` | Shows the contributor biography excerpt in selected preview panels. | מציג תקציר ביוגרפיה של המתמלל בלוחות תצוגה נבחרים. | Keep | Shows the contributor biography excerpt in selected preview panels. | מציג תקציר ביוגרפיה של המתמלל בלוחות תצוגה נבחרים. |
| `admin.helpers.contributor_page_default_sort` | Initial sort for the full contributor item list. | מיון ראשוני לרשימת פרקי המתמלל המלאה. | Keep | Initial sort for the full contributor item list. | מיון ראשוני לרשימת פרקי המתמלל המלאה. |
| `admin.helpers.contributor_page_grid_columns` | Maximum number of contributor item cards per desktop row. | מספר מרבי של כרטיסי פרקי מתמלל בשורת דסקטופ. | Keep | Maximum number of contributor item cards per desktop row. | מספר מרבי של כרטיסי פרקי מתמלל בשורת דסקטופ. |
| `admin.helpers.contributor_page_grid_gap` | Semantic spacing token for contributor item cards. | אסימון ריווח סמנטי לכרטיסי פרקי מתמלל. | Keep | Semantic spacing token for contributor item cards. | אסימון ריווח סמנטי לכרטיסי פרקי מתמלל. |
| `admin.helpers.contributor_page_items_per_page` | Initial page size for the full contributor public item list. | גודל עמוד ראשוני לרשימת הפרקים המפורסמים המלאה של המתמלל. | Keep | Initial page size for the full contributor public item list. | גודל עמוד ראשוני לרשימת הפרקים המפורסמים המלאה של המתמלל. |
| `admin.helpers.contributor_page_page_size_options` | Allowed page sizes for the full contributor item list. | גדלי עמוד מותרים לרשימת פרקי המתמלל המלאה. | Keep | Allowed page sizes for the full contributor item list. | גדלי עמוד מותרים לרשימת פרקי המתמלל המלאה. |
| `admin.helpers.contributor_page_search_enabled` | Lets visitors search within a contributor’s public items. | מאפשר למבקרים לחפש בתוך הפרקים המפורסמים של מתמלל. | Keep | Lets visitors search within a contributor’s public items. | מאפשר למבקרים לחפש בתוך הפרקים המפורסמים של מתמלל. |
| `admin.helpers.contributor_page_sort_options` | Allowed sorts for the full contributor item list. | אפשרויות מיון מותרות לרשימת פרקי המתמלל המלאה. | Keep | Allowed sorts for the full contributor item list. | אפשרויות מיון מותרות לרשימת פרקי המתמלל המלאה. |
| `admin.helpers.contributors_directory_default_sort` | Initial sort for the contributor directory before a visitor chooses a sort. | המיון הראשוני בספריית המתמללים לפני שמבקר בוחר מיון אחר. | Keep | Initial sort for the contributor directory before a visitor chooses a sort. | המיון הראשוני בספריית המתמללים לפני שמבקר בוחר מיון אחר. |
| `admin.helpers.contributors_directory_per_page_options` | Allowed contributor directory page sizes. Step 10 supports 10, 15, and 20. | גדלי עמוד מותרים בספריית המתמללים. שלב 10 תומך ב-10, 15 ו-20. | Keep | Allowed contributor directory page sizes. Step 10 supports 10, 15, and 20. | גדלי עמוד מותרים בספריית המתמללים. שלב 10 תומך ב-10, 15 ו-20. |
| `admin.helpers.contributors_directory_preview_items_per_page` | Number of related public items shown in the selected contributor preview. | מספר הפרקים המפורסמים הקשורים שמוצגים בתצוגת המתמלל הנבחר. | Keep | Number of related public items shown in the selected contributor preview. | מספר הפרקים המפורסמים הקשורים שמוצגים בתצוגת המתמלל הנבחר. |
| `admin.helpers.contributors_directory_preview_search_enabled` | Lets visitors filter the selected contributor preview without leaving the directory. | מאפשר למבקרים לסנן את תצוגת המתמלל הנבחר בלי לעזוב את הספרייה. | Keep | Lets visitors filter the selected contributor preview without leaving the directory. | מאפשר למבקרים לסנן את תצוגת המתמלל הנבחר בלי לעזוב את הספרייה. |
| `admin.helpers.contributors_directory_sort_options` | Allowed contributor directory sorts. Unsupported values are ignored by validation. | אפשרויות המיון המותרות בספריית המתמללים. ערכים לא נתמכים יידחו בוולידציה. | Keep | Allowed contributor directory sorts. Unsupported values are ignored by validation. | אפשרויות המיון המותרות בספריית המתמללים. ערכים לא נתמכים יידחו בוולידציה. |
| `admin.helpers.contributors_page_description` | Plain public summary shown above the contributor directory. | תקציר פשוט שמוצג מעל ספריית המתמללים. | Keep | Plain public summary shown above the contributor directory. | תקציר פשוט שמוצג מעל ספריית המתמללים. |
| `admin.helpers.contributors_page_enabled` | Disabled contributor pages return 404 and skip contributor-focused homepage sections. | דפי מתמללים מושבתים מחזירים 404 ומדלגים על מקטעי דף בית ממוקדי מתמללים. | Keep | Disabled contributor pages return 404 and skip contributor-focused homepage sections. | דפי מתמללים מושבתים מחזירים 404 ומדלגים על מקטעי דף בית ממוקדי מתמללים. |
| `admin.helpers.contributors_page_item_label_plural` | Public plural label used for related items in contributor contexts. | תווית ברבים לפרקים בהקשרי מתמללים. | Keep | Public plural label used for related items in contributor contexts. | תווית ברבים לפרקים בהקשרי מתמללים. |
| `admin.helpers.contributors_page_item_label_singular` | Public singular label used for related items in contributor contexts. | תווית ביחיד לפרקים בהקשרי מתמללים. | Keep | Public singular label used for related items in contributor contexts. | תווית ביחיד לפרקים בהקשרי מתמללים. |
| `admin.helpers.contributors_page_label_plural` | Public plural label such as Contributors or Transcribers. This does not rename the Author model. | תווית ברבים כגון מתמללים. הדבר אינו משנה את מודל Author. | Keep | Public plural label such as Contributors or Transcribers. This does not rename the Author model. | תווית ברבים כגון מתמללים. הדבר אינו משנה את מודל Author. |
| `admin.helpers.contributors_page_label_singular` | Public singular label such as Contributor or Transcriber. This does not rename the Author model. | תווית ביחיד כגון מתמלל או מתמלל. הדבר אינו משנה את מודל Author. | Keep | Public singular label such as Contributor or Transcriber. This does not rename the Author model. | תווית ביחיד כגון מתמלל או מתמלל. הדבר אינו משנה את מודל Author. |
| `admin.helpers.contributors_page_title` | Public H1 for the canonical contributor directory. | כותרת H1 לספריית המתמללים הקנונית. | Keep | Public H1 for the canonical contributor directory. | כותרת H1 לספריית המתמללים הקנונית. |
| `admin.helpers.edit_effective_transcription_action` | The edit target resolves as effective published transcription, then featured transcription, then latest transcription. | יעד העריכה נבחר לפי תמלול אפקטיבי שפורסם, אחר כך תמלול נבחר, ואחר כך התמלול האחרון. | Keep | The edit target resolves as effective published transcription, then featured transcription, then latest transcription. | יעד העריכה נבחר לפי תמלול אפקטיבי שפורסם, אחר כך תמלול נבחר, ואחר כך התמלול האחרון. |
| `admin.helpers.featured_transcription` | Only same-item transcriptions can be selected. This selector appears after an item has more than one transcription. | ניתן לבחור רק תמלולים של אותו פרק. הבורר מוצג לאחר שלפרק יש יותר מתמלול אחד. | Keep | Only same-item transcriptions can be selected. This selector appears after an item has more than one transcription. | ניתן לבחור רק תמלולים של אותו פרק. הבורר מוצג לאחר שלפרק יש יותר מתמלול אחד. |
| `admin.helpers.item_page_show_transcript_actions_menu` | When enabled, the public transcript details row shows a local actions menu for timestamps, speakers, copy link, font size, fullscreen, and player controls. | כאשר מופעל, שורת פרטי התמלול מציגה תפריט פעולות מקומי לזמנים, דוברים, העתקת קישור, גודל טקסט, מסך מלא והצגת הנגן. | Keep | When enabled, the public transcript details row shows a local actions menu for timestamps, speakers, copy link, font size, fullscreen, and player controls. | כאשר מופעל, שורת פרטי התמלול מציגה תפריט פעולות מקומי לזמנים, דוברים, העתקת קישור, גודל טקסט, מסך מלא והצגת הנגן. |
| `admin.helpers.item_page_transcription_date_enabled` | Controls whether the selected transcription publish date is available to the episode metadata renderer. | קובע אם תאריך פרסום התמלול הנבחר זמין למרנדר מטא-דאטה של דף הפרק. | Keep | Controls whether the selected transcription publish date is available to the episode metadata renderer. | קובע אם תאריך פרסום התמלול הנבחר זמין למרנדר מטא-דאטה של דף הפרק. |
| `admin.helpers.podcasts_group_page_show_episode_authors` | Shows credited authors/transcribers on episode cards when available. | מציג מתמללים בכרטיסי פרקים כאשר קיימים. | Keep | Shows credited authors/transcribers on episode cards when available. | מציג מתמללים בכרטיסי פרקים כאשר קיימים. |
| `admin.helpers.podcasts_group_page_show_episode_effective_date` | Shows the effective public transcription date on episode cards. | מציג את תאריך התמלול ההאפקטיבי בכרטיסי הפרקים. | Keep | Shows the effective public transcription date on episode cards. | מציג את תאריך התמלול ההאפקטיבי בכרטיסי הפרקים. |
| `admin.helpers.podcasts_page_show_episode_count` | Shows the count of public items with published transcriptions. | מציג את מספר הפרקים המפורסמים עם תמלולים שפורסמו. | Keep | Shows the count of public items with published transcriptions. | מציג את מספר הפרקים המפורסמים עם תמלולים שפורסמו. |
| `admin.helpers.public_front_card_templates` | Global reusable templates for content item, content group, and contributor cards. | תבניות גלובליות לשימוש חוזר עבור פרקים, פודקאסטים ומתמללים. | Keep | Global reusable templates for content item, content group, and contributor cards. | תבניות גלובליות לשימוש חוזר עבור פרקים, פודקאסטים ומתמללים. |
| `admin.helpers.public_front_transcription_display` | Choose whether item cards show only effective transcription data or also a count badge when multiple public transcriptions exist. | קובע אם כרטיסי פרקים יציגו רק את התמלול האפקטיבי או גם תגית ספירה כשיש כמה תמלולים ציבוריים. | Keep | Choose whether item cards show only effective transcription data or also a count badge when multiple public transcriptions exist. | קובע אם כרטיסי פרקים יציגו רק את התמלול האפקטיבי או גם תגית ספירה כשיש כמה תמלולים ציבוריים. |
| `admin.helpers.public_transcription_policy_count_mode` | Controls public counts, contributor ordering, and aggregate totals. Featured-only counts one effective transcription per public item. | קובע ספירות ציבוריות, דירוג מתמללים וסיכומים. במצב תמלול נבחר נספר תמלול אפקטיבי אחד לכל פרק ציבורי. | Suppressed in single | — | — |
| `admin.helpers.public_transcription_policy_public_mode` | Controls which transcription set public selectors and filters treat as visible. | קובע איזו קבוצת תמלולים נחשבת גלויה עבור בוררים וסינונים ציבוריים. | Suppressed in single | — | — |
| `admin.helpers.public_transcription_policy_show_multiple_transcriptions_on_item_page` | Reserved for item-page rendering support. M4 decides where multiple transcript groups are shown. | שמור לתמיכת רינדור בדף פרק. M4 יחליט איפה להציג כמה קבוצות תמלול. | Suppressed in single | — | — |
| `admin.helpers.set_featured_transcription_action` | Sets this published transcription as the item featured transcript. Draft transcriptions remain private and cannot become publicly effective. | מגדיר את התמלול המפורסם הזה כתמלול הנבחר של הפרק. תמלולי טיוטה נשארים פרטיים ואינם יכולים להפוך לפעילים. | Suppressed in single | — | — |
| `admin.helpers.show_episode_workspace_hint_line` | Shows a note when alternate transcriptions exist but are hidden from the single-transcription workspace. | מציג הערה כאשר קיימים תמלולים חלופיים שמוסתרים מסביבת העבודה עם התמלול היחיד. | Suppressed in single | — | — |
| `admin.helpers.show_episode_workspace_language_code` | Shows the BCP-47 language code field inside the workspace transcription section. | מציג את שדה קוד השפה BCP-47 בתוך מקטע התמלול בסביבת העבודה. | Keep | Shows the BCP-47 language code field inside the workspace transcription section. | מציג את שדה קוד השפה BCP-47 בתוך מקטע התמלול בסביבת העבודה. |
| `admin.helpers.top_transcribers_enabled` | Controls whether homepage top-transcriber sections render. | קובע אם מקטעי מתמללים מובילים בדף הבית יוצגו. | Keep | Controls whether homepage top-transcriber sections render. | קובע אם מקטעי מתמללים מובילים בדף הבית יוצגו. |
| `admin.helpers.top_transcribers_layout` | Step 10 supports the horizontal selector with preview below it. | שלב 10 תומך בבורר אופקי עם תצוגה מתחתיו. | Keep | Step 10 supports the horizontal selector with preview below it. | שלב 10 תומך בבורר אופקי עם תצוגה מתחתיו. |
| `admin.helpers.top_transcribers_limit` | Maximum contributors shown in top-transcriber selectors. | מספר המתמללים המרבי שיוצג בבורר מתמללים מובילים. | Keep | Maximum contributors shown in top-transcriber selectors. | מספר המתמללים המרבי שיוצג בבורר מתמללים מובילים. |
| `admin.helpers.top_transcribers_preview_default_page_size` | Initial number of items shown in a selected top-transcriber preview. | מספר הפרקים הראשוני שמוצג בתצוגת מתמלל מוביל נבחר. | Keep | Initial number of items shown in a selected top-transcriber preview. | מספר הפרקים הראשוני שמוצג בתצוגת מתמלל מוביל נבחר. |
| `admin.helpers.top_transcribers_preview_grid_columns` | Maximum number of selected contributor preview items per desktop row. | מספר מרבי של פרקי מתמלל נבחר בשורת דסקטופ. | Keep | Maximum number of selected contributor preview items per desktop row. | מספר מרבי של פרקי מתמלל נבחר בשורת דסקטופ. |
| `admin.helpers.top_transcribers_preview_page_size_options` | Allowed page sizes for top-transcriber previews. Step 10 supports 5, 10, and 15. | גדלי עמוד מותרים לתצוגות מתמללים מובילים. שלב 10 תומך ב-5, 10 ו-15. | Keep | Allowed page sizes for top-transcriber previews. Step 10 supports 5, 10, and 15. | גדלי עמוד מותרים לתצוגות מתמללים מובילים. שלב 10 תומך ב-5, 10 ו-15. |
| `admin.helpers.top_transcribers_show_count_badge` | Shows published transcription/item count metadata in the selected contributor preview. | מציג מטא-דאטה של מספר תמלולים ופרקים בתצוגת המתמלל הנבחר. | Keep | Shows published transcription/item count metadata in the selected contributor preview. | מציג מטא-דאטה של מספר תמלולים ופרקים בתצוגת המתמלל הנבחר. |
| `admin.helpers.top_transcribers_show_full_page_link` | Shows the contributor page link inside the selected contributor preview. | מציג קישור לדף המתמלל בתוך התצוגה של המתמלל הנבחר. | Keep | Shows the contributor page link inside the selected contributor preview. | מציג קישור לדף המתמלל בתוך התצוגה של המתמלל הנבחר. |
| `admin.helpers.transcript_markdown` | Store Markdown only. Attachments and raw embeds are disabled here. | יש לשמור Markdown בלבד. קבצים מצורפים והטמעות גולמיות מושבתים כאן. | Variant | Store the episode transcript as Markdown only. Attachments and raw embeds are disabled. | יש לשמור את תמלול הפרק כ-Markdown בלבד. קבצים מצורפים והטמעות גולמיות מושבתים. |
| `admin.helpers.transcription_author` | Legacy primary transcriber compatibility field for this transcription record. | שדה תאימות למתמלל הראשי ברשומת התמלול הזו. | Keep | Legacy primary transcriber compatibility field for this transcription record. | שדה תאימות למתמלל הראשי ברשומת התמלול הזו. |
| `admin.helpers.transcription_mode` | Single mode hides multi-transcription controls and preserves the one-transcript workspace. Multi mode exposes eligible multi-transcription controls by role. | מצב יחיד מסתיר בקרות ריבוי תמלולים ושומר על סביבת עבודה עם תמלול אחד. מצב ריבוי חושף בקרות מתאימות לפי תפקיד. | Keep | Single mode hides multi-transcription controls and preserves the one-transcript workspace. Multi mode exposes eligible multi-transcription controls by role. | מצב יחיד מסתיר בקרות ריבוי תמלולים ושומר על סביבת עבודה עם תמלול אחד. מצב ריבוי חושף בקרות מתאימות לפי תפקיד. |
| `admin.helpers.transcription_presentation_mode` | Controls how the transcription section is presented inside the workspace form. | קובע איך מקטע התמלול מוצג בתוך טופס סביבת העבודה. | Keep | Controls how the transcription section is presented inside the workspace form. | קובע איך מקטע התמלול מוצג בתוך טופס סביבת העבודה. |
| `admin.helpers.transcription_published_at` | Optional publication date-time in the Asia/Jerusalem admin timezone. | תאריך ושעת פרסום אופציונליים באזור הזמן הניהולי Asia/Jerusalem. | Variant | Optional publication date-time for the episode transcript in the Asia/Jerusalem admin timezone. | תאריך ושעת פרסום אופציונליים לתמלול הפרק באזור הזמן הניהולי Asia/Jerusalem. |
| `admin.helpers.transcription_status` | Drafts are admin-only. Published transcriptions can become public if the item and group are also published. | טיוטות זמינות למנהלים בלבד. תמלולים שפורסמו יכולים להפוך למפורסמים אם גם הפרק והפודקאסט מפורסמים. | Variant | Draft episode transcripts are admin-only. A published transcript can be public when the episode and podcast are published. | טיוטת תמלול הפרק זמינה למנהלים בלבד. תמלול שפורסם יכול להיות ציבורי כשהפרק והפודקאסט מפורסמים. |
| `admin.helpers.transcription_title` | Optional transcript title used to distinguish alternate versions. | כותרת תמלול אופציונלית המשמשת להבחנה בין גרסאות חלופיות. | Variant | Optional title for the episode transcript. | כותרת אופציונלית לתמלול הפרק. |
| `admin.helpers.transcription_transcribers` | Choose one or more transcribers. Public episode transcriber display comes from this transcription relationship. | בחרו מתמלל אחד או יותר. תצוגת המתמללים הציבורית של הפרק מגיעה מהקשר הזה של התמלול. | Variant | Choose the transcribers credited for the episode transcript. | בחרו את המתמללים שיקבלו קרדיט על תמלול הפרק. |
| `admin.homepage_section_type.top_transcribers` | Top transcribers | המתמללים המובילים | Keep | Top transcribers | המתמללים המובילים |
| `admin.import.columns.featured_transcription_reference_key` | Featured transcription reference key | מפתח ייחוס של תמלול נבחר | Keep | Featured transcription reference key | מפתח ייחוס של תמלול נבחר |
| `admin.import.columns.primary_transcriber_reference_key` | Primary transcriber reference key | מפתח ייחוס של מתמלל ראשי | Keep | Primary transcriber reference key | מפתח ייחוס של מתמלל ראשי |
| `admin.import.columns.transcriber_names` | Transcriber names | שמות מתמללים | Keep | Transcriber names | שמות מתמללים |
| `admin.import.columns.transcriber_reference_keys` | Transcriber reference keys | מפתחות ייחוס של מתמללים | Keep | Transcriber reference keys | מפתחות ייחוס של מתמללים |
| `admin.import.failures.create_found_existing_transcription` | Create-only import found an existing transcription for this item, author, and publication date. | ייבוא ליצירה בלבד מצא תמלול קיים עבור פרק, מחבר ותאריך פרסום אלה. | Keep | Create-only import found an existing transcription for this item, author, and publication date. | ייבוא ליצירה בלבד מצא תמלול קיים עבור פרק, מחבר ותאריך פרסום אלה. |
| `admin.import.failures.missing_transcriber` | A transcription import row must include at least one transcriber reference key or name. | שורת ייבוא של תמלול חייבת לכלול לפחות מפתח ייחוס או שם של מתמלל אחד. | Keep | A transcription import row must include at least one transcriber reference key or name. | שורת ייבוא של תמלול חייבת לכלול לפחות מפתח ייחוס או שם של מתמלל אחד. |
| `admin.import.failures.published_transcription_requires_markdown` | Published transcriptions must include transcript Markdown. | תמלולים שפורסמו חייבים לכלול Markdown של התמלול. | Keep | Published transcriptions must include transcript Markdown. | תמלולים שפורסמו חייבים לכלול Markdown של התמלול. |
| `admin.import.failures.unresolved_featured_transcription` | Could not resolve same-item featured transcription reference key :reference_key. | לא ניתן היה לפתור את מפתח הייחוס של התמלול הנבחר מאותו פרק :reference_key. | Keep | Could not resolve same-item featured transcription reference key :reference_key. | לא ניתן היה לפתור את מפתח הייחוס של התמלול הנבחר מאותו פרק :reference_key. |
| `admin.import.failures.unresolved_transcriber_names` | Could not resolve transcriber names: :names. | לא ניתן היה לפתור את שמות המתמללים: :names. | Keep | Could not resolve transcriber names: :names. | לא ניתן היה לפתור את שמות המתמללים: :names. |
| `admin.import.failures.unresolved_transcribers` | Could not resolve transcriber reference keys: :reference_keys. | לא ניתן היה לפתור את מפתחות הייחוס של המתמללים: :reference_keys. | Keep | Could not resolve transcriber reference keys: :reference_keys. | לא ניתן היה לפתור את מפתחות הייחוס של המתמללים: :reference_keys. |
| `admin.import.failures.update_missing_transcription` | Update-only import could not find a transcription for this item, author, and publication date. | ייבוא לעדכון בלבד לא מצא תמלול עבור פרק, מחבר ותאריך פרסום אלה. | Keep | Update-only import could not find a transcription for this item, author, and publication date. | ייבוא לעדכון בלבד לא מצא תמלול עבור פרק, מחבר ותאריך פרסום אלה. |
| `admin.import.options.relation_mode_helper` | Replace treats provided relation cells as the complete enabled set. Add only attaches missing values and never detaches. Blank relation cells leave existing categories, tags, and transcribers unchanged in both modes. | החלפה מתייחסת לתאי קשרים שמולאו כאל הרשימה הפעילה המלאה. הוספה בלבד מצרפת ערכים חסרים ולא מסירה קיימים. תאי קשרים ריקים משאירים קטגוריות, תגיות ומתמללים ללא שינוי בשני המצבים. | Keep | Replace treats provided relation cells as the complete enabled set. Add only attaches missing values and never detaches. Blank relation cells leave existing categories, tags, and transcribers unchanged in both modes. | החלפה מתייחסת לתאי קשרים שמולאו כאל הרשימה הפעילה המלאה. הוספה בלבד מצרפת ערכים חסרים ולא מסירה קיימים. תאי קשרים ריקים משאירים קטגוריות, תגיות ומתמללים ללא שינוי בשני המצבים. |
| `admin.item_page_info_fields.transcribers` | Transcribers | מתמללים | Keep | Transcribers | מתמללים |
| `admin.item_page_info_fields.transcription_count` | Transcription count | מספר תמלולים | Keep | Transcription count | מספר תמלולים |
| `admin.item_page_info_fields.transcription_date` | Transcription date | תאריך תמלול | Keep | Transcription date | תאריך תמלול |
| `admin.labels.transcription_context` | :title [:status] | :title [:status] | Keep | :title [:status] | :title [:status] |
| `admin.labels.untitled_transcription` | Transcription #:id | תמלול מספר :id | Keep | Transcription #:id | תמלול מספר :id |
| `admin.labels.visibility_transcription` | Published workspace transcription: :state | תמלול סביבת עבודה מפורסם: :state | Variant | Published episode transcript: :state | תמלול הפרק פורסם: :state |
| `admin.labels.workspace_hidden_transcriptions_hint` | :count alternate transcription remains attached and hidden from this workspace.\|:count alternate transcriptions remain attached and hidden from this workspace. | תמלול חלופי אחד נשאר מחובר ומוסתר מסביבת העבודה.\|:count תמלולים חלופיים נשארים מחוברים ומוסתרים מסביבת העבודה. | Suppressed in single | — | — |
| `admin.layouts.transcript_first` | Transcript first | תמלול תחילה | Keep | Transcript first | תמלול תחילה |
| `admin.modals.edit_effective_transcription` | Edit transcription: :title [:status] | עריכת תמלול: :title [:status] | Keep | Edit transcription: :title [:status] | עריכת תמלול: :title [:status] |
| `admin.modals.edit_effective_transcription_missing` | Edit transcription | עריכת תמלול | Keep | Edit transcription | עריכת תמלול |
| `admin.modals.replace_workspace_transcription` | Replace workspace transcription | החלפת תמלול סביבת העבודה | Keep | Replace workspace transcription | החלפת תמלול סביבת העבודה |
| `admin.notifications.content_item_created_add_transcription` | Continue on the edit page and add a transcription from the Transcriptions tab. | יש להמשיך בדף העריכה ולהוסיף תמלול מלשונית התמלולים. | Keep | Continue on the edit page and add a transcription from the Transcriptions tab. | יש להמשיך בדף העריכה ולהוסיף תמלול מלשונית התמלולים. |
| `admin.notifications.effective_transcription_saved` | Transcription updated. | התמלול עודכן. | Variant | Episode transcript saved | תמלול הפרק נשמר |
| `admin.notifications.featured_transcription_saved` | Featured transcription updated. | התמלול הנבחר עודכן. | Keep | Featured transcription updated. | התמלול הנבחר עודכן. |
| `admin.notifications.first_transcription_featured` | If this is the first transcription for the item, it was selected as featured automatically. | אם זהו התמלול הראשון של הפרק, הוא נבחר כתמלול נבחר באופן אוטומטי. | Variant | The episode transcript was selected automatically. | תמלול הפרק נבחר אוטומטית. |
| `admin.notifications.transcription_created` | Transcription created. | התמלול נוצר. | Variant | Episode transcript created | תמלול הפרק נוצר |
| `admin.notifications.transcription_not_found` | No transcription could be resolved for this episode. | לא נמצא תמלול מתאים לפרק הזה. | Keep | No transcription could be resolved for this episode. | לא נמצא תמלול מתאים לפרק הזה. |
| `admin.notifications.workspace_transcription_replaced` | Workspace transcription replaced. | תמלול סביבת העבודה הוחלף. | Keep | Workspace transcription replaced. | תמלול סביבת העבודה הוחלף. |
| `admin.public_display_section_sorts.latest_transcription` | Latest transcription | תמלול אחרון | Keep | Latest transcription | תמלול אחרון |
| `admin.public_display_section_sorts.oldest_transcription` | Oldest transcription | תמלול ישן | Keep | Oldest transcription | תמלול ישן |
| `admin.public_display_section_sorts.top_transcriptions` | Top transcriptions | מספר תמלולים | Keep | Top transcriptions | מספר תמלולים |
| `admin.public_display_section_sources.contributors` | Contributors | מתמללים | Keep | Contributors | מתמללים |
| `admin.public_display_section_sources.top_transcribers` | Top transcribers | מתמללים מובילים | Keep | Top transcribers | מתמללים מובילים |
| `admin.public_front_routes.contributors` | Contributors | מתמללים | Keep | Contributors | מתמללים |
| `admin.public_front_routes.request_transcription` | Request transcription | בקשת תמלול | Keep | Request transcription | בקשת תמלול |
| `admin.public_front_routes.volunteer_transcriber` | Volunteer transcriber | התנדבות לתמלול | Keep | Volunteer transcriber | התנדבות לתמלול |
| `admin.public_transcription_policy_modes.all_published` | All published transcriptions | כל התמלולים שפורסמו | Suppressed in single | — | — |
| `admin.public_transcription_policy_modes.featured_only` | Featured / effective transcription only | תמלול נבחר / אפקטיבי בלבד | Suppressed in single | — | — |
| `admin.resources.author.navigation` | Transcribers | מתמללים | Keep | Transcribers | מתמללים |
| `admin.resources.transcription.navigation` | Transcriptions | תמלולים | Variant (review) | Episode transcripts | תמלולי פרקים |
| `admin.resources.transcription.plural` | Transcriptions | תמלולים | Variant (review) | Episode transcripts | תמלולי פרקים |
| `admin.resources.transcription.singular` | Transcription | תמלול | Variant (review) | Episode transcript | תמלול הפרק |
| `admin.sections.episode_workspace_transcription` | Workspace transcription | תמלול סביבת העבודה | Variant | Episode transcript | תמלול הפרק |
| `admin.sections.featured_transcription` | Featured transcription | תמלול נבחר | Keep | Featured transcription | תמלול נבחר |
| `admin.sections.item_page_transcription_date` | Transcription date | תאריך תמלול | Keep | Transcription date | תאריך תמלול |
| `admin.sections.public_front_contributor_cards` | Contributor cards | כרטיסי מתמללים | Keep | Contributor cards | כרטיסי מתמללים |
| `admin.sections.public_front_contributor_page_items` | Contributor page items | פרקי דף מתמלל | Keep | Contributor page items | פרקי דף מתמלל |
| `admin.sections.public_front_contributors_directory` | Contributor directory | ספריית מתמללים | Keep | Contributor directory | ספריית מתמללים |
| `admin.sections.public_front_contributors_identity` | Contributor labels | תוויות מתמללים | Keep | Contributor labels | תוויות מתמללים |
| `admin.sections.public_front_contributors_page` | Contributors page | דף מתמללים | Keep | Contributors page | דף מתמללים |
| `admin.sections.public_front_item_page_transcript_controls` | Transcript controls | פקדי תמלול | Keep | Transcript controls | פקדי תמלול |
| `admin.sections.public_front_top_transcribers` | Top transcribers | מתמללים מובילים | Keep | Top transcribers | מתמללים מובילים |
| `admin.sections.public_transcription_policy` | Public transcription policy | מדיניות תמלולים ציבורית | Suppressed in single | — | — |
| `admin.sections.transcript` | Transcript | תמלול | Variant | Episode transcript | תמלול הפרק |
| `admin.settings_backup_snapshot_screens.contributor` | Contributor | מתמלל | Keep | Contributor | מתמלל |
| `admin.settings_backup_snapshot_screens.contributors` | Contributors | מתמללים | Keep | Contributors | מתמללים |
| `admin.sort.latest_transcription` | Latest transcription first | התמלולים החדשים תחילה | Keep | Latest transcription first | התמלולים החדשים תחילה |
| `admin.sort.oldest_transcription` | Oldest transcription first | התמלולים הישנים תחילה | Keep | Oldest transcription first | התמלולים הישנים תחילה |
| `admin.summaries.homepage_section_top_transcribers` | Top transcribers section: no target is required. Public contributors are ranked by published transcriptions on public items. | מקטע מתמללים מובילים: אין צורך ביעד. מתמללים מדורגים לפי תמלולים שפורסמו בפרקים. | Keep | Top transcribers section: no target is required. Public contributors are ranked by published transcriptions on public items. | מקטע מתמללים מובילים: אין צורך ביעד. מתמללים מדורגים לפי תמלולים שפורסמו בפרקים. |
| `admin.tabs.public_content_settings.contributors` | Contributors | מתמללים | Keep | Contributors | מתמללים |
| `admin.tabs.transcriptions` | Transcriptions | תמלולים | Keep (intentional relation manager) | Transcriptions | תמלולים |
| `admin.tabs.transcriptions_badge_tooltip` | Number of transcriptions for this item. | מספר התמלולים של פרק זה. | Keep (intentional relation manager) | Number of transcriptions for this item. | מספר התמלולים של פרק זה. |
| `admin.transcription_display.effective_only` | Effective transcription only | תמלול אפקטיבי בלבד | Keep | Effective transcription only | תמלול אפקטיבי בלבד |
| `admin.transcription_display.effective_plus_count` | Effective transcription + count badge | תמלול אפקטיבי + תגית ספירה | Suppressed in single | — | — |
| `admin.transcription_modes.multi` | Multiple transcriptions | כמה תמלולים | Keep | Multiple transcriptions | כמה תמלולים |
| `admin.transcription_modes.single` | Single transcription | תמלול יחיד | Keep | Single transcription | תמלול יחיד |
| `admin.transcription_presentation_modes.collapsible` | Collapsible section | מקטע נפתח | Keep | Collapsible section | מקטע נפתח |
| `admin.transcription_presentation_modes.modal` | Modal | מודל | Keep | Modal | מודל |
| `admin.transcription_presentation_modes.slideover` | Slide-over | לוח צד | Keep | Slide-over | לוח צד |
| `admin.user_roles.transcriber` | Transcriber | מתמלל | Keep | Transcriber | מתמלל |
| `admin.filters.transcription_history` | — | — | New single-resource control | Transcript history | היסטוריית תמלולים |
| `admin.helpers.transcription_content_item` | — | — | New single-only resource helper | Choose the episode that owns this transcript. | בחרו את הפרק שאליו שייך התמלול. |
| `admin.validation.transcription_already_exists` | — | — | New shared validation | This episode already has its transcript | לפרק כבר יש תמלול |

## Explicit review flags

1. The single standalone-resource noun is “Episode transcript” /
   “תמלול הפרק”. It is intentionally more explicit than the generic base noun.
2. Technical import/export headers keep row-oriented transcription language;
   they describe portable database records, not a public per-episode count.
3. Contributor relationship words (“transcriber”, “transcription title”) stay
   where they describe the actual credited transcript document. Only counts,
   headings, current-row admin surfaces, and per-episode plurality are varied.
