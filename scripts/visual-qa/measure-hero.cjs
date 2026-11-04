#!/usr/bin/env node
/**
 * Measures the REAL rendered hero at desktop / tablet / mobile (PRD v1.6 responsive layout validation).
 * Opens only the production artifact REDO built (file://), waits for fonts and images, then reports line counts,
 * overflow, clipping and CTA visibility from the actual layout — never from character counts.
 * It reads and measures; it never edits the page and never changes type size.
 *
 * Usage: node measure-hero.cjs <index.html> <output dir>
 * Prints one JSON object per viewport on stdout.
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const VIEWPORTS = [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 390, height: 844 },
];

/** Runs inside the page. Pure DOM measurement. */
function measure() {
    const hero = (document.querySelector('h1') || {}).closest ? document.querySelector('h1').closest('[data-section]') : null;
    if (!hero) return { found: false };
    const h1 = hero.querySelector('h1');
    const desc = hero.querySelector('.bp-lead, .bp-typo__lead');
    const eyebrow = hero.querySelector('.bp-eyebrow, .bp-pill');
    const cta = hero.querySelector('a.bp-btn');

    const lines = (el) => {
        if (!el || !el.textContent.trim()) return 0;
        const range = document.createRange();
        range.selectNodeContents(el);
        const tops = [];
        for (const r of range.getClientRects()) {
            if (r.width < 1 || r.height < 1) continue;
            if (!tops.some((t) => Math.abs(t - r.top) < r.height * 0.5)) tops.push(r.top);
        }
        return tops.length;
    };
    const rectOf = (el) => { const r = el.getBoundingClientRect(); return { x: r.left, y: r.top, w: r.width, h: r.height, right: r.right, bottom: r.bottom }; };
    const vw = document.documentElement.clientWidth;
    const clippedBy = (el) => {
        const r = el.getBoundingClientRect();
        for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
            const s = getComputedStyle(p);
            if (['hidden', 'clip'].includes(s.overflowX) || ['hidden', 'clip'].includes(s.overflowY)) {
                const pr = p.getBoundingClientRect();
                if (r.left < pr.left - 1 || r.right > pr.right + 1 || r.top < pr.top - 1 || r.bottom > pr.bottom + 1) return p.className || p.tagName;
            }
            if (p === hero) break;
        }
        return null;
    };
    const overflows = (el) => !!el && (el.scrollWidth > el.clientWidth + 1 || el.getBoundingClientRect().right > vw + 1 || el.getBoundingClientRect().left < -1);

    const cs = getComputedStyle(h1);
    const family = cs.fontFamily.split(',')[0].replace(/["']/g, '').trim();
    const faces = Array.from(document.fonts).filter((f) => f.family.replace(/["']/g, '') === family);
    const fontLoaded = document.fonts.status === 'loaded' && (faces.length === 0 ? true : faces.some((f) => f.status === 'loaded'));

    const ctaVisible = !!cta && (() => {
        const s = getComputedStyle(cta); const r = cta.getBoundingClientRect();
        return s.display !== 'none' && s.visibility !== 'hidden' && parseFloat(s.opacity) > 0 && r.width > 0 && r.height > 0 && !clippedBy(cta);
    })();
    const heroRect = hero.getBoundingClientRect();

    return {
        found: true,
        section_id: hero.getAttribute('data-section'),
        font_family: family, font_loaded: fontLoaded, font_faces: faces.map((f) => f.status),
        h1_lines: lines(h1), description_lines: lines(desc), eyebrow_lines: lines(eyebrow),
        h1_font_px: parseFloat(cs.fontSize), h1_line_height_px: parseFloat(cs.lineHeight) || null,
        description_font_px: desc ? parseFloat(getComputedStyle(desc).fontSize) : null,
        overflow_detected: [h1, desc, eyebrow, cta].some(overflows) || document.documentElement.scrollWidth > vw + 1,
        clipping_detected: [h1, desc, cta].filter(Boolean).some((e) => !!clippedBy(e)),
        clipped_by: [h1, desc, cta].filter(Boolean).map(clippedBy).filter(Boolean),
        cta_visible: ctaVisible, cta_above_fold: !!cta && cta.getBoundingClientRect().bottom <= window.innerHeight && cta.getBoundingClientRect().top >= 0,
        hero_height: Math.round(heroRect.height), h1_rect: rectOf(h1),
    };
}

async function main() {
    const [, , file, outDir] = process.argv;
    if (!file || !outDir) { console.error('Usage: node measure-hero.cjs <index.html> <output dir>'); process.exit(1); }
    fs.mkdirSync(outDir, { recursive: true });
    const browser = await chromium.launch();
    try {
        for (const vp of VIEWPORTS) {
            const page = await browser.newPage({ viewport: { width: vp.width, height: vp.height } });
            const result = { viewport: vp.name, width: vp.width, height: vp.height };
            try {
                await page.goto('file://' + path.resolve(file), { waitUntil: 'networkidle', timeout: 20000 });
                // Fonts decide line breaks: measure only once they have settled (bounded wait, then report what is true).
                await page.evaluate(() => Promise.race([document.fonts.ready, new Promise((r) => setTimeout(r, 6000))]));
                await page.evaluate(() => Promise.all(Array.from(document.images).map((i) => (i.complete ? null : new Promise((r) => { i.onload = i.onerror = r; setTimeout(r, 4000); })))));
                await page.waitForTimeout(150);
                Object.assign(result, await page.evaluate(measure));
                if (result.found) {
                    const shot = path.join(outDir, `hero-${vp.name}.png`);
                    const handle = await page.evaluateHandle(() => document.querySelector('h1').closest('[data-section]'));
                    await handle.asElement().screenshot({ path: shot });
                    result.screenshot = path.basename(shot);
                }
            } catch (e) {
                result.error = String(e.message).slice(0, 200);
            }
            console.log(JSON.stringify(result));
            await page.close();
        }
    } finally {
        await browser.close();
    }
}

main().catch((e) => { console.error(e); process.exit(1); });
