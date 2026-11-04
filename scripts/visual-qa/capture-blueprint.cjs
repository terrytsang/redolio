#!/usr/bin/env node
/**
 * Captures a rendered blueprint preview URL at desktop/tablet/mobile and runs
 * deterministic checks (PRD v1.5 blueprint QA): overflow, broken images,
 * console/page errors, section sequence vs the blueprint, duplicate sections.
 * The screenshot is only ever a preview — never an executable template.
 *
 * Usage: node capture-blueprint.cjs <url> <output dir> <expected instance ids JSON>
 * Prints one JSON object per viewport on stdout.
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const VIEWPORTS = [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 390, height: 844 },
];

async function main() {
    const [, , url, outDir, expectedJson] = process.argv;
    if (!url || !outDir) {
        console.error('Usage: node capture-blueprint.cjs <url> <output dir> <expected JSON>');
        process.exit(1);
    }
    const expected = expectedJson ? JSON.parse(expectedJson) : [];
    fs.mkdirSync(outDir, { recursive: true });

    const browser = await chromium.launch();
    try {
        for (const vp of VIEWPORTS) {
            const page = await browser.newPage({ viewport: { width: vp.width, height: vp.height } });
            const consoleErrors = [];
            const failedRequests = [];
            page.on('console', (m) => m.type() === 'error' && consoleErrors.push(m.text().slice(0, 200)));
            page.on('pageerror', (e) => consoleErrors.push(String(e.message).slice(0, 200)));
            page.on('requestfailed', (r) => failedRequests.push(r.url().slice(0, 200)));

            let loadError = null;
            try {
                await page.goto(url, { waitUntil: 'networkidle', timeout: 20000 });
            } catch (e) {
                loadError = String(e.message).slice(0, 200);
            }

            const result = { viewport: vp.name, width: vp.width, height: vp.height, load_error: loadError, console_errors: consoleErrors, failed_requests: failedRequests };
            if (!loadError) {
                const shot = path.join(outDir, `${vp.name}.png`);
                await page.screenshot({ path: shot, fullPage: true });
                Object.assign(result, { screenshot: path.basename(shot) }, await page.evaluate((exp) => {
                    const doc = document.documentElement;
                    const ids = Array.from(document.querySelectorAll('[data-section]')).map((n) => n.getAttribute('data-section'));
                    const imgs = Array.from(document.images);
                    // only compulsory sections must be present; optional ones may be omitted
                    const filtered = exp.filter((e) => ids.includes(e));
                    return {
                        has_horizontal_overflow: doc.scrollWidth > doc.clientWidth + 1,
                        section_ids: ids,
                        sequence_matches: JSON.stringify(ids) === JSON.stringify(filtered) && filtered.length === ids.length,
                        duplicate_sections: ids.length !== new Set(ids).size,
                        h1_count: document.querySelectorAll('h1').length,
                        broken_image_count: imgs.filter((i) => !i.complete || i.naturalWidth === 0).length,
                        empty_sections: Array.from(document.querySelectorAll('[data-section]')).filter((n) => n.textContent.trim().length === 0).map((n) => n.getAttribute('data-section')),
                        page_height: doc.scrollHeight,
                    };
                }, expected));
            }
            console.log(JSON.stringify(result));
            await page.close();
        }
    } finally {
        await browser.close();
    }
}

main().catch((e) => { console.error(e); process.exit(1); });
