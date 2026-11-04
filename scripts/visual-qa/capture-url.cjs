#!/usr/bin/env node
/**
 * Homepage screenshot of a public reference website (for the admin's Design Directions).
 *
 * Usage: node capture-url.cjs <https url> <output .jpg path>
 * With REDO_BROWSER_MODE=browserbase it opens a Browserbase cloud browser (BROWSERBASE_API_KEY, optional BROWSERBASE_PROJECT_ID), so the
 * address is only ever opened in an isolated remote browser. Without it, it uses a local Playwright Chromium (development).
 * The caller has already validated the URL (http/https, public host).
 */
let chromium;
try { ({ chromium } = require('playwright')); } catch (e) { ({ chromium } = require('playwright-core')); }
const fs = require('fs');

async function main() {
    const [, , url, out] = process.argv;
    if (!url || !out || !/^https?:\/\//i.test(url)) { console.error('Usage: node capture-url.cjs <https url> <output.jpg>'); process.exit(1); }

    let browser, release = async () => {}, ctx;
    if (process.env.REDO_BROWSER_MODE === 'browserbase') {
        const key = process.env.BROWSERBASE_API_KEY;
        if (!key) throw new Error('BROWSERBASE_API_KEY is not set.');
        const project = process.env.BROWSERBASE_PROJECT_ID || undefined;
        const headers = { 'X-BB-API-Key': key, 'Content-Type': 'application/json' };
        const res = await fetch('https://api.browserbase.com/v1/sessions', { method: 'POST', headers, body: JSON.stringify(project ? { projectId: project } : {}) });
        const session = await res.json().catch(() => ({}));
        if (!res.ok || !session.connectUrl) throw new Error('Browserbase could not start a browser session (HTTP ' + res.status + ').');
        browser = await chromium.connectOverCDP(session.connectUrl);
        ctx = browser.contexts()[0] || (await browser.newContext());
        release = () => fetch('https://api.browserbase.com/v1/sessions/' + session.id, { method: 'POST', headers, body: JSON.stringify({ status: 'REQUEST_RELEASE', projectId: session.projectId }) }).catch(() => {});
    } else {
        browser = await chromium.launch();
        ctx = await browser.newContext();
    }

    try {
        const page = await ctx.newPage();
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
        await page.addStyleTag({ content: 'html{scrollbar-width:none}::-webkit-scrollbar{display:none}' }).catch(() => {});
        await page.waitForTimeout(1200); // let hero animations and lazy images settle
        const buf = await page.screenshot({ type: 'jpeg', quality: 82 });
        fs.writeFileSync(out, buf);
        console.log(JSON.stringify({ ok: true, bytes: buf.length, title: (await page.title()).slice(0, 120) }));
    } finally {
        await browser.close().catch(() => {});
        await release();
    }
}

main().catch((err) => { console.error(String((err && err.message) || err).slice(0, 400)); process.exit(1); });
