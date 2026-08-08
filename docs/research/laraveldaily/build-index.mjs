#!/usr/bin/env node
/**
 * Build index.md — a grep-first index of every LaravelDaily lesson in scope.
 *
 *   node build-index.mjs
 *
 * Reads inventory.json (written by `scrape.mjs --inventory`). If raw/ exists it
 * enriches each row with body size, link/comment counts, transcript presence and
 * the API identifiers actually mentioned in the lesson — which is what makes the
 * index worth grepping rather than just browsing.
 *
 * Design: ONE LINE PER LESSON, every searchable term on that line. `grep -i observer
 * index.md` should return the lessons, not a heading you then have to read around.
 */

import { readFile, writeFile, readdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const RAW = path.join(HERE, 'raw');

/** Course format isn't on the course page, only on the catalogue card. */
const FORMAT = {
  'filament-4': 'video', 'laravel-eloquent-expert': 'video+text', 'roles-permissions': 'video',
  'queues-laravel': 'text', 'testing-laravel': 'text', 'multi-language-laravel': 'text',
  'laravel-http-client-api': 'video', 'tailwind-laravel': 'text', 'laravel-13-beginners': 'video',
  'laravel-from-scratch': 'video', 'php-laravel': 'text', 'livewire-beginners': 'video',
  'alpine-js': 'video', 'laravel-cms-review': 'video', 'api-laravel': 'video',
  'design-patterns': 'text', 'laravel-user-timezones': 'text', 'laravel-ai-sdk': 'video',
  'ai-agents-laravel-2026': 'video', 'laravel-projects-structure': 'text',
  'structuring-databases-laravel': 'text', 'laravel-security-composer': 'text',
  'laravel-saas': 'video', 'livewire-v4': 'video', 'laravel-reverb': 'text',
  'exceptions-errors-laravel': 'text', 'laravel-project-process': 'text',
};

/** slug/course keyword -> subject. First match wins, so order matters. */
const TOPICS = [
  ['Filament', /filament|shield|infolist|relation-manager|panel|widget|resource/],
  ['Livewire & Alpine', /livewire|wire-|alpine|island|x-data|intersect/],
  ['Testing', /test|pest|tdd|coverage|assert|factor(y|ies)|fake|mutation/],
  ['Eloquent & relations', /eloquent|relation|belongsto|hasmany|hasone|polymorphic|ofmany|withcount|eager|n1|scope|observer|mutator|accessor|casts|prunable|model/],
  ['Database & schema', /database|migration|schema|index|uuid|ulid|json-column|enum-db|normaliz|pivot|foreign-key|primary-key|seed|eav|parent-children/],
  ['Queues & jobs', /queue|job|horizon|supervisor|batch|chain|worker|dispatch|idempotent|unique-jobs|failed/],
  ['Security & auth', /secur|auth|permission|role|gate|policy|token|sanctum|secret|rotation|supply-chain|composer-habits|mfa|multi-factor|tenan/],
  ['Errors & exceptions', /exception|error|try-catch|handler|debug/],
  ['API & HTTP', /api|http-client|webhook|rest|json|cors|throttl|scribe|openapi|swagger|versioning|stripe|cloudinary|discord/],
  ['Localisation & time', /translat|locale|language|timezone|multi-language|date|currenc/],
  ['Architecture & patterns', /pattern|service|action|dto|repository|pipeline|solid|module|ddd|structure|refactor|helper|extract|event|listener/],
  ['Frontend & UI', /tailwind|blade|component|css|layout|theme|design|breakpoint|color/],
  ['AI & tooling', /\bai\b|agent|boost|claude|codex|cursor|junie|copilot|opencode|llm|prompt|skill/],
  ['Realtime', /reverb|broadcast|real-time|websocket|online|chat/],
  ['Process & deploy', /deploy|ci-cd|github|pint|larastan|static-analysis|sentry|backup|envoyer|forge|staging|process|launch/],
  ['PHP language', /php-|classes|traits|interface|closure|visibility|static|ternary|array-functions|string-functions|variable-types/],
];

const topicOf = (slug, course) => {
  const hay = `${slug} ${course}`.toLowerCase();
  for (const [name, re] of TOPICS) if (re.test(hay)) return name;
  return 'General';
};

/**
 * "Also touches" — cross-cutting concepts mined from the lesson BODY (and transcript),
 * as opposed to TOPICS, which classifies the primary subject from the slug alone.
 *
 * Purpose: surface the non-obvious. A Filament lesson that happens to demonstrate
 * factories, a queues lesson that leans on notifications, a SaaS lesson with a solid
 * Stripe-webhook section — grep for the concept and these lessons appear even though
 * their titles say nothing about it.
 *
 * Rules of thumb for the vocabulary: concrete nouns over vibes, tight word-boundary
 * regexes over substrings (¬ "cast" matching "broadcast"), and a mention threshold so
 * a single passing reference does not index.
 */
const CONCEPTS = [
  ['validation',    /\bvalidat(?:e|ion|ed|or)|form request|\brules\(\)/gi],
  ['authorization', /\bpolic(?:y|ies)\b|\bgate(?:s|::)|\bauthoriz|\bcan\(\)/gi],
  ['authentication',/\bauth\(\)|\blogin\b|\bregister(?:ed)?\b|\bsanctum\b|\bfortify\b|\bguard\b/gi],
  ['roles',         /\brole(?:s)?\b|\bpermission(?:s)?\b|\bspatie\/laravel-permission\b|\bshield\b/gi],
  ['notifications', /\bnotification(?:s)?\b|\bnotify\b|\bNotification::/gi],
  ['mail',          /\bmailable(?:s)?\b|\bMail::|\bemail(?:s)? (?:is |are )?sent\b|\benvelope\(\)/gi],
  ['queues',        /\bqueue(?:d|s)?\b|\bShouldQueue\b|\bjob(?:s)? (?:table|class|worker)|\bdispatch\(/gi],
  ['events',        /\bevent\(new\b|\blistener(?:s)?\b|\bobserver(?:s)?\b|\bObservedBy\b|\bbooted\(\)/gi],
  ['caching',       /\bcache(?:d|s)?\b|\bCache::|\bremember\(/gi],
  ['factories',     /\bfactor(?:y|ies)\b|\bfake\(\)|\bfaker\b|\bseeder(?:s)?\b|\bseed(?:ing)?\b/gi],
  ['testing',       /\bpest\b|\bphpunit\b|\bassert[A-Z]?|\bit\(['"]|\btest\(['"]|\bRefreshDatabase\b/gi],
  ['migrations',    /\bmigration(?:s)?\b|\bSchema::|\bforeignId\b|\bmigrate\b/gi],
  ['enums',         /\benum(?:s)?\b|\bBackedEnum\b|\bcases\(\)/gi],
  ['casts',         /\bcasts\(\)|\bprotected \$casts\b|\bAttribute::make\b|\baccessor(?:s)?\b|\bmutator(?:s)?\b/gi],
  ['relationships', /\bbelongsTo\b|\bhasMany\b|\bhasOne\b|\bbelongsToMany\b|\bmorph[A-Z]|\bpivot\b/gi],
  ['eager-loading', /\beager.load|\bwith\(\[|\bload\(\[?['"]|\bN\+1\b/gi],
  ['scopes',        /\bscope[A-Z][a-z]+|\bglobal scope(?:s)?\b|\blocal scope(?:s)?\b/gi],
  ['pagination',    /\bpaginat(?:e|ion|or)\b|\bsimplePaginate\b|\bcursorPaginate\b/gi],
  ['file-uploads',  /\bfile upload(?:s)?\b|\bUploadedFile\b|\bstoreAs\b|\bStorage::|\bmedialibrary\b/gi],
  ['api-resources', /\bApiResource\b|\bJsonResource\b|\bResource::collection\b|\bapi resource(?:s)?\b/gi],
  ['middleware',    /\bmiddleware(?:s)?\b/gi],
  ['routing',       /\bRoute::|\broute model binding\b|\bnamed route(?:s)?\b/gi],
  ['blade',         /\bblade\b|\b@foreach\b|\b@if\b|\bx-data\b|\bcomponent(?:s)? (?:class|view)\b/gi],
  ['livewire',      /\blivewire\b|\bwire:[a-z]/gi],
  ['filament',      /\bfilament\b|\bresource(?:s)? (?:page|class|form|table)\b/gi],
  ['tailwind',      /\btailwind\b|\bdark:|\bsm:|\bmd:|\blg:/gi],
  ['localization',  /\btranslat(?:e|ion|ions|able)\b|\b__\(['"]|\btrans\(|\blocale(?:s)?\b|\blang\b/gi],
  ['timezones',     /\btimezone(?:s)?\b|\bCarbon(?:Immutable)?::|\bnow\(\)|->format\(['"]/gi],
  ['scheduling',    /\bschedul(?:e|er|ed|ing)\b|\bcron\b|\beveryMinute\b|\bdaily\(\)/gi],
  ['websockets',    /\breverb\b|\bbroadcast(?:ing)?\b|\bwebsocket(?:s)?\b|\bEcho\b|\bpusher\b/gi],
  ['webhooks',      /\bwebhook(?:s)?\b/gi],
  ['http-client',   /\bHttp::|\bhttp client\b|\bexternal api\b|\b3rd.party api\b/gi],
  // Deliberately narrow: "billing"/"subscription" alone match AI-tool pricing talk
  // (Claude/Copilot plans), which tagged IDE lessons with `stripe` on the first run.
  ['stripe',        /\bstripe\b|\bcashier\b/gi],
  ['multi-tenancy', /\btenan(?:t|ts|cy)\b|\bteam(?:s)? (?:id|scope|switch)/gi],
  ['soft-deletes',  /\bsoft.?delete(?:s|d)?\b|\bSoftDeletes\b|\brestore(?:d)?\(\)|\bforceDelete\b/gi],
  ['transactions',  /\bDB::transaction\b|\btransaction(?:s)?\b|\brollback\b/gi],
  ['security',      /\bxss\b|\bcsrf\b|\bsql injection\b|\bsanitiz|\bescap(?:e|ing)\b|\bsecret(?:s)?\b/gi],
  ['performance',   /\bperformance\b|\bslow quer|\bmemory\b|\bbenchmark\b|\boptimiz/gi],
  ['debugging',     /\bdebugbar\b|\bray\b|\bdd\(|\bdump\(|\btinker\b|\blogs?\b/gi],
  ['packages',      /\bcomposer require\b|\bpackagist\b|\bvendor\/[a-z-]+\/[a-z-]+/gi],
  ['ai-agents',     /\bclaude\b|\bcodex\b|\bcursor\b|\bcopilot\b|\bboost\b|\bSKILL\.md\b|\bmcp\b/gi],
  ['deployment',    /\bdeploy(?:ment|ed|ing)?\b|\bforge\b|\benvoyer\b|\bproduction server\b|\bci\/cd\b/gi],
];

/** Concepts a topic already implies — suppressed so "also touches" stays *also*. */
const TOPIC_IMPLIES = {
  'Testing': ['testing', 'factories'],
  'Eloquent & relations': ['relationships', 'casts', 'scopes', 'eager-loading'],
  'Database & schema': ['migrations', 'relationships'],
  'Queues & jobs': ['queues'],
  'Security & auth': ['roles', 'authorization', 'authentication', 'security'],
  'Errors & exceptions': ['debugging'],
  'API & HTTP': ['api-resources', 'routing', 'http-client', 'webhooks'],
  'Localisation & time': ['localization', 'timezones'],
  'Frontend & UI': ['tailwind', 'blade'],
  'Livewire & Alpine': ['livewire', 'blade'],
  'Filament': ['filament'],
  'Realtime': ['websockets'],
  'AI & tooling': ['ai-agents'],
  'Process & deploy': ['deployment'],
  'Architecture & patterns': [],
};

function alsoTouches(text, topic, cap = 8) {
  if (!text) return [];
  const suppressed = new Set(TOPIC_IMPLIES[topic] || []);
  const hits = [];
  for (const [name, re] of CONCEPTS) {
    if (suppressed.has(name)) continue;
    const n = (text.match(re) || []).length;
    if (n >= 3) hits.push([name, n]); // threshold: a passing mention doesn't index
  }
  return hits.sort((a, b) => b[1] - a[1]).slice(0, cap).map(([n]) => n);
}

/** Turn a slug into something readable, dropping the numeric noise the site adds. */
const humanise = (slug) => slug
  .replace(/^\d+-/, '').replace(/-\d+$/, '').replace(/-\d+-\d+$/, '')
  .replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

/**
 * API identifiers worth indexing, pulled from lesson body text.
 *
 * PHP attributes are matched WITHOUT requiring the closing bracket: real usage is
 * `#[ObservedBy([UserObserver::class])]`, so a `#\[[A-Za-z]+\]` pattern finds nothing.
 * That gap made the index return zero hits for the very names it exists to find.
 */
const IDENT = new RegExp([
  '#\\[[A-Z][A-Za-z]*',                       // #[ObservedBy, #[Tries
  '[A-Z][a-zA-Z]+::[a-zA-Z]+\\(\\)',          // Model::observe()
  '[A-Z][a-zA-Z]+\\(\\)',                     // Attribute()
  '[a-z]+[A-Z][a-zA-Z]*\\(\\)',               // latestOfMany(), ofMany()
  'wire:[a-z:.-]+',                           // wire:sort, wire:intersect
  'artisan [a-z:]+',                          // artisan make:observer
  '@[a-z]{3,}',                               // @island, @placeholder
  '\\$[a-z][a-zA-Z]{3,}',                     // $timeout, $uniqueFor
].join('|'), 'g');

function identifiers(body, limit = 12) {
  if (!body) return [];
  const counts = new Map();
  for (const m of body.match(IDENT) || []) {
    const k = m.trim();
    if (k.length < 4 || k.length > 40) continue;
    counts.set(k, (counts.get(k) || 0) + 1);
  }
  return [...counts.entries()]
    .sort((a, b) => b[1] - a[1]).slice(0, limit).map(([k]) => k);
}

const dateKey = (rel) => {
  if (!rel) return 0;
  const M = { Jan: 1, Feb: 2, Mar: 3, Apr: 4, May: 5, Jun: 6, Jul: 7, Aug: 8, Sep: 9, Oct: 10, Nov: 11, Dec: 12 };
  const [mo, yr] = rel.split(/\s+/);
  return (+yr) * 12 + (M[mo] || 0);
};

async function main() {
  const inv = JSON.parse(await readFile(path.join(HERE, 'inventory.json'), 'utf8'));

  const raw = {};
  if (existsSync(RAW)) {
    for (const f of await readdir(RAW)) {
      if (!f.endsWith('.json') || f.startsWith('_')) continue;
      try { raw[f.replace(/\.json$/, '')] = JSON.parse(await readFile(path.join(RAW, f), 'utf8')); } catch {}
    }
  }
  const enriched = Object.keys(raw).length;

  // Re-derive the paywall flag rather than trusting the scraper's per-lesson guess.
  //
  // The scraper flags any body not ending in terminal punctuation, which catches every
  // lesson that legitimately ends in a code block — 46 false positives against 0 real
  // ones on the first full run. A real paywall cut is *relative*: the teaser is a small
  // fraction of the course's typical lesson. So require both signals.
  const medians = {};
  for (const [course, c] of Object.entries(raw)) {
    const lens = Object.values(c.lessons || {}).map((l) => l.body?.length || 0)
      .filter(Boolean).sort((a, b) => a - b);
    medians[course] = lens.length ? lens[Math.floor(lens.length / 2)] : 0;
  }

  const rows = [];
  for (const [course, meta] of Object.entries(inv)) {
    for (const [i, slug] of (meta.slugs || []).entries()) {
      const r = raw[course]?.lessons?.[slug];
      const med = medians[course] || 0;
      const looksCut = r?.body
        && !/[.!?:)»"'`\]]\s*$/.test(r.body)
        && med > 0 && r.body.length < med * 0.4;
      rows.push({
        course,
        slug,
        n: i + 1,
        released: meta.released,
        format: FORMAT[course] || meta.format || '?',
        topic: topicOf(slug, course),
        title: r?.title || humanise(slug),
        chars: r?.body?.length || 0,
        links: r?.links?.length || 0,
        comments: r?.comments?.length || 0,
        tx: r?.transcript ? r.transcript.length : (r?.vimeo ? 0 : null),
        idents: identifiers(r?.body),
        also: alsoTouches(`${r?.body || ''}\n${r?.transcript || ''}`, topicOf(slug, course)),
        suspect: !!looksCut,
      });
    }
  }

  const byTopic = new Map();
  for (const r of rows) {
    if (!byTopic.has(r.topic)) byTopic.set(r.topic, []);
    byTopic.get(r.topic).push(r);
  }

  const out = [];
  out.push('# LaravelDaily lesson index');
  out.push('');
  out.push(`Generated by \`build-index.mjs\` — **${rows.length} lessons** across `
    + `**${Object.keys(inv).length} courses**.`);
  out.push('');
  out.push(enriched
    ? `Enriched from \`raw/\` (**${enriched}** courses scraped): sizes, counts and the API `
      + 'identifiers each lesson actually mentions.'
    : '**Not yet enriched** — run `node scrape.mjs` to add sizes, comment counts and API '
      + 'identifiers. Until then this indexes titles and topics only.');
  out.push('');
  out.push('## How to use it');
  out.push('');
  out.push('One line per lesson, every searchable term on that line — so grep returns the');
  out.push('lesson, not a heading you then have to read around.');
  out.push('');
  out.push('```bash');
  out.push('grep -i "observer"  docs/research/laraveldaily/index.md   # by concept');
  out.push('grep -i "ofMany()"  docs/research/laraveldaily/index.md   # by API identifier');
  out.push('grep -i "2026"      docs/research/laraveldaily/index.md   # by recency');
  out.push('grep -i "filament"  docs/research/laraveldaily/index.md   # by course/subject');
  out.push('```');
  out.push('');
  out.push('Columns per row: `course` · `released` · `format` · size · links · comments ·');
  out.push('transcript · identifiers · `also:` concepts. The `also:` list is mined from the');
  out.push('lesson body and transcript, minus what its topic already implies — it is where the');
  out.push('non-obvious lives: a Filament lesson demonstrating factories, a SaaS lesson with a');
  out.push('real Stripe-webhook section. `grep "also:.*factories"` finds material to learn from');
  out.push('that no title would surface. `**TRUNCATED**` marks a body that is both unterminated');
  out.push('*and* well under its course median — the shape of a paywall teaser. A body merely');
  out.push('ending in a code block is not flagged.');
  out.push('');
  out.push('**Freshness still applies.** A row being listed says nothing about whether its');
  out.push('advice is current — see [README](README.md) §2, especially the rule that lessons');
  out.push('older than Laravel 12 need their *pattern* checked, not just their APIs.');
  out.push('');

  for (const topic of [...byTopic.keys()].sort()) {
    const list = byTopic.get(topic).sort((a, b) =>
      dateKey(b.released) - dateKey(a.released) || a.course.localeCompare(b.course) || a.n - b.n);
    out.push(`## ${topic}  <sub>${list.length} lessons</sub>`);
    out.push('');
    for (const r of list) {
      const bits = [`\`${r.course}\``, r.released || '?', r.format];
      if (r.chars) bits.push(`${(r.chars / 1000).toFixed(1)}k`);
      if (r.links) bits.push(`${r.links}L`);
      if (r.comments) bits.push(`${r.comments}C`);
      if (r.tx) bits.push(`tx${(r.tx / 1000).toFixed(1)}k`);
      else if (r.tx === 0) bits.push('tx-none');
      const ids = r.idents.length ? ` — ${r.idents.join(' ')}` : '';
      const also = r.also?.length ? ` · also: ${r.also.join(' ')}` : '';
      const flag = r.suspect ? ' **TRUNCATED**' : '';
      out.push(`- [${r.title}](https://laraveldaily.com/lesson/${r.course}/${r.slug}) · `
        + `${bits.join(' · ')}${ids}${also}${flag}`);
    }
    out.push('');
  }

  await writeFile(path.join(HERE, 'index.md'), out.join('\n'));
  console.log(`index.md · ${rows.length} lessons · ${byTopic.size} topics`
    + ` · enriched from ${enriched} scraped course(s)`);
  for (const t of [...byTopic.keys()].sort()) console.log(`  ${String(byTopic.get(t).length).padStart(3)}  ${t}`);
}

main().catch((e) => { console.error(e); process.exit(1); });
