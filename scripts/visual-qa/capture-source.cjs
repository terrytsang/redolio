#!/usr/bin/env node
/**
 * Renders a customer's website in a real browser so REDO can (1) read the real content of sites that
 * need JavaScript or sit behind a bot-protection challenge, and (2) take a genuine desktop screenshot
 * for the "before" panel.
 *
 * SECURITY — this opens a URL chosen by a user. PHP has already run SsrfGuard on it, but this script
 * does NOT rely on that: ALL browser traffic (navigation, redirects, sub-resources, fetch/XHR,
 * WebSockets) is forced through egress-proxy.cjs, which resolves each host once, requires every
 * address to be public, and connects only to the address it validated. Chromium cannot reach a
 * private/loopback/link-local/metadata address, even via a redirect or DNS rebinding. QUIC (which
 * would bypass a proxy) is off; non-http(s) schemes are aborted; service workers, downloads and all
 * permissions are off; there is no persistent profile.
 *
 * Usage: node capture-source.cjs '<json config>'   { url, outDir, maxPages, maxMs }
 * Prints one JSON object: { ok, pages: [{url,status,html,cleared}], screenshot, challenge_cleared, errors, blocked }
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const { startProxy, validatedAddress } = require('./egress-proxy.cjs');

// ---------------------------------------------------------------- page helpers
const CHALLENGE_TITLE = /^\s*(pow|just a moment|attention required|checking your browser|access denied|one moment|please wait|security check|verify(ing)? you are human|ddos protection)/i;

async function settle(page, deadline) {
    // Wait through challenge redirects until real content appears (or time runs out).
    let cleared = true;
    for (;;) {
        await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
        let title = '', len = 0;
        try {
            title = await page.title();
            len = (await page.evaluate(() => (document.body ? document.body.innerText : '').trim().length)) || 0;
        } catch { await page.waitForTimeout(800); if (Date.now() > deadline) return false; continue; } // mid-navigation
        const challenged = CHALLENGE_TITLE.test(title) || (len < 120 && /pow|challenge|captcha/i.test(title));
        if (!challenged && len >= 40) return cleared;
        cleared = false;
        if (Date.now() > deadline) return false;
        await page.waitForTimeout(1200);
    }
}

async function lazyScroll(page) {
    try {
        await page.evaluate(async () => {
            const step = Math.max(400, Math.floor(window.innerHeight * 0.8));
            for (let y = 0, i = 0; y < document.body.scrollHeight && i < 14; y += step, i++) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 140)); }
            window.scrollTo(0, 0);
        });
        await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
        // Let sliders/fades finish and every image decode before anything is captured, so the "before" shows
        // the page as a visitor sees it (not a hero caught mid-transition).
        await page.evaluate(async () => {
            await document.fonts.ready.catch(() => {});
            const imgs = Array.from(document.images);
            await Promise.race([Promise.all(imgs.map((i) => (i.complete ? Promise.resolve() : new Promise((r) => { i.onload = i.onerror = r; })))), new Promise((r) => setTimeout(r, 6000))]);
        });
        await page.waitForTimeout(3500);
    } catch { /* best effort */ }
}

(async () => {
    const cfg = JSON.parse(process.argv[2] || '{}');
    const result = { ok: false, pages: [], screenshot: null, challenge_cleared: true, errors: [], blocked: [] };
    const started = Date.now();
    const deadline = started + (cfg.maxMs || 70000);
    let browser; let proxy;
    try {
        const u = new URL(cfg.url);
        if (!['http:', 'https:'].includes(u.protocol) || !(await validatedAddress(u.hostname))) {
            result.errors.push('The address is not a permitted public destination.');
            return;
        }

        proxy = await startProxy();
        browser = await chromium.launch({
            proxy: { server: `http://127.0.0.1:${proxy.port}` },
            args: [
                '--proxy-bypass-list=<-loopback>',   // loopback is NOT exempt from the proxy
                '--disable-quic',                    // QUIC/HTTP3 is UDP and would bypass the proxy
                '--disable-dev-shm-usage', '--no-first-run',
                '--host-resolver-rules=MAP * ~NOTFOUND , EXCLUDE 127.0.0.1', // any direct DNS attempt fails
                '--webrtc-ip-handling-policy=disable_non_proxied_udp', '--force-webrtc-ip-handling-policy',
            ],
        });
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block', acceptDownloads: false, permissions: [], locale: 'en-US' });
        // Belt and braces: only web schemes (plus inert data/blob/about) may load at all.
        await context.route('**/*', (route) => {
            let proto = '';
            try { proto = new URL(route.request().url()).protocol; } catch { return route.abort('blockedbyclient'); }
            return ['http:', 'https:', 'data:', 'blob:', 'about:'].includes(proto) ? route.continue() : route.abort('blockedbyclient');
        });

        const origin = u.origin;
        const visited = new Set();
        const queue = [cfg.url];
        const page = await context.newPage();
        let lastDocStatus = 0;
        page.on('response', (r) => { if (r.request().isNavigationRequest() && r.frame() === page.mainFrame()) lastDocStatus = r.status(); });

        while (queue.length && result.pages.length < (cfg.maxPages || 4) && Date.now() < deadline) {
            const target = queue.shift();
            if (visited.has(target)) continue;
            visited.add(target);

            await page.goto(target, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch((e) => result.errors.push(`goto ${target}: ${String(e.message).slice(0, 100)}`));
            const cleared = await settle(page, deadline);
            if (result.pages.length === 0) result.challenge_cleared = cleared;
            await lazyScroll(page);

            let html = '';
            try { html = await page.content(); } catch (e) { result.errors.push(`content: ${String(e.message).slice(0, 80)}`); }
            const final = page.url();
            // Never keep a page that ended up off-origin (e.g. a redirect to another site).
            if (new URL(final).origin !== origin) { result.errors.push('redirected off-origin'); continue; }
            result.pages.push({ url: final, status: cleared ? (lastDocStatus >= 400 ? 200 : lastDocStatus || 200) : lastDocStatus || 0, html: html.slice(0, 2_000_000), cleared });

            if (result.pages.length === 1) {
                fs.mkdirSync(cfg.outDir, { recursive: true });
                const file = path.join(cfg.outDir, 'desktop.png');
                const height = await page.evaluate(() => document.documentElement.scrollHeight).catch(() => 900);
                await (height > 7000
                    ? page.screenshot({ path: file, clip: { x: 0, y: 0, width: 1440, height: 7000 } })
                    : page.screenshot({ path: file, fullPage: true })).then(() => { result.screenshot = 'desktop.png'; }).catch((e) => result.errors.push(`screenshot: ${String(e.message).slice(0, 80)}`));

                // Same-origin links worth reading: navigation first, then the rest, no files or fragments.
                const links = await page.evaluate((o) => {
                    const seen = new Set(); const out = [];
                    const take = (a) => { try { const h = new URL(a.href, location.href); h.hash = ''; const s = h.toString(); if (h.origin === o && !/\.(pdf|zip|jpe?g|png|gif|webp|svg|mp4|docx?|xlsx?)$/i.test(h.pathname) && !seen.has(s) && s !== location.href.split('#')[0]) { seen.add(s); out.push(s); } } catch {} };
                    document.querySelectorAll('nav a[href], header a[href]').forEach(take);
                    document.querySelectorAll('main a[href], body a[href]').forEach(take);
                    return out.slice(0, 12);
                }, origin).catch(() => []);
                queue.push(...links);
            }
        }
        result.ok = result.pages.length > 0;
    } catch (e) {
        result.errors.push(String((e && e.message) || e).slice(0, 200));
    } finally {
        if (browser) await browser.close().catch(() => {});
        if (proxy) { result.blocked = [...new Set([...result.blocked, ...proxy.stats.blocked])].slice(0, 20); proxy.close(); }
        result.elapsed_ms = Date.now() - started;
        console.log(JSON.stringify(result));
    }
})();
