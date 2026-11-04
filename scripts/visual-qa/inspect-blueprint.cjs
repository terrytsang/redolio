/**
 * In-page blueprint inspection, shared by every browser provider: the local
 * Playwright worker requires it, and the Browserless adapter sends its source to
 * the remote browser. It must stay a single self-contained function (no closures
 * over module scope) so its source can be shipped as-is.
 */
/** Runs inside the page. Pure DOM inspection — no network, no mutation of content. */
function inspectBlueprint({ exp, width }) {
    const visible = (el) => {
        if (!el) return false;
        const s = getComputedStyle(el);
        const r = el.getBoundingClientRect();
        return s.display !== 'none' && s.visibility !== 'hidden' && r.width > 0 && r.height > 0;
    };
    const sections = Array.from(document.querySelectorAll('[data-section]'));
    const ids = sections.map((n) => n.getAttribute('data-section'));

    const hrefs = Array.from(document.querySelectorAll('a[href^="#"]')).map((a) => a.getAttribute('href'));
    const brokenLinks = [...new Set(hrefs.filter((h) => h.length > 1 && !document.getElementById(h.slice(1))))];

    const nav = document.querySelector('.bp-nav__links');
    const summary = document.querySelector('.bp-nav details summary');
    let navOk;
    let navMode;
    if (width < 900) {
        navMode = 'disclosure';
        if (summary && visible(summary)) {
            summary.click();
            const menu = document.querySelector('.bp-nav__menu');
            navOk = visible(menu) && menu.querySelectorAll('a').length > 0;
        } else {
            navOk = false;
        }
    } else {
        navMode = 'inline';
        navOk = visible(nav) && nav.querySelectorAll('a').length > 0;
    }

    const cta = document.querySelector('.bp-hero .bp-btn, .bp-hero--aerial .bp-btn');

    const forms = Array.from(document.querySelectorAll('form')).map((f) => {
        const fields = Array.from(f.querySelectorAll('input, textarea'));
        const unlabeled = fields.filter((i) => !i.closest('label')).length;
        return { fields: fields.length, unlabeled, has_target: !!f.getAttribute('action') || !!f.getAttribute('onsubmit'), has_submit: !!f.querySelector('[type=submit], button') };
    });

    // Numbers/prices/percentages that read as claims must come from the verified spec text.
    const text = document.body.innerText || '';
    const claimRe = /[$€£]\s?\d[\d,.]*|\b\d[\d,]*\+?\s?(?:%|years?|clients?|customers?|projects?|reviews?)\b|\b\d(?:\.\d)?\s?\/\s?5\b/gi;
    const allowed = (exp.allowed_text || '').toLowerCase();
    const unverified = [...new Set((text.match(claimRe) || []).map((m) => m.trim()))].filter((m) => !allowed.includes(m.toLowerCase()));

    return {
        section_ids: ids,
        expected_section_ids: exp.sections,
        sequence_matches: JSON.stringify(ids) === JSON.stringify(exp.sections),
        duplicate_sections: ids.length !== new Set(ids).size,
        empty_sections: sections.filter((n) => (n.textContent || '').trim().length === 0).map((n) => n.getAttribute('data-section')),
        broken_links: brokenLinks,
        nav_mode: navMode,
        nav_ok: !!navOk,
        primary_cta_visible: visible(cta),
        forms,
        h1_count: document.querySelectorAll('h1').length,
        unverified_claims: unverified.slice(0, 10),
    };
}

if (typeof module !== 'undefined') module.exports = { inspectBlueprint };
