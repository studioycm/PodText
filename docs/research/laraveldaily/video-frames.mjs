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
