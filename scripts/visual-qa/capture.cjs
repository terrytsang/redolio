#!/usr/bin/env node
/**
 * REDO Visual QA capture worker (PRD v1.4 "Visual QA Worker", extended for v1.5).
 *
 * Loads a standalone, already-built static site (the artifact the export/publish
 * pipeline produces) from a local file:// path, captures a full-page screenshot at
 * desktop/tablet/mobile and runs deterministic in-browser checks. When an
 * expectations JSON file is supplied (blueprint sites) it also checks section
 * sequence, empty sections, internal links, navigation, CTA visibility, forms,
 * reduced-motion and invented figures. This never touches a model API.
 *
 * Remote mode (REDO_BROWSER_MODE=browserbase): connects to a Browserbase cloud browser instead of launching Chromium here, for
 * hosts that cannot run browser binaries. The remote browser cannot read local files, so the page's HTML (local images already
 * inlined by the PHP side) is loaded with setContent. BROWSERBASE_API_KEY / BROWSERBASE_PROJECT_ID come from the environment.
 *
 * Usage: node capture.cjs <index.html path> <output dir> [expectations.json]
 * Prints one JSON object per viewport on stdout.
 */
let chromium;
try { ({ chromium } = require('playwright')); } catch (e) { ({ chromium } = require('playwright-core')); } // the remote provider only needs playwright-core
const path = require('path');
const fs = require('fs');
const { inspectBlueprint } = require('./inspect-blueprint.cjs');

const VIEWPORTS = [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 390, height: 844 },
];

async function main() {
    const [, , htmlPath, outputDir, expectationsPath] = process.argv;

    if (!htmlPath || !outputDir) {
        console.error('Usage: node capture.cjs <index.html path> <output dir> [expectations.json]');
        process.exit(1);
    }
    if (!fs.existsSync(htmlPath)) {
        console.error(`File not found: ${htmlPath}`);
        process.exit(1);
    }

    const expectations = expectationsPath && fs.existsSync(expectationsPath)
        ? JSON.parse(fs.readFileSync(expectationsPath, 'utf8'))
        : null;

    fs.mkdirSync(outputDir, { recursive: true });
    const fileUrl = 'file://' + path.resolve(htmlPath);
    const remote = process.env.REDO_BROWSER_MODE === 'browserbase' ? await openBrowserbase() : null;
    const browser = remote ? remote.browser : await chromium.launch();
    const html = remote ? fs.readFileSync(htmlPath, 'utf8') : null;

    try {
        for (const viewport of VIEWPORTS) {
            const result = await captureViewport(browser, fileUrl, viewport, outputDir, expectations, remote ? { ctx: remote.ctx, html } : null);
            console.log(JSON.stringify(result));
        }
    } finally {
        await browser.close().catch(() => {});
        remote && (await remote.release());
    }
}

/** Starts a Browserbase session (REST) and connects to it over CDP. */
async function openBrowserbase() {
    const key = process.env.BROWSERBASE_API_KEY;
    if (!key) throw new Error('BROWSERBASE_API_KEY is not set.');
    const project = process.env.BROWSERBASE_PROJECT_ID || undefined;
    const headers = { 'X-BB-API-Key': key, 'Content-Type': 'application/json' };
    const res = await fetch('https://api.browserbase.com/v1/sessions', { method: 'POST', headers, body: JSON.stringify(project ? { projectId: project } : {}) });
    const session = await res.json().catch(() => ({}));
    if (!res.ok || !session.connectUrl) throw new Error('Browserbase could not start a browser session (HTTP ' + res.status + ').');
    const browser = await chromium.connectOverCDP(session.connectUrl);
    const ctx = browser.contexts()[0] || (await browser.newContext());
    const release = () => fetch('https://api.browserbase.com/v1/sessions/' + session.id, { method: 'POST', headers, body: JSON.stringify({ status: 'REQUEST_RELEASE', projectId: session.projectId }) }).catch(() => {});

    return { browser, ctx, release };
}

async function captureViewport(browser, fileUrl, viewport, outputDir, expectations, remote) {
    const page = remote ? await remote.ctx.newPage() : await browser.newPage({ viewport: { width: viewport.width, height: viewport.height } });
    if (remote) await page.setViewportSize({ width: viewport.width, height: viewport.height });

    const consoleErrors = [];
    const pageErrors = [];
    const failedRequests = [];

    page.on('console', (msg) => {
        if (msg.type() === 'error') consoleErrors.push(msg.text().slice(0, 300));
    });
    page.on('pageerror', (err) => pageErrors.push(String(err.message || err).slice(0, 300)));
    page.on('requestfailed', (req) => failedRequests.push(req.url().slice(0, 200)));

    let loadError = null;
    try {
        if (remote) {
            await page.setContent(remote.html, { waitUntil: 'networkidle', timeout: 20000 });
            // A cloud browser shows a 15px scrollbar that shrinks the page below the viewport width; hide it so the layout matches the real width.
            await page.addStyleTag({ content: 'html{scrollbar-width:none}::-webkit-scrollbar{display:none}' });
            await page.evaluate(() => document.fonts && document.fonts.ready);
        }
        else await page.goto(fileUrl, { waitUntil: 'networkidle', timeout: 15000 });
    } catch (err) {
        loadError = String(err.message || err).slice(0, 300);
    }

    const screenshotPath = path.join(outputDir, `${viewport.name}.png`);
    let overflow = false;
    let imageCount = 0;
    let brokenImageCount = 0;
    let brokenImageSrcs = [];
    let blueprint = null;
    let reducedMotionOk = null;

    if (!loadError) {
        await page.screenshot({ path: screenshotPath, fullPage: true });

        const m = await page.evaluate(() => {
            const doc = document.documentElement;
            const images = Array.from(document.querySelectorAll('img'));
            const broken = images.filter((img) => img.complete && img.naturalWidth === 0);
            return {
                scrollWidth: doc.scrollWidth,
                clientWidth: doc.clientWidth,
                imageCount: images.length,
                brokenImageCount: broken.length,
                brokenSrcs: broken.map((i) => i.getAttribute('src') || '').slice(0, 20),
            };
        });
        overflow = m.scrollWidth > m.clientWidth + 2; // scrollbar rounding tolerance
        imageCount = m.imageCount;
        brokenImageCount = m.brokenImageCount;
        brokenImageSrcs = m.brokenSrcs;

        if (expectations) {
            blueprint = await page.evaluate(inspectBlueprint, { exp: expectations, width: viewport.width }).catch((e) => ({ error: String(e.message || e).slice(0, 200) }));

            if (viewport.name === 'desktop') {
                await page.emulateMedia({ reducedMotion: 'reduce' });
                reducedMotionOk = await page.evaluate(() => {
                    const els = Array.from(document.querySelectorAll('.bp-btn, [class^="bp-"]')).slice(0, 40);
                    const zero = (v) => v.split(',').every((p) => parseFloat(p) === 0);
                    return els.every((el) => {
                        const s = getComputedStyle(el);
                        return zero(s.transitionDuration || '0s') && zero(s.animationDuration || '0s');
                    });
                });
            }
        }
    }

    await page.close();

    return {
        viewport: viewport.name,
        width: viewport.width,
        height: viewport.height,
        screenshot_path: loadError ? null : screenshotPath,
        load_error: loadError,
        console_errors: consoleErrors.slice(0, 10),
        page_errors: pageErrors.slice(0, 10),
        failed_requests: failedRequests.slice(0, 10),
        has_horizontal_overflow: overflow,
        image_count: imageCount,
        broken_image_count: brokenImageCount,
        broken_image_srcs: brokenImageSrcs,
        blueprint,
        reduced_motion_ok: reducedMotionOk,
    };
}

main().catch((err) => {
    console.error(String((err && err.stack) || err));
    process.exit(1);
});
