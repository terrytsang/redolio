<style nonce="{{ $nonce }}">
.redo-edit{outline:2px dashed transparent;outline-offset:3px;border-radius:4px;cursor:text;transition:outline-color .12s,background-color .12s}
.redo-edit:hover{outline-color:#6D28D9;background-color:rgba(109,40,217,.07)}
.redo-edit:focus{outline:2px solid #6D28D9;background-color:rgba(109,40,217,.09)}
.redo-edit[data-empty]{min-width:3ch;display:inline-block}
.redo-pick{cursor:pointer}.redo-pick:not(.bp-hero__art):not(.bp-full__art){position:relative}
.redo-pick::after{content:attr(data-tip);position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);background:rgba(10,11,13,.88);color:#fff;font:600 12px/1 system-ui,sans-serif;padding:9px 14px;border-radius:999px;opacity:0;pointer-events:none;transition:opacity .12s;z-index:5;white-space:nowrap}
.redo-pick:hover::after{opacity:1}
.redo-pick:hover{outline:2px solid #6D28D9;outline-offset:-2px}
.redo-flash{animation:redoflash .9s ease-out}
.bp-widget{cursor:pointer}.bp-widget:hover{outline:2px dashed #6D28D9;outline-offset:-3px}
.bp-widget[data-widget-label]:hover::before{content:attr(data-widget-label) " — click to edit";position:absolute;left:12px;top:8px;background:#6D28D9;color:#fff;font:600 11px/1 system-ui,sans-serif;padding:6px 10px;border-radius:999px;z-index:6;pointer-events:none}
.bp-widget--floating-chat{position:static}
.bp-widget[data-widget-place="inline"]{position:relative}
.redo-wgrip{position:absolute;left:50%;top:8px;transform:translateX(-50%);z-index:60;display:none;align-items:center;gap:6px;background:#6D28D9;color:#fff;border:0;font:600 12px/1 system-ui,sans-serif;padding:8px 15px;border-radius:999px;cursor:grab;box-shadow:0 6px 18px rgba(0,0,0,.28);user-select:none;-webkit-user-select:none}
.bp-widget:hover .redo-wgrip,.bp-widget:focus-within .redo-wgrip{display:inline-flex}.redo-wgrip:active{cursor:grabbing}
.bp-widget.redo-dragging{opacity:.35;outline:2px dashed #6D28D9}
@keyframes redoflash{from{background-color:rgba(109,40,217,.28)}to{background-color:transparent}}
@media (prefers-reduced-motion:reduce){.redo-flash{animation:none}}
</style>
<script nonce="{{ $nonce }}">
(function () {
    'use strict';
    var FIELDS = {!! json_encode($fields, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!};
    var ORIGIN = location.origin;
    var norm = function (s) { return (s || '').replace(/\s+/g, ' ').trim(); };
    var claimed = new Set();
    var els = {}; // "id|path" -> element
    var key = function (f) { return f.id + '|' + f.path; };
    var post = function (m) { parent.postMessage(m, ORIGIN); };

    // Navigation inside the page is never followed while editing.
    document.addEventListener('click', function (e) { if (e.target.closest('a')) e.preventDefault(); }, true);
    document.addEventListener('submit', function (e) { e.preventDefault(); }, true);

    function sectionRoot(id) { return document.querySelector('[data-section="' + (window.CSS && CSS.escape ? CSS.escape(id) : id) + '"]'); }
    function isLeaf(el) { return Array.prototype.every.call(el.children, function (c) { return c.tagName === 'BR'; }); }

    FIELDS.forEach(function (f) {
        var root = sectionRoot(f.id);
        if (!root) return;
        var el = null;
        if (f.type === 'string' || f.type === 'text') {
            if (!f.value) return; // an empty optional field has nothing on the page to click; the side panel adds it
            var want = norm(f.value);
            var all = root.querySelectorAll('*');
            for (var i = 0; i < all.length; i++) {
                var c = all[i];
                if (claimed.has(c) || c.closest('a.bp-btn') || ['SCRIPT', 'STYLE', 'SVG', 'svg'].indexOf(c.tagName) >= 0 || !isLeaf(c)) continue;
                if (norm(c.textContent) === want) { el = c; break; }
            }
            if (el) makeText(el, f);
        } else if (f.type === 'cta') {
            if (!f.value) return;
            var want2 = norm(f.value);
            var links = root.querySelectorAll('a');
            for (var j = 0; j < links.length; j++) {
                if (!claimed.has(links[j]) && norm(links[j].textContent) === want2) { el = links[j]; break; }
            }
            if (el) makeCta(el, f);
        } else if (f.type === 'image' && f.hero) {
            el = root.querySelector('.bp-hero__art, .bp-full__art, .bp-visual, .bp-mock');
            if (el) makeImage(el, f);
        }
        if (el) { claimed.add(el); els[key(f)] = el; }
    });

    function meta(f, extra) { return Object.assign({ id: f.id, path: f.path, kind: f.type, max: f.max, required: f.required }, extra || {}); }

    function makeText(el, f) {
        var single = f.type === 'string';
        el.classList.add('redo-edit');
        el.setAttribute('role', 'textbox');
        el.setAttribute('aria-label', 'Edit text');
        el.setAttribute('tabindex', '0');
        el.setAttribute('spellcheck', 'true');
        el.contentEditable = 'plaintext-only';
        var plain = el.contentEditable === 'plaintext-only';
        if (!plain) el.contentEditable = 'true';
        var before = '';
        el.addEventListener('focus', function () { before = el.innerText; post(meta(f, { type: 'focus', value: before })); });
        el.addEventListener('input', function () {
            var t = el.innerText;
            post(meta(f, { type: 'input', length: t.length }));
        });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && single) { e.preventDefault(); el.blur(); }
            if (e.key === 'Escape') { el.innerText = before; el.blur(); }
        });
        el.addEventListener('paste', function (e) { // always plain text
            e.preventDefault();
            var t = (e.clipboardData || window.clipboardData).getData('text/plain');
            if (single) t = t.replace(/\s+/g, ' ');
            document.execCommand('insertText', false, t);
        });
        el.addEventListener('drop', function (e) { e.preventDefault(); });
        el.addEventListener('blur', function () {
            var t = el.innerText.replace(/ /g, ' ');
            if (norm(t) === norm(before)) { el.innerText = before; return; }
            post(meta(f, { type: 'edit', value: t, previous: before }));
        });
    }
    function makeCta(el, f) {
        el.classList.add('redo-edit', 'redo-pick');
        el.setAttribute('data-tip', 'Edit button');
        el.style.cursor = 'pointer';
        el.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); post(meta(f, { type: 'select', value: norm(el.textContent), rect: rect(el) })); });
    }
    function makeImage(el, f) {
        el.classList.add('redo-pick');
        el.setAttribute('data-tip', 'Change picture');
        el.setAttribute('tabindex', '0');
        el.setAttribute('role', 'button');
        el.setAttribute('aria-label', 'Change picture');
        var go = function (e) { e.preventDefault(); post(meta(f, { type: 'select', value: f.value, rect: rect(el) })); };
        el.addEventListener('click', go);
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') go(e); });
    }
    function rect(el) { var r = el.getBoundingClientRect(); return { x: r.left, y: r.top, w: r.width, h: r.height }; }

    // ---- add-on widgets: select by click, drop new ones between sections
    document.addEventListener('click', function (e) {
        var w = e.target.closest && e.target.closest('.bp-widget[data-widget]');
        if (w && !e.target.closest('.redo-edit')) { e.preventDefault(); post({ type: 'widget-select', id: w.getAttribute('data-widget') }); }
    }, true);
    var ind = null;
    function indicator() {
        if (!ind) {
            ind = document.createElement('div');
            ind.style.cssText = 'position:fixed;left:0;right:0;height:0;border-top:4px solid #6D28D9;z-index:2147483000;pointer-events:none;display:none';
            var l = document.createElement('span'); l.textContent = 'Drop the add-on here';
            l.style.cssText = 'position:absolute;left:50%;top:-16px;transform:translateX(-50%);background:#6D28D9;color:#fff;font:600 12px system-ui,sans-serif;padding:4px 12px;border-radius:999px;white-space:nowrap';
            ind.appendChild(l); document.body.appendChild(ind);
        }
        return ind;
    }
    var moving = null, autoDy = 0, autoTimer = null;
    // Every place a block can go: between the blocks inside <main> (sections and add-ons), as "after this section" plus, when an add-on
    // sits right there, "before that add-on" — so add-ons can be put above or below each other, not only between sections.
    function boundaries() {
        var main = document.querySelector('main'); if (!main) return [];
        var kids = Array.prototype.filter.call(main.children, function (c) { return c.offsetHeight > 0 && !(moving && c.getAttribute('data-widget') === moving); });
        var out = [], last = 'top';
        var nextWidget = function (i) { var k = kids[i]; return k && k.getAttribute('data-widget-place') === 'inline' ? k.getAttribute('data-widget') : ''; };
        if (kids.length && kids[0].getAttribute('data-widget-place') !== 'top') out.push({ y: kids[0].getBoundingClientRect().top, after: 'top', before: nextWidget(0) });
        if (!kids.length) out.push({ y: 0, after: 'top', before: '' });
        kids.forEach(function (k, i) {
            if (k.hasAttribute('data-section')) last = k.getAttribute('data-section');
            out.push({ y: k.getBoundingClientRect().bottom, after: last, before: nextWidget(i + 1) });
        });
        return out;
    }
    function nearest(y) { var best = null; boundaries().forEach(function (b) { if (!best || Math.abs(b.y - y) < Math.abs(best.y - y)) best = b; }); return best; }
    function stopAuto() { autoDy = 0; if (autoTimer) { clearInterval(autoTimer); autoTimer = null; } }
    function autoScroll(y) { // a long page: dragging near the top or bottom edge scrolls it, so an add-on can travel the whole page
        var edge = 80, dy = y < edge ? -Math.ceil((edge - y) / 4) : (y > window.innerHeight - edge ? Math.ceil((y - (window.innerHeight - edge)) / 4) : 0);
        autoDy = dy;
        if (dy && !autoTimer) autoTimer = setInterval(function () { autoDy ? window.scrollBy(0, autoDy) : stopAuto(); }, 16);
        if (!dy) stopAuto();
    }
    var hasWidget = function (e) { return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'text/plain') >= 0; };
    document.addEventListener('dragover', function (e) {
        if (!hasWidget(e)) return;
        e.preventDefault(); e.dataTransfer.dropEffect = moving ? 'move' : 'copy';
        autoScroll(e.clientY);
        var b = nearest(e.clientY); if (!b) return;
        var i = indicator(); i.firstChild.textContent = moving ? 'Move the add-on here' : 'Drop the add-on here';
        i.style.display = 'block'; i.style.top = Math.max(2, Math.min(window.innerHeight - 6, b.y)) + 'px';
    });
    document.addEventListener('dragleave', function (e) { if (!e.relatedTarget) { indicator().style.display = 'none'; stopAuto(); } });
    document.addEventListener('drop', function (e) {
        if (!hasWidget(e)) return;
        e.preventDefault(); indicator().style.display = 'none'; stopAuto();
        var t = e.dataTransfer.getData('text/plain') || '';
        var b = nearest(e.clientY);
        if (t.indexOf('redo-move:') === 0) { post({ type: 'widget-move', id: t.slice(10), after: b ? b.after : 'top', before: b ? b.before : '' }); moving = null; return; }
        if (t.indexOf('redo-widget:') !== 0) return;
        post({ type: 'widget-drop', widget: t.slice(12), after: b ? b.after : 'top' });
    });
    // A grip on every movable add-on: grab it and drag the add-on up or down the page.
    Array.prototype.forEach.call(document.querySelectorAll('.bp-widget[data-widget][data-widget-place="inline"]'), function (w) {
        var g = document.createElement('button'); g.type = 'button'; g.className = 'redo-wgrip'; g.draggable = true;
        g.setAttribute('aria-label', 'Drag to move this add-on up or down'); g.textContent = '⠿ Drag to move';
        g.addEventListener('dragstart', function (e) {
            moving = w.getAttribute('data-widget'); w.classList.add('redo-dragging');
            e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', 'redo-move:' + moving);
            try { e.dataTransfer.setDragImage(w, 40, 24); } catch (x) { /* optional */ }
        });
        g.addEventListener('dragend', function () { moving = null; w.classList.remove('redo-dragging'); indicator().style.display = 'none'; stopAuto(); });
        w.appendChild(g);
    });

    window.addEventListener('message', function (e) {
        if (e.origin !== ORIGIN || e.source !== parent) return;
        var m = e.data || {};
        var el = els[m.id + '|' + m.path];
        if (m.type === 'set-text' && el) { el.innerText = m.value; el.classList.remove('redo-flash'); void el.offsetWidth; el.classList.add('redo-flash'); }
        if (m.type === 'focus-field' && el) { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); if (el.isContentEditable) el.focus({ preventScroll: true }); else { el.classList.add('redo-flash'); } }
        if (m.type === 'preview-layout') { // instant feedback while a slider moves; the server save follows
            var sr = sectionRoot(m.id);
            if (sr) {
                var W = { narrow: '760px', wide: '1360px', full: '100%' };
                if (W[m.width]) sr.style.setProperty('--container', W[m.width]); else sr.style.removeProperty('--container');
                if (m.split >= 30 && m.split <= 70) { sr.style.setProperty('--cols', m.split + 'fr ' + (100 - m.split) + 'fr'); sr.style.setProperty('--ed-text', 'auto'); sr.style.setProperty('--ed-aside', 'auto'); } else { sr.style.removeProperty('--cols'); sr.style.removeProperty('--ed-text'); sr.style.removeProperty('--ed-aside'); }
            }
        }
        if (m.type === 'locate-field' && el) { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); el.classList.remove('redo-flash'); void el.offsetWidth; el.classList.add('redo-flash'); } // show where it is, without taking the cursor
        if (m.type === 'drag-end') indicator().style.display = 'none';
        if (m.type === 'scroll-widget') { var wEl = document.querySelector('[data-widget="' + (window.CSS && CSS.escape ? CSS.escape(m.id) : m.id) + '"]'); wEl && wEl.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
        if (m.type === 'scroll-section') { var r = sectionRoot(m.id); r && r.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
    });
    post({ type: 'ready', editable: Object.keys(els) });
})();
</script>
