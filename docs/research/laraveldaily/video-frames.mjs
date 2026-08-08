#!/usr/bin/env node
/**
 * Frame extractor with zero installs: Playwright + system Chrome (h264-capable),
 * seek a <video> element, draw to canvas, save PNG contact sheets + full frames.
 *
 *   node frames.mjs sheet <video.mp4> <outdir> [stepSeconds]   → tiled contact sheets
 *   node frames.mjs frame <video.mp4> <outdir> <t1> [t2...]    → full-res single frames
 */
import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const [mode, videoPath, outDir, ...rest] = process.argv.slice(2);
if (!mode || !videoPath || !outDir) { console.error('usage: see header'); process.exit(1); }

// yt mode has its own flow (declared below; function declarations hoist).
if (mode === 'yt') { await ytMode(); process.exit(0); }

await mkdir(outDir, { recursive: true });

const browser = await chromium.launch({
  channel: 'chrome',
  headless: true,
  // file:// pages are opaque-origin for canvas, so drawing the video taints it and
  // toDataURL throws SecurityError. This flag makes file:// self-readable.
  args: ['--allow-file-access-from-files'],
});
const page = await browser.newPage();
const fileUrl = 'file://' + encodeURI(path.resolve(videoPath)).replace(/[()]/g, (c) => ({ '(': '%28', ')': '%29' }[c]));

// Navigate to the mp4 itself: Chrome renders a media document whose <video> we drive.
// (A setContent page is about:blank-origin and is refused file:// subresources.)
await page.goto(fileUrl);
const meta = await page.evaluate(() => new Promise((res, rej) => {
  const v = document.querySelector('video');
  if (!v) return rej(new Error('no <video> in media document'));
  v.id = 'v'; v.muted = true; v.pause();
  if (v.readyState >= 1) return res({ dur: v.duration, w: v.videoWidth, h: v.videoHeight });
  v.onloadedmetadata = () => res({ dur: v.duration, w: v.videoWidth, h: v.videoHeight });
  v.onerror = () => rej(new Error('video failed to load — codec?'));
}));
console.log(`duration ${meta.dur.toFixed(1)}s  ${meta.w}x${meta.h}`);

const seek = (t) => page.evaluate((t) => new Promise((res) => {
  const v = document.getElementById('v');
  v.onseeked = () => requestAnimationFrame(() => res());
  v.currentTime = t;
}), t);

const grab = () => page.evaluate(() => {
  const v = document.getElementById('v');
  const c = document.createElement('canvas');
  c.width = v.videoWidth; c.height = v.videoHeight;
  c.getContext('2d').drawImage(v, 0, 0);
  return c.toDataURL('image/png').split(',')[1];
});

if (mode === 'frame') {
  for (const ts of rest.map(Number)) {
    await seek(ts);
    const b64 = await grab();
    const f = path.join(outDir, `t${String(ts).replace('.', '_')}.png`);
    await writeFile(f, Buffer.from(b64, 'base64'));
    console.log('wrote', f);
  }
} else {
  const step = Number(rest[0] || 15);
  const times = [];
  for (let t = 2; t < meta.dur - 1; t += step) times.push(Math.round(t));
  const COLS = 3, ROWS = 4, per = COLS * ROWS;
  for (let s = 0; s * per < times.length; s++) {
    const batch = times.slice(s * per, (s + 1) * per);
    await page.evaluate(({ COLS, ROWS, w, h }) => {
      const c = document.createElement('canvas'); c.id = 'sheet';
      c.width = COLS * w; c.height = ROWS * h;
      const old = document.getElementById('sheet'); if (old) old.remove();
      document.body.appendChild(c);
      window.__ctx = c.getContext('2d');
      window.__ctx.fillStyle = '#fff'; window.__ctx.font = `bold ${Math.round(h/14)}px monospace`;
    }, { COLS, ROWS, w: meta.w, h: meta.h });
    for (let i = 0; i < batch.length; i++) {
      await seek(batch[i]);
      await page.evaluate(({ i, COLS, t, w, h }) => {
        const v = document.getElementById('v');
        const x = (i % COLS) * w, y = Math.floor(i / COLS) * h;
        window.__ctx.drawImage(v, x, y, w, h);
        window.__ctx.strokeStyle = '#0f0'; window.__ctx.strokeRect(x, y, w, h);
        window.__ctx.fillStyle = '#0f0';
        window.__ctx.fillText(`${Math.floor(t/60)}:${String(t%60).padStart(2,'0')}`, x + 8, y + h - 10);
      }, { i, COLS, t: batch[i], w: meta.w, h: meta.h });
    }
    const b64 = await page.evaluate(() => document.getElementById('sheet').toDataURL('image/png').split(',')[1]);
    const f = path.join(outDir, `sheet${s}.png`);
    await writeFile(f, Buffer.from(b64, 'base64'));
    console.log('wrote', f, `(${batch.length} frames @ ${batch[0]}s..${batch[batch.length-1]}s)`);
  }
}
await browser.close();

/**
 * (appended mode) YouTube capture:
 *   node video-frames.mjs yt <videoId> <outdir> <fromSec> <toSec> [stepSec]
 * Chrome channel again (h264/MSE). Seeks the watch-page player and canvas-dumps
 * contact sheets. MSE video is same-origin-safe for canvas (no file:// taint).
 */
async function ytMode() {
  const [, videoId, outDir2, fromS, toS, stepS] = process.argv.slice(2);
  await mkdir(outDir2, { recursive: true });
  const browser2 = await chromium.launch({ channel: 'chrome', headless: true });
  const page2 = await browser2.newPage({ viewport: { width: 1600, height: 900 } });
  await page2.goto(`https://www.youtube.com/watch?v=${videoId}&t=${fromS}s`, { waitUntil: 'domcontentloaded' });
  // consent page (EU flows) — click through if it appears
  try {
    const consent = await page2.$('button[aria-label*="Accept"], form[action*="consent"] button');
    if (consent) { await consent.click(); await page2.waitForLoadState('domcontentloaded'); }
  } catch {}
  await page2.waitForSelector('video', { timeout: 30000 });
  await page2.evaluate(() => {
    const v = document.querySelector('video');
    v.muted = true; v.play();
    const p = document.getElementById('movie_player');
    if (p && p.setPlaybackQualityRange) p.setPlaybackQualityRange('hd1080', 'hd1080');
  });
  await new Promise((r) => setTimeout(r, 4000)); // let quality switch settle

  // Far seeks on a long VOD trigger fresh MSE segment fetches; 'seeked' can fire
  // while readyState is still HAVE_METADATA and the frame is black. Poll for real
  // decodable data (readyState >= 2, not seeking) with a generous ceiling.
  const seekYt = (t) => page2.evaluate((t) => new Promise((res) => {
    const v = document.querySelector('video');
    v.currentTime = t;
    const t0 = Date.now();
    const poll = () => {
      if ((!v.seeking && v.readyState >= 2) || Date.now() - t0 > 20000) {
        setTimeout(res, 600); // paint settle
      } else setTimeout(poll, 300);
    };
    setTimeout(poll, 300);
  }), t);
  const grabYt = () => page2.evaluate(() => {
    const v = document.querySelector('video');
    const c = document.createElement('canvas');
    c.width = v.videoWidth; c.height = v.videoHeight;
    c.getContext('2d').drawImage(v, 0, 0);
    return c.toDataURL('image/png').split(',')[1];
  });

  const meta2 = await page2.evaluate(() => {
    const v = document.querySelector('video');
    return { w: v.videoWidth, h: v.videoHeight, dur: v.duration };
  });
  console.log(`yt ${videoId} ${meta2.w}x${meta2.h} dur=${Math.round(meta2.dur)}s`);

  const step2 = Number(stepS || 20);
  const times2 = [];
  for (let t = Number(fromS); t <= Number(toS); t += step2) times2.push(Math.round(t));
  const COLS = 3, ROWS = 4, per = COLS * ROWS;
  for (let s = 0; s * per < times2.length; s++) {
    const batch = times2.slice(s * per, (s + 1) * per);
    await page2.evaluate(({ COLS, ROWS, w, h }) => {
      const old = document.getElementById('sheet'); if (old) old.remove();
      const c = document.createElement('canvas'); c.id = 'sheet';
      c.width = COLS * w; c.height = ROWS * h;
      document.body.appendChild(c);
      window.__ctx = c.getContext('2d');
      window.__ctx.font = `bold ${Math.round(h / 14)}px monospace`;
    }, { COLS, ROWS, w: meta2.w, h: meta2.h });
    for (let i = 0; i < batch.length; i++) {
      await seekYt(batch[i]);
      await page2.evaluate(({ i, COLS, t, w, h }) => {
        const v = document.querySelector('video');
        const x = (i % COLS) * w, y = Math.floor(i / COLS) * h;
        window.__ctx.drawImage(v, x, y, w, h);
        window.__ctx.strokeStyle = '#0f0'; window.__ctx.strokeRect(x, y, w, h);
        window.__ctx.fillStyle = '#0f0';
        const hh = Math.floor(t / 3600), mm = Math.floor(t % 3600 / 60), ss = t % 60;
        window.__ctx.fillText(`${hh}:${String(mm).padStart(2, '0')}:${String(ss).padStart(2, '0')}`, x + 10, y + h - 12);
      }, { i, COLS, t: batch[i], w: meta2.w, h: meta2.h });
    }
    const b64 = await page2.evaluate(() => document.getElementById('sheet').toDataURL('image/png').split(',')[1]);
    const f = path.join(outDir2, `yt-sheet${s}.png`);
    await writeFile(f, Buffer.from(b64, 'base64'));
    console.log('wrote', f, `(${batch.length} frames @ ${batch[0]}s..${batch[batch.length - 1]}s)`);
  }
  await browser2.close();
}
