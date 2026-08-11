import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const jobPath = process.argv[2];

if (!jobPath) {
    throw new Error('A settings snapshot job JSON path is required.');
}

const job = JSON.parse(await fs.readFile(jobPath, 'utf8'));
const targets = Array.isArray(job.targets) ? job.targets : [];

if (targets.length === 0) {
    throw new Error('The settings snapshot job does not contain any targets.');
}

// One target's failure must not sink its batch siblings: every target gets its
// own try/catch, and the per-target outcomes are always written to the results
// file (before the browser closes) so the manager can map them back to rows.
const results = [];
let browser = null;

try {
    browser = await chromium.launch({ headless: true });

    for (const target of targets) {
        try {
            await captureTarget(browser, target);
            results.push({ snapshot_id: target.snapshot_id ?? null, ok: true, error: null });
        } catch (error) {
            results.push({
                snapshot_id: target.snapshot_id ?? null,
                ok: false,
                error: String(error?.message ?? error),
            });
        }
    }
} finally {
    if (typeof job.results_path === 'string' && job.results_path !== '') {
        try {
            await fs.mkdir(path.dirname(job.results_path), { recursive: true });
            await fs.writeFile(job.results_path, JSON.stringify({ results }), 'utf8');
        } catch (error) {
            console.error(`Could not write the snapshot results file: ${String(error?.message ?? error)}`);
        }
    }

    if (browser) {
        await browser.close();
    }
}

const failedResults = results.filter((result) => !result.ok);

if (failedResults.length > 0) {
    console.error(`${failedResults.length} of ${targets.length} snapshot targets failed.`);
    process.exitCode = 1;
}

async function captureTarget(browser, target) {
    const viewport = normalizeViewport(target.viewport);
    const fallbackViewport = normalizeViewport(target.fallback_viewport ?? target.viewport);
    const deviceScaleFactor = normalizeDeviceScaleFactor(target.device_scale_factor);
    const theme = target.theme === 'dark' ? 'dark' : 'light';

    try {
        await captureTargetWithViewport(browser, target, theme, viewport, deviceScaleFactor);

        return;
    } catch (error) {
        if (target.mode !== 'thumb' && target.kind !== 'thumbnail') {
            throw error;
        }

        await captureTargetWithViewport(browser, target, theme, fallbackViewport, 1);
    }
}

async function captureTargetWithViewport(browser, target, theme, viewport, deviceScaleFactor) {
    const context = await browser.newContext({
        viewport,
        deviceScaleFactor,
        colorScheme: theme,
    });

    try {
        await context.addInitScript((selectedTheme) => {
            localStorage.setItem('podtext-theme', selectedTheme);

            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle(
                'dark',
                selectedTheme === 'dark' || (selectedTheme === 'system' && prefersDark),
            );
        }, theme);

        const page = await context.newPage();

        await page.goto(target.url, {
            waitUntil: 'networkidle',
            timeout: 60000,
        });

        await page.evaluate((selectedTheme) => {
            localStorage.setItem('podtext-theme', selectedTheme);

            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle(
                'dark',
                selectedTheme === 'dark' || (selectedTheme === 'system' && prefersDark),
            );
        }, theme);

        await page.waitForTimeout(100);

        const outputs = target.outputs && typeof target.outputs === 'object' ? target.outputs : {};
        const formats = Array.isArray(target.formats) ? target.formats : Object.keys(outputs);

        for (const format of formats) {
            if (!outputs[format]) {
                continue;
            }

            await fs.mkdir(path.dirname(outputs[format]), { recursive: true });

            if (format === 'png') {
                await page.screenshot({
                    path: outputs[format],
                    fullPage: target.mode !== 'thumb' && target.kind !== 'thumbnail',
                });

                continue;
            }

            if (format === 'pdf') {
                await page.pdf({
                    path: outputs[format],
                    printBackground: true,
                    format: 'A4',
                });

                continue;
            }

            if (format === 'html') {
                await fs.writeFile(outputs[format], await page.content(), 'utf8');
            }
        }
    } finally {
        await context.close();
    }
}

function normalizeViewport(viewport) {
    const width = Number(viewport?.width ?? 1440);
    const height = Number(viewport?.height ?? 900);

    return {
        width: Number.isFinite(width) && width > 0 ? Math.round(width) : 1440,
        height: Number.isFinite(height) && height > 0 ? Math.round(height) : 900,
    };
}

function normalizeDeviceScaleFactor(value) {
    const scale = Number(value ?? 1);

    if (!Number.isFinite(scale) || scale <= 0) {
        return 1;
    }

    return scale;
}
