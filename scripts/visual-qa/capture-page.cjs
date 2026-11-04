#!/usr/bin/env node
/** Full-page screenshot of a local HTML file. Usage: node capture-page.cjs <html> <out.png> [width] */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
(async () => {
    const [, , html, out, width] = process.argv;
    if (!html || !out || !fs.existsSync(html)) { console.error('Usage: node capture-page.cjs <html> <out.png> [width]'); process.exit(1); }
    fs.mkdirSync(path.dirname(out), { recursive: true });
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage({ viewport: { width: +width || 1440, height: 900 } });
        await page.goto('file://' + path.resolve(html), { waitUntil: 'networkidle', timeout: 20000 });
        await page.screenshot({ path: out, fullPage: true });
    } finally { await browser.close(); }
})().catch((e) => { console.error(String((e && e.stack) || e)); process.exit(1); });
