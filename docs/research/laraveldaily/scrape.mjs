#!/usr/bin/env node
/**
 * LaravelDaily course scraper.
 *
 * Pulls every lesson of the configured courses to disk: text body, curated links,
 * comments, and (for video lessons) the Vimeo auto-caption transcript.
 *
 * Auth: uses a PERSISTENT browser profile. The site's session cookie is httpOnly,
 * so curl cannot authenticate and the profile is the only practical carrier.
 *
 *   1. node scrape.mjs --login     → opens a window; YOU log in; close it when done.
 *   2. node scrape.mjs             → headless, scrapes everything, resumable.
 *
 * The script never handles credentials — you type them into the browser yourself.
 * The profile directory holds a live session: it lives outside the repo and must
 * never be committed.
 *
 * Other flags:
 *   --courses=a,b     only these course slugs
 *   --no-transcripts  skip the Vimeo caption pass (much faster)
 *   --out=DIR         output directory (default ./raw)
 *   --inventory       write inventory.json (course + lesson list) and exit.
 *                     Needs no premium session — course pages list lessons publicly.
 */

import { chromium } from 'playwright';
import { mkdir, writeFile, readFile, access } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const PROFILE = process.env.LD_PROFILE
  || path.join(process.env.HOME, '.cache', 'laraveldaily-scraper-profile');

const args = process.argv.slice(2);
const flag = (name) => args.some((a) => a === `--${name}`);
const opt = (name, dflt) => {
  const hit = args.find((a) => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : dflt;
};

const OUT = path.resolve(HERE, opt('out', 'raw'));
const WANT_TRANSCRIPTS = !flag('no-transcripts');
const BASE = 'https://laraveldaily.com';
const PAUSE = Number(opt('pause', '350')); // politeness delay, ms

const ALL_COURSES = [
  'roles-permissions', 'filament-4', 'laravel-eloquent-expert', 'testing-laravel',
  'queues-laravel', 'multi-language-laravel', 'laravel-http-client-api', 'tailwind-laravel',
  'laravel-projects-structure', 'structuring-databases-laravel', 'laravel-security-composer',
  'exceptions-errors-laravel', 'design-patterns', 'laravel-user-timezones', 'laravel-ai-sdk',
  'ai-agents-laravel-2026', 'livewire-v4', 'laravel-reverb', 'laravel-saas',
  'laravel-project-process', 'api-laravel', 'laravel-cms-review', 'alpine-js',
  'laravel-13-beginners', 'laravel-from-scratch', 'php-laravel', 'livewire-beginners',
];

const COURSES = opt('courses') ? opt('courses').split(',').filter(Boolean) : ALL_COURSES;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...a) => console.log(`[${new Date().toISOString().slice(11, 19)}]`, ...a);

/** Turn a WEBVTT payload into readable prose. */
function vttToProse(vtt) {
  const out = [];
  for (let line of vtt.split('\n')) {
    line = line.trim();
    if (!line || line.startsWith('WEBVTT') || /^\d+$/.test(line) || line.includes('-->')) continue;
    if (out[out.length - 1] !== line) out.push(line);
  }
  return out.join(' ');
}

/**
 * Extract from RAW server HTML, not the rendered DOM.
 *
 * This matters: the rendered page reveals the lesson body progressively, so reading
 * `.prose` after domcontentloaded silently truncates it — measured 3,333 chars against
 * 11,371 in the raw response for the same lesson, ending mid-sentence. The server sends
 * the whole thing up front, so parsing the response is both complete and much faster
 * (no navigation, no render).
 *
 * Parsing happens inside the browser purely to borrow its DOMParser.
 */
const PARSE = (html) => {
  const d = new DOMParser().parseFromString(html, 'text/html');
  const prose = d.querySelector('.prose');
  const vm = html.match(/player\.vimeo\.com\/video\/(\d+)\?h=([a-z0-9]+)/);
  return {
    title: (d.title || '').replace(/\s*\|\s*Laravel Daily\s*$/, '').trim(),
    body: prose ? prose.innerText.trim() : '',
    links: Array.from(d.querySelectorAll('.prose a[href]'))
      .map((a) => ({ href: a.getAttribute('href'), text: a.innerText.trim().slice(0, 120) })),
    comments: Array.from(d.querySelectorAll('.comments-comment')).map((c) => ({
      when: (c.querySelector('.comments-date')?.innerText || '').trim(),
      reply: !!c.closest('.comments-nested'),
      text: (c.querySelector('.comment-text')?.innerText || '').trim(),
    })).filter((c) => c.text),
    vimeo: vm ? { id: vm[1], hash: vm[2] } : null,
  };
};

/** Fetch raw HTML through the browser context so it carries the session cookie. */
async function rawHtml(ctx, url) {
  const res = await ctx.request.get(url, { timeout: 45000 });
  if (!res.ok()) throw new Error(`HTTP ${res.status()}`);
  return res.text();
}

async function lessonSlugs(ctx, page, course) {
  const html = await rawHtml(ctx, `${BASE}/course/${course}`);
  return page.evaluate((h) => [...new Set(
    Array.from(new DOMParser().parseFromString(h, 'text/html')
      .querySelectorAll('a[href*="/lesson/"]'))
      .map((a) => a.getAttribute('href').split('/').pop()),
  )], html);
}

/** Vimeo config needs a real navigation (403 to curl, referer-gated). */
async function transcript(page, { id, hash }) {
  await page.goto(`https://player.vimeo.com/video/${id}?h=${hash}`, { waitUntil: 'domcontentloaded' });
  const url = await page.evaluate(
    () => window.playerConfig?.request?.text_tracks?.[0]?.url || null,
  ).catch(() => null);
  if (!url) return null;
  // The caption URL is signed and public — plain fetch works, no CORS in Node.
  const res = await fetch(url);
  if (!res.ok) return null;
  return vttToProse(await res.text());
}

async function main() {
  await mkdir(OUT, { recursive: true });
  await mkdir(PROFILE, { recursive: true });

  const loginMode = flag('login');
  const ctx = await chromium.launchPersistentContext(PROFILE, {
    headless: !loginMode,
    viewport: { width: 1400, height: 900 },
  });
  const page = ctx.pages()[0] || await ctx.newPage();

  if (loginMode) {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    log('Log in in the window that just opened, then press Ctrl+C here when done.');
    await new Promise(() => {}); // hold open until interrupted
  }

  // Inventory mode: enumerate lessons only. Works without a premium session, so it
  // can be regenerated cheaply and feeds build-index.mjs before any scrape has run.
  if (flag('inventory')) {
    const inv = {};
    for (const course of COURSES) {
      try {
        const html = await rawHtml(ctx, `${BASE}/course/${course}`);
        const meta = await page.evaluate((h) => {
          const d = new DOMParser().parseFromString(h, 'text/html');
          const t = d.body.innerText.replace(/\s+/g, ' ');
          const m = t.match(/(\d+)\s*lessons?.{0,80}?((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s*20\d\d)/i);
          return {
            title: (d.title || '').replace(/\s*\|\s*Laravel Daily\s*$/, '').trim(),
            released: m?.[2] || null,
            format: /Text-based Course/.test(t) ? 'text' : (/Video Course/.test(t) ? 'video' : null),
            slugs: [...new Set(Array.from(d.querySelectorAll('a[href*="/lesson/"]'))
              .map((a) => a.getAttribute('href').split('/').pop()))],
          };
        }, html);
        inv[course] = meta;
        log(`inventory ${course}: ${meta.slugs.length} lessons · ${meta.released} · ${meta.format}`);
      } catch (e) {
        log(`!! ${course}: ${e.message}`);
      }
      await sleep(PAUSE);
    }
    await writeFile(path.join(HERE, 'inventory.json'), JSON.stringify(inv, null, 1));
    log(`wrote inventory.json · ${Object.values(inv).reduce((a, c) => a + c.slugs.length, 0)} lessons`);
    await ctx.close();
    return;
  }

  // --- Premium canary -------------------------------------------------------
  //
  // Do NOT test for strings like "Logout" or "Progress": they appear in the markup
  // for guests too, so that check passes while every lesson comes back as a paywall
  // teaser. Measured on one lesson: premium 11,371 chars, guest 3,322 — the guest
  // copy ends mid-sentence. A string check reports success at 29% capture.
  //
  // So the canary is a length assertion against a known lesson instead.
  const CANARY = {
    url: `${BASE}/lesson/laravel-reverb/notification-message-public-private`,
    premium: 11371,
    guest: 3322,
    floor: 8000, // comfortably above the teaser, below the real body
  };
  const canaryLen = await page.evaluate(
    (h) => (new DOMParser().parseFromString(h, 'text/html')
      .querySelector('.prose')?.innerText.trim().length ?? 0),
    await rawHtml(ctx, CANARY.url),
  );
  if (canaryLen < CANARY.floor) {
    log(`PAYWALLED — canary lesson returned ${canaryLen} chars, expected ~${CANARY.premium}.`);
    log(`(A guest session returns ~${CANARY.guest}.) This profile is not premium-authenticated.`);
    log('Run:  node scrape.mjs --login    then log in in the window that opens.');
    await ctx.close();
    process.exit(1);
  }
  log(`premium canary ok (${canaryLen} chars) · ${COURSES.length} courses · transcripts=${WANT_TRANSCRIPTS}`);

  const summary = [];

  for (const course of COURSES) {
    const file = path.join(OUT, `${course}.json`);
    let done = {};
    if (existsSync(file)) {
      try { done = JSON.parse(await readFile(file, 'utf8')); } catch { done = {}; }
    }
    done.course = course;
    done.scrapedAt = new Date().toISOString();
    done.lessons ||= {};

    let slugs;
    try {
      slugs = await lessonSlugs(ctx, page, course);
    } catch (e) {
      log(`!! ${course}: course page failed — ${e.message}`);
      continue;
    }
    done.lessonOrder = slugs;
    log(`${course}: ${slugs.length} lessons`);

    for (const [i, slug] of slugs.entries()) {
      const prior = done.lessons[slug];
      const needsBody = !prior?.body;
      const needsTx = WANT_TRANSCRIPTS && prior?.vimeo && prior.transcript === undefined;
      if (!needsBody && !needsTx) continue;

      try {
        if (needsBody) {
          const html = await rawHtml(ctx, `${BASE}/lesson/${course}/${slug}`);
          const data = await page.evaluate(PARSE, html);
          done.lessons[slug] = { n: i + 1, slug, ...data };
          await sleep(PAUSE);
        }
        const rec = done.lessons[slug];
        if (WANT_TRANSCRIPTS && rec.vimeo && rec.transcript === undefined) {
          rec.transcript = await transcript(page, rec.vimeo);
          await sleep(PAUSE);
        }
        // Per-lesson teaser heuristic: a paywalled body is cut mid-sentence, so a
        // body that neither ends in terminal punctuation nor is plausibly short is
        // suspect. Flagged, not dropped — judge from the summary.
        const r = done.lessons[slug];
        r.suspectTruncated = r.body.length > 200 && !/[.!?:)»"'`\]]\s*$/.test(r.body);
        log(`  ${String(i + 1).padStart(2)}/${slugs.length} ${slug} · ${r.body.length}c`
          + ` · ${r.links.length}L · ${r.comments.length}C`
          + (r.transcript ? ` · tx ${r.transcript.length}c` : r.vimeo ? ' · tx none' : '')
          + (r.suspectTruncated ? '  ** SUSPECT TRUNCATED **' : ''));
      } catch (e) {
        log(`  !! ${slug} — ${e.message}`);
        done.lessons[slug] = { n: i + 1, slug, error: e.message };
      }

      await writeFile(file, JSON.stringify(done, null, 1)); // checkpoint every lesson
    }

    const ls = Object.values(done.lessons).filter((l) => !l.error);
    summary.push({
      course,
      lessons: ls.length,
      chars: ls.reduce((a, l) => a + (l.body?.length || 0), 0),
      avg: ls.length ? Math.round(ls.reduce((a, l) => a + (l.body?.length || 0), 0) / ls.length) : 0,
      comments: ls.reduce((a, l) => a + (l.comments?.length || 0), 0),
      transcripts: ls.filter((l) => l.transcript).length,
      suspect: ls.filter((l) => l.suspectTruncated).length,
      errors: Object.values(done.lessons).filter((l) => l.error).length,
    });
    await writeFile(file, JSON.stringify(done, null, 1));
  }

  await writeFile(path.join(OUT, '_summary.json'), JSON.stringify(summary, null, 1));
  log('DONE');
  console.table(summary);
  await ctx.close();
}

main().catch((e) => { console.error(e); process.exit(1); });
