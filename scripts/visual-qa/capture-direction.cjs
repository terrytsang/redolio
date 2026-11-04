#!/usr/bin/env node
/**
 * Captures the real rendered showcase website for one design direction:
 *   hero.png  — 1440x900 first screen (the direction card image)
 *   full.png  — full page at 1440 wide
 *   mobile.png — 390x844 first screen
 * Loads a self-contained export directory from file:// (fonts bundled next to the page),
 * waits for web fonts to finish loading so typography is real, and reports console errors.
 *
 * Usage: node capture-direction.cjs <index.html> <output dir>
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

(async () => {
    const [, , htmlPath, outDir] = process.argv;
    if (!htmlPath || !outDir || !fs.existsSync(htmlPath)) {
        console.error('Usage: node capture-direction.cjs <index.html> <output dir>');
        process.exit(1);
    }
    fs.mkdirSync(outDir, { recursive: true });
    const url = 'file://' + path.resolve(htmlPath);
    const browser = await chromium.launch();
    const result = { errors: [], fonts: [] };
    try {
        for (const [name, width, height, full] of [['hero', 1440, 900, false], ['full', 1440, 900, true], ['mobile', 390, 844, false]]) {
            const page = await browser.newPage({ viewport: { width, height } });
            page.on('console', (m) => m.type() === 'error' && result.errors.push(`${name}: ${m.text().slice(0, 160)}`));
            page.on('pageerror', (e) => result.errors.push(`${name}: ${String(e.message).slice(0, 160)}`));
            page.on('requestfailed', (r) => result.errors.push(`${name}: failed ${r.url().slice(0, 120)}`));
            await page.goto(url, { waitUntil: 'networkidle', timeout: 20000 });
            await page.evaluate(() => document.fonts.ready);
            if (name === 'hero') {
                result.fonts = await page.evaluate(() => Array.from(document.fonts).filter((f) => f.status === 'loaded').map((f) => `${f.family} ${f.weight}`));
            }
            await page.waitForTimeout(150);
            await page.screenshot({ path: path.join(outDir, `${name}.png`), fullPage: full });
            await page.close();
        }
    } finally {
        await browser.close();
    }
    console.log(JSON.stringify(result));
})().catch((e) => { console.error(String((e && e.stack) || e)); process.exit(1); });
