@extends('layouts.app')

@section('title', 'REDOLIO — Edit your site')
@section('no_float_toggle', '1')

@php
    $headline = config('hero_layout.headline');
    $api = fn ($name, $extra = []) => route('api.hero.'.$name, array_merge([$run->public_id], $extra));
@endphp

@section('content')
<div id="editor" class="flex h-screen flex-col bg-canvas"
     data-hint-min="{{ $headline['target_min_chars'] }}" data-hint-max="{{ $headline['target_max_chars'] }}" data-hint-soft="{{ $headline['soft_max_chars'] }}">

    {{-- Top bar: where you are, what's saved, what's next --}}
    <header class="relative flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-line bg-surface px-4 py-2.5 lg:px-6">
        <a href="{{ route('analyses.reveal', $run->public_id) }}" class="text-[13px] font-medium text-body hover:text-ink">← Back to before &amp; after</a>
        <span class="hidden h-5 w-px bg-line sm:block"></span>
        <h1 class="text-[15px] font-extrabold tracking-tight text-ink">Edit your site</h1>
        <span id="save-state" class="inline-flex items-center gap-1.5 text-[12px] text-muted" role="status" aria-live="polite"><span class="h-1.5 w-1.5 rounded-full bg-ok"></span><span id="save-text">{{ ! empty($state['unsaved']) ? 'Layout not saved yet' : 'Layout saved' }}</span></span>

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <x-theme-toggle />
            <div class="inline-flex rounded-full border border-line bg-surface p-0.5 text-[12px] font-semibold" role="group" aria-label="Preview size">
                @foreach (['desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile'] as $k => $l)
                    <button type="button" data-device="{{ $k }}" class="device-btn rounded-full px-3 py-1 {{ $k === 'desktop' ? 'bg-ink text-canvas' : 'text-body' }}">{{ $l }}</button>
                @endforeach
            </div>
            <button type="button" id="undo" class="rounded-lg border border-line bg-surface px-3 py-1.5 text-[13px] font-medium text-ink disabled:opacity-40" disabled title="Undo (Ctrl/⌘ Z)">Undo</button>
            <button type="button" id="redo" class="rounded-lg border border-line bg-surface px-3 py-1.5 text-[13px] font-medium text-ink disabled:opacity-40" disabled title="Redo (Ctrl/⌘ Shift Z)">Redo</button>
            <a href="{{ route('analyses.reveal', $run->public_id) }}" class="rounded-lg border border-line bg-surface px-3.5 py-1.5 text-[13px] font-semibold text-ink hover:border-line-strong">Done — see before &amp; after</a>
            <button type="button" id="save-layout" class="rounded-lg border border-accent bg-accent-soft px-3.5 py-1.5 text-[13px] font-semibold text-accent-ink hover:bg-surface disabled:border-line disabled:bg-surface disabled:text-muted disabled:opacity-60" title="Your edits are kept as you go. Saving locks them in as a layout you can return to from History.">Save layout</button>
            <form method="POST" id="approve-form" action="{{ route('analyses.approve-design', $run->public_id) }}">@csrf
                <button type="submit" class="rounded-lg bg-accent px-4 py-1.5 text-[13px] font-semibold text-white hover:bg-accent-strong">Approve design <span id="edit-count" class="ml-1 hidden rounded-full bg-white/25 px-1.5 text-[11px]"></span></button>
            </form>
        </div>
    </header>

    <div id="banner" class="hidden bg-warn-soft px-6 py-2.5 text-[13px] text-warn"></div>
    {{-- Free plan + Export Pass: what the pass still includes for this website (Starter and Agency are unlimited, so this stays hidden). --}}
    <div id="pass-note" class="hidden items-center justify-between gap-3 border-b border-line bg-accent-soft px-6 py-2 text-[12.5px] text-accent-ink"></div>

    <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
        {{-- Canvas: the real page. Click any highlighted text, button or picture. --}}
        <main class="relative min-h-[50vh] flex-1 overflow-hidden" aria-label="Your page">
            <p class="pointer-events-none absolute left-1/2 top-3 z-10 -translate-x-1/2 rounded-full bg-[#0A0B0D]/85 px-4 py-1.5 text-[12px] font-medium text-white" id="canvas-tip">Click any text to edit it · click a button or picture to change it</p>
            <div id="canvas" class="absolute inset-0 overflow-auto p-3 lg:p-5">
                <div id="frame-wrap" class="relative mx-auto origin-top-left overflow-hidden rounded-xl bg-surface shadow-lg ring-1 ring-line" style="width:1440px">
                    <iframe id="frame" title="Your page — click text to edit" src="{{ $frameUrl }}" sandbox="allow-scripts allow-same-origin" class="block w-full border-0 bg-surface" style="height:2400px"></iframe>
                </div>
            </div>
        </main>

        {{-- Panel --}}
        <aside class="flex max-h-[55vh] w-full shrink-0 flex-col border-t border-line bg-surface lg:max-h-none lg:w-[400px] lg:border-l lg:border-t-0" aria-label="Edit panel">
            <div class="flex border-b border-line text-[13px] font-semibold" role="tablist">
                <button type="button" role="tab" data-tab="page" class="tab-btn flex-1 border-b-2 border-accent px-3 py-3 text-accent-ink">Page</button>
                <button type="button" role="tab" data-tab="addons" class="tab-btn flex-1 border-b-2 border-transparent px-2 py-3 text-muted">Add-ons</button>
                <button type="button" role="tab" data-tab="hero" class="tab-btn flex-1 border-b-2 border-transparent px-2 py-3 text-muted">Hero copy</button>
                <button type="button" role="tab" data-tab="history" class="tab-btn flex-1 border-b-2 border-transparent px-3 py-3 text-muted">History</button>
            </div>
            <div id="notes" class="hidden border-b border-warn/30 bg-warn-soft px-4 py-3 text-[12px] leading-snug text-warn"></div>
            <div id="hint" class="hidden border-b border-line bg-accent-soft px-4 py-2.5 text-[12px] text-accent-ink"></div>
            <div class="min-h-0 flex-1 overflow-y-auto" id="panel-page" role="tabpanel"></div>
            <div class="hidden min-h-0 flex-1 overflow-y-auto" id="panel-addons" role="tabpanel"></div>
            <div class="hidden min-h-0 flex-1 overflow-y-auto" id="panel-hero" role="tabpanel"></div>
            <div class="hidden min-h-0 flex-1 overflow-y-auto" id="panel-history" role="tabpanel"></div>
        </aside>
    </div>

    {{-- Shown after "Save layout" on the Free plan: saving is free, publishing and downloading are not. --}}
    <div id="upgrade-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="upgrade-title">
        <div class="w-full max-w-md rounded-2xl bg-surface p-7 text-center shadow-2xl">
            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-ok-soft text-xl text-ok">✓</div>
            <h2 id="upgrade-title" class="mt-4 text-[20px] font-extrabold tracking-tight text-ink">Layout saved</h2>
            <p class="mt-2 text-[14px] leading-relaxed text-muted">Your edits are safe in your account. To <strong>publish</strong> this website on a REDOLIO address or <strong>download the HTML files</strong>, choose a Starter or Agency plan.</p>
            <a href="{{ route('analyses.upgrade', ['analysisRun' => $run->public_id, 'intent' => 'approve']) }}" class="btn btn-primary mt-6 w-full py-3">See plans &amp; publish →</a>
            <button type="button" id="upgrade-later" class="mt-3 text-[13px] font-semibold text-muted hover:text-ink">Keep editing</button>
        </div>
    </div>

    {{-- Picture picker --}}
    <div id="picker" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="picker-title">
        <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-2xl bg-surface shadow-2xl">
            <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                <h2 id="picker-title" class="text-base font-extrabold">Choose a picture</h2>
                <button type="button" id="picker-close" class="rounded-lg px-2 py-1 text-muted hover:bg-canvas" aria-label="Close">✕</button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-5">
                <div class="mb-4 inline-flex rounded-full border border-line bg-surface p-0.5 text-[12px] font-semibold" role="tablist" aria-label="Where to get the picture">
                    <button type="button" role="tab" data-src="mine" class="src-btn rounded-full bg-ink px-3.5 py-1.5 text-canvas">Your pictures</button>
                    <button type="button" role="tab" data-src="unsplash" class="src-btn rounded-full px-3.5 py-1.5 text-body">Search Unsplash</button>
                    <button type="button" role="tab" data-src="ai" class="src-btn rounded-full px-3.5 py-1.5 text-body">✦ Meta AI Image</button>
                </div>
                <div id="picker-ai" class="hidden">
                    <label for="ai-prompt" class="block text-[12px] font-semibold text-body">Describe the picture you want</label>
                    <textarea id="ai-prompt" rows="3" maxlength="400" class="mt-1 w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:border-accent focus:outline-none" placeholder="e.g. a bright, friendly dental clinic reception with a smiling receptionist"></textarea>
                    <p class="mt-1 text-[11px] text-faint">We start from this section’s title and description. Edit it freely — no text or logos are added to the picture.</p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <button type="button" id="ai-go" class="rounded-lg bg-accent px-4 py-2 text-[13px] font-semibold text-white disabled:opacity-40">✦ Generate image</button>
                        <p id="ai-quota" class="text-[12px] text-muted" aria-live="polite"></p>
                    </div>
                    <p id="ai-msg" class="mt-2 hidden text-[12px]" aria-live="polite"></p>
                    <div id="ai-result" class="mt-3 hidden overflow-hidden rounded-xl border-2 border-accent"><img id="ai-img" alt="" class="aspect-[3/2] max-h-60 w-full object-cover"></div>
                    <p class="mt-2 text-[11px] text-faint">Made with Meta’s Muse Image model. Each picture you make uses one of your AI images.</p>
                </div>
                <div id="picker-unsplash" class="hidden">
                    <form id="us-form" class="flex gap-2" role="search"><input id="us-q" type="search" maxlength="80" placeholder="Search free photos — e.g. “dental clinic”, “coffee shop”" class="min-w-0 flex-1 rounded-lg border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:border-accent focus:outline-none" aria-label="Search Unsplash"><button class="rounded-lg bg-accent px-4 py-2 text-[13px] font-semibold text-white">Search</button></form>
                    <p id="us-msg" class="mt-2 text-[12px] text-muted">Free-to-use photos from Unsplash. The photographer is credited below each picture.</p>
                    <div id="us-grid" class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3"></div>
                    <button type="button" id="us-more" class="mt-3 hidden w-full rounded-lg border border-line px-3 py-2 text-[12px] font-semibold text-body hover:border-accent">Show more</button>
                </div>
                <div id="picker-mine">
                <p class="mb-3 text-[12px] text-muted">Pictures from your own site, plus anything you upload. Use photos you have the right to use. PNG transparency is kept.</p>
                <div id="picker-grid" class="grid grid-cols-2 gap-3 sm:grid-cols-3"></div>
                <label class="mt-4 flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line-strong px-4 py-5 text-[13px] font-semibold text-body hover:border-accent hover:text-accent-ink">
                    <span>＋ Upload your own (JPG, PNG or WebP, up to 5 MB)</span>
                    <input id="picker-file" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
                </label>
                <p id="picker-error" class="mt-2 hidden text-[12px] text-bad"></p>
                </div>
                <label class="mt-4 block text-[12px] font-semibold text-body">Describe the picture (for screen readers and search)
                    <input id="picker-alt" type="text" maxlength="200" class="mt-1 w-full rounded-lg border border-line-strong px-3 py-2 text-[13px] font-normal text-ink">
                </label>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-line px-5 py-3.5">
                <button type="button" id="picker-remove" class="hidden text-[13px] font-semibold text-bad">Remove picture</button>
                <span class="flex-1"></span>
                <button type="button" id="picker-cancel" class="rounded-lg border border-line px-4 py-2 text-[13px] font-semibold">Cancel</button>
                <button type="button" id="picker-use" class="rounded-lg bg-accent px-4 py-2 text-[13px] font-semibold text-white disabled:opacity-40" disabled>Use this picture</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';
    const URLS = {
        apply: @json(route('analyses.edit.apply', $run->public_id)),
        restore: @json(url('/analyses/'.$run->public_id.'/edit/restore')),
        upload: @json(route('analyses.edit.upload', $run->public_id)),
        save: @json(route('analyses.edit.save', $run->public_id)),
        unsplash: @json(route('analyses.edit.unsplash', $run->public_id)),
        aiImage: @json(route('analyses.edit.ai-image', $run->public_id)),
        pricing: @json(route('pricing')),
        frame: @json($frameUrl),
        positioning: @json($api('positioning')), analyse: @json($api('positioning.analyse')), generate: @json($api('generate')),
        options: @json($api('options')), approveBase: @json(url('/api/projects/'.$run->public_id.'/hero/options')), jobBase: @json(url('/api/projects/'.$run->public_id.'/hero/jobs')),
        diagnose: @json($api('diagnose')), versionsApi: @json($api('versions')),
        validate: @json($api('validate-render')), renderStatus: @json($api('render-status')), acceptException: @json($api('accept-exception')),
    };
    const CSRF = @json(csrf_token());
    let S = @json($state);
    const root = document.getElementById('editor');
    const $ = (id) => document.getElementById(id);
    const frame = $('frame');
    let device = 'desktop';
    let selected = null; // the field the picker is editing

    // ------------------------------------------------------------------ tiny DOM helper (text only — never innerHTML with user content)
    function h(tag, attrs, ...kids) {
        const e = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs || {})) {
            if (v === false || v == null) continue;
            if (k === 'class') e.className = v; else if (k.startsWith('on')) e.addEventListener(k.slice(2), v); else e.setAttribute(k, v === true ? '' : v);
        }
        kids.flat().forEach((c) => { if (c == null || c === false) return; e.append(c.nodeType ? c : document.createTextNode(String(c))); });
        return e;
    }
    const btn = (label, cls, onclick, attrs) => h('button', Object.assign({ type: 'button', class: cls, onclick }, attrs || {}), label);
    const INPUT = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20';

    // ------------------------------------------------------------------ frame scaling + device
    const WIDTHS = { desktop: 1440, tablet: 768, mobile: 390 };
    function fit() {
        const wrap = $('frame-wrap'), canvas = $('canvas');
        const w = WIDTHS[device];
        const avail = canvas.clientWidth - (window.innerWidth >= 1024 ? 40 : 24);
        const scale = Math.min(1, avail / w);
        wrap.style.width = w + 'px';
        wrap.style.transform = 'scale(' + scale + ')';
        wrap.style.marginBottom = (-(wrap.offsetHeight * (1 - scale))) + 'px';
        wrap.style.marginLeft = Math.max(0, (canvas.clientWidth - (window.innerWidth >= 1024 ? 40 : 24) - w * scale) / 2) + 'px';
        frame.style.height = Math.max(900, Math.round((canvas.clientHeight - 40) / scale)) + 'px';
        sizeToContent();
    }
    function sizeToContent() {
        try { const d = frame.contentDocument; if (d && d.documentElement) frame.style.height = Math.max(900, d.documentElement.scrollHeight) + 'px'; } catch (e) { /* cross-origin: keep fixed height */ }
    }
    document.querySelectorAll('.device-btn').forEach((b) => b.addEventListener('click', () => {
        device = b.dataset.device;
        document.querySelectorAll('.device-btn').forEach((x) => { const on = x === b; x.classList.toggle('bg-ink', on); x.classList.toggle('text-canvas', on); x.classList.toggle('text-body', !on); });
        fit();
    }));
    window.addEventListener('resize', fit);
    frame.addEventListener('load', () => { fit(); setTimeout(fit, 400); setTimeout(fit, 1500); if (keepScroll !== null) { const y = keepScroll; keepScroll = null; [60, 450, 1100].forEach((t) => setTimeout(() => { $('canvas').scrollTop = y; }, t)); } });
    let keepScroll = null;
    const reloadFrame = () => { keepScroll = $('canvas').scrollTop; frame.src = URLS.frame + '?t=' + Date.now(); };
    const toFrame = (m) => frame.contentWindow && frame.contentWindow.postMessage(m, location.origin);

    // ------------------------------------------------------------------ saving (sequential, so base_version is always right)
    let chain = Promise.resolve(), pending = 0;
    const undoStack = [], redoStack = [];
    function setSave(state, text) {
        const dot = $('save-state').firstElementChild;
        dot.className = 'h-1.5 w-1.5 rounded-full ' + (state === 'saving' ? 'bg-amber-500 animate-pulse' : state === 'error' ? 'bg-bad' : state === 'draft' ? 'bg-warn' : 'bg-ok');
        $('save-text').textContent = text;
    }
    // Edits are kept on the server as you go (a draft); "Save layout" turns the draft into a saved layout.
    function settle() { S.unsaved ? setSave('draft', 'Layout not saved yet') : setSave('saved', 'Layout saved'); }
    function banner(msg, actions) {
        const b = $('banner');
        if (!msg) { b.classList.add('hidden'); b.replaceChildren(); return; }
        b.replaceChildren(h('span', {}, msg + ' '), ...(actions || []));
        b.classList.remove('hidden');
    }
    function notes(list) {
        const n = $('notes');
        const items = (list || []).filter((w) => w.level !== 'tip' || true);
        if (!items.length) { n.classList.add('hidden'); n.replaceChildren(); return; }
        n.replaceChildren(h('p', { class: 'mb-1 font-semibold' }, 'Worth a look'), ...items.slice(0, 4).map((w) => h('p', { class: 'mt-1' }, (w.level === 'confirm' ? '⚠ ' : '💡 ') + w.message)));
        n.classList.remove('hidden');
    }

    function commit(ops, opts) {
        opts = opts || {};
        pending++; setSave('saving', 'Saving…');
        const job = chain.then(async () => {
            let res, data = {};
            try {
                res = await fetch(URLS.apply, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify({ base_version: S.version, ops }) });
                data = await res.json().catch(() => ({}));
            } catch (e) { throw { network: true }; }
            if (res.status === 401) { location.href = @json(route('login')); throw { handled: true }; }
            if (res.status === 409) throw { stale: true, message: data.message };
            if (!res.ok) throw { message: data.message || 'That change could not be saved.' };
            Object.assign(S, { version: data.version, sections: data.sections, hidden: data.hidden, destinations: data.destinations, assets: data.assets, versions: data.versions, edits: data.edits, hero_status: data.hero_status, hero_fit: data.hero_fit, unsaved: data.unsaved, pass: data.pass, fonts: data.fonts, widgets: data.widgets, widget_types: data.widget_types || S.widget_types });
            notes(data.warnings);
            if (opts.record !== false) { undoStack.push({ undo: typeof opts.inverse === 'function' ? opts.inverse(data) : opts.inverse, redo: ops }); redoStack.length = 0; }
            return data;
        });
        chain = job.catch(() => {});
        return job.then((data) => { pending--; if (!pending) settle(); refreshChrome(); if (opts.reload) reloadFrame(); renderPage(); renderHistory(); renderAddons(); return data; }, (err) => {
            pending--;
            if (err.stale) { setSave('error', 'Out of date'); banner('This page was changed somewhere else (another tab?).', [btn('Reload the latest', 'font-semibold underline', () => location.reload())]); }
            else if (err.network) { setSave('error', 'Offline — not saved'); banner('We couldn’t reach the server, so your last change isn’t saved yet.', [btn('Try again', 'font-semibold underline', () => { banner(); if (opts.retry) opts.retry(); })]); }
            else if (!err.handled) { setSave('error', 'That change wasn’t saved'); banner(err.message); setTimeout(() => { banner(); if (!pending) settle(); }, 6000); }
            if (opts.revert) opts.revert();
            renderPage(); renderAddons(); // put every field back to what is actually saved
            throw err;
        });
    }
    // Export Pass on the Free plan: two saved edits and two more downloads per website.
    function renderPass() {
        const n = $('pass-note'), p = S.pass;
        if (!p) { n.classList.add('hidden'); n.classList.remove('flex'); n.replaceChildren(); return; }
        const plural = (k, w) => k + ' ' + w + (k === 1 ? '' : 's');
        const used = p.edits <= 0;
        n.replaceChildren(
            h('span', {}, h('strong', {}, 'Export Pass'), ' · ' + (used ? 'no saved edits left' : plural(p.edits, 'saved edit') + ' left') + ' and ' + plural(p.downloads, 'download') + ' left for this website. Unlimited edits and downloads on Starter or Agency.'),
            h('a', { href: @json(route('analyses.upgrade', ['analysisRun' => $run->public_id])), class: 'shrink-0 font-semibold underline' }, 'See plans'));
        n.classList.remove('hidden'); n.classList.add('flex');
    }
    function refreshChrome() {
        renderPass();
        $('undo').disabled = !undoStack.length; $('redo').disabled = !redoStack.length;
        $('save-layout').disabled = !S.unsaved || pending > 0;
        const c = $('edit-count');
        c.textContent = S.edits ? S.edits + ' edit' + (S.edits === 1 ? '' : 's') : ''; c.classList.toggle('hidden', !S.edits);
    }
    async function saveLayout() {
        await chain;
        setSave('saving', 'Saving layout…');
        let res, d = {};
        try {
            res = await fetch(URLS.save, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify({ base_version: S.version }) });
            d = await res.json().catch(() => ({}));
        } catch (e) { setSave('error', 'Offline — not saved'); banner('We couldn’t reach the server, so the layout isn’t saved yet.'); throw e; }
        if (res.status === 401) { location.href = @json(route('login')); throw new Error('auth'); }
        if (!res.ok) { setSave('error', 'Not saved'); banner(d.message || 'The layout could not be saved.', res.status === 409 ? [btn('Reload the latest', 'font-semibold underline', () => location.reload())] : []); throw new Error(d.message || 'save'); }
        Object.assign(S, d); setSave('saved', 'Layout saved'); refreshChrome(); renderHistory(); renderPage();
    }
    const CAN_LAUNCH = @json($canLaunch);
    const upgradeModal = $('upgrade-modal');
    $('upgrade-later').addEventListener('click', () => { upgradeModal.classList.add('hidden'); upgradeModal.classList.remove('flex'); });
    upgradeModal.addEventListener('keydown', (e) => e.key === 'Escape' && $('upgrade-later').click());
    $('save-layout').addEventListener('click', () => saveLayout().then(() => { if (!CAN_LAUNCH) { upgradeModal.classList.remove('hidden'); upgradeModal.classList.add('flex'); $('upgrade-later').focus(); } }).catch(() => {}));
    // Approving always approves a saved layout: anything still unsaved is saved first.
    $('approve-form').addEventListener('submit', async (e) => {
        if (!S.unsaved && !pending) return;
        e.preventDefault();
        try { await chain; if (S.unsaved) await saveLayout(); } catch (x) { return; }
        e.target.submit();
    });
    $('undo').addEventListener('click', () => step(undoStack, redoStack, 'undo', 'redo'));
    $('redo').addEventListener('click', () => step(redoStack, undoStack, 'redo', 'undo'));
    function step(from, to, useKey, pushKey) {
        const e = from.pop(); if (!e) return;
        const ops = e[useKey]; if (!ops) { refreshChrome(); return; }
        commit(ops, { record: false, reload: true }).then(() => { to.push(e); refreshChrome(); }).catch(() => from.push(e));
    }
    document.addEventListener('keydown', (ev) => {
        if (!(ev.metaKey || ev.ctrlKey) || ev.key.toLowerCase() !== 'z') return;
        if (ev.target.closest('input, textarea, [contenteditable]')) return;
        ev.preventDefault(); (ev.shiftKey ? $('redo') : $('undo')).click();
    });
    window.addEventListener('beforeunload', (e) => { if (pending) { e.preventDefault(); e.returnValue = ''; } });

    // ------------------------------------------------------------------ field helpers
    const section = (id) => S.sections.find((s) => s.instance_id === id);
    const field = (id, path) => (section(id)?.fields || []).find((f) => f.path === path);

    function opsForText(f, id, value) { return [{ op: 'set', instance_id: id, path: f.path, value }]; }
    function setField(id, f, value) {
        const prev = f.value;
        return commit(opsForText(f, id, value), { inverse: opsForText(f, id, prev), revert: () => toFrame({ type: 'set-text', id, path: f.path, value: prev }) })
            .then(() => { const nf = field(id, f.path); nf && toFrame({ type: 'set-text', id, path: f.path, value: nf.value }); }).catch(() => {});
    }
    function ctaOps(id, f, label, dest) {
        const op = { op: 'set_cta', instance_id: id, path: f.path, label };
        if (dest.startsWith('key:')) op.destination_key = dest.slice(4); else op.href = dest.slice(5);
        return [op];
    }
    function setCta(id, f, label, dest) {
        const prev = { label: f.value, href: f.href };
        return commit(ctaOps(id, f, label, dest), { inverse: prev.label ? ctaOps(id, f, prev.label, 'href:' + prev.href) : null, reload: true }).catch(() => {});
    }

    // ------------------------------------------------------------------ messages from the page
    window.addEventListener('message', (e) => {
        if (e.origin !== location.origin || e.source !== frame.contentWindow) return;
        const m = e.data || {};
        const f = m.id ? field(m.id, m.path) : null;
        if (m.type === 'focus' && f) { showHint(f, m.value.length); selectField(m.id, m.path, false); }
        if (m.type === 'input' && f) showHint(f, m.length);
        if (m.type === 'edit' && f) { hideHint(); setField(m.id, f, m.value); }
        if (m.type === 'widget-drop') addWidget(m.widget, m.after);
        if (m.type === 'widget-move') moveWidget(m.id, m.after, m.before);
        if (m.type === 'widget-select') { openWidget = m.id; tab('addons'); setTimeout(() => { const c = document.querySelector('[data-wcard="' + m.id + '"]'); c && c.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }, 30); }
        if (m.type === 'select' && f) { selectField(m.id, m.path, false); if (f.type === 'image') openPicker(m.id, f); }
        if (m.type === 'ready') { $('canvas-tip').classList.remove('opacity-0'); setTimeout(() => $('canvas-tip').classList.add('opacity-0', 'transition-opacity', 'duration-700'), 5000); }
    });
    function showHint(f, len) {
        const hint = $('hint'); const max = f.max || 0;
        const isHeadline = f.path === 'headline' && section(f.id || '')?.component_id !== 'x';
        let t = len + (max ? ' / ' + max : '') + ' characters';
        let warn = max && len > max;
        if (f.path === 'headline') {
            const min = +root.dataset.hintMin, tgt = +root.dataset.hintMax, soft = +root.dataset.hintSoft;
            if (len > soft) { t += ' — long headlines wrap to many lines; shorter reads faster.'; warn = true; }
            else if (len > tgt) t += ' — a bit long; one clear idea works best.';
            else if (len < min && len > 0) t += ' — quite short; say what you do.';
            else t += ' — good length.';
        }
        hint.textContent = (f.label ? f.label + ': ' : '') + t;
        hint.className = 'border-b px-4 py-2.5 text-[12px] ' + (warn ? 'border-warn/30 bg-warn-soft text-warn' : 'border-line bg-accent-soft text-accent-ink');
    }
    const hideHint = () => $('hint').classList.add('hidden');

    // ------------------------------------------------------------------ tabs
    function tab(name) {
        document.querySelectorAll('.tab-btn').forEach((b) => { const on = b.dataset.tab === name; b.classList.toggle('border-accent', on); b.classList.toggle('text-accent-ink', on); b.classList.toggle('border-transparent', !on); b.classList.toggle('text-muted', !on); });
        ['page', 'addons', 'hero', 'history'].forEach((n) => $('panel-' + n).classList.toggle('hidden', n !== name));
        if (name === 'hero') loadHero();
        if (name === 'addons') renderAddons();
    }
    document.querySelectorAll('.tab-btn').forEach((b) => b.addEventListener('click', () => tab(b.dataset.tab)));

    // ------------------------------------------------------------------ PAGE panel: click ONE element (on the page or in the list) and see only its options
    const openSections = new Set(['hero']);
    let selField = null; // { id, path } — the one element being edited
    function selectField(id, path, fromPanel) {
        selField = { id, path };
        tab('page'); renderPage();
        const sec = section(id); sec && openSections.add(sec.component_id === 'hero' ? 'hero' : id);
        if (fromPanel !== false) {
            toFrame({ type: 'locate-field', id, path });
            const main = $('f-' + id + '-' + path.replace(/\./g, '-')); main && main.focus({ preventScroll: true });
        }
        $('panel-page').scrollTop = 0;
    }
    function clearSelection() { selField = null; hideHint(); renderPage(); }

    function renderPage() {
        const p = $('panel-page');
        const keepScroll = p.scrollTop;
        const sec = selField && section(selField.id), fld = sec && field(selField.id, selField.path);
        if (sec && fld) { p.replaceChildren(inspector(sec, fld)); p.scrollTop = keepScroll; return; }
        selField = null;
        p.replaceChildren(
            h('div', { class: 'mx-4 mt-3 rounded-xl bg-accent-soft px-3.5 py-3 text-[12.5px] leading-snug text-accent-ink' }, h('strong', {}, 'Click anything on the page'), ' — text, a button or a picture — and only its options show up here: wording, size, font and link. Changes save as you go and can be undone.'),
            ...S.sections.filter((s) => s.fields.length).map((s) => sectionCard(s)),
            ...(S.hidden.length ? [h('div', { class: 'm-4 rounded-xl border border-dashed border-line-strong p-3' }, h('p', { class: 'text-[12px] font-semibold text-body' }, 'Hidden sections'),
                ...S.hidden.map((x) => h('div', { class: 'mt-2 flex items-center justify-between text-[13px]' }, h('span', {}, x.label), btn('Show again', 'font-semibold text-accent-ink', () => commit([{ op: 'show_section', instance_id: x.instance_id }], { inverse: [{ op: 'hide_section', instance_id: x.instance_id }], reload: true }).catch(() => {})))))] : []),
        );
        p.scrollTop = keepScroll;
    }
    const KIND = { string: 'Text', text: 'Text', cta: 'Button', image: 'Picture' };
    // The inspector: one element, everything about it, nothing else.
    function inspector(s, f) {
        return h('div', { class: 'flex flex-col gap-4 p-4', id: 'inspector' },
            btn('← All elements', 'self-start text-[12px] font-semibold text-accent-ink hover:text-ink', clearSelection),
            h('div', {}, h('p', { class: 'text-[11px] font-bold uppercase tracking-wider text-muted' }, (KIND[f.type] || 'Element') + ' · ' + s.label), h('p', { class: 'mt-0.5 text-[17px] font-extrabold leading-tight text-ink' }, f.label)),
            fieldControl(s, f, true),
            h('div', { class: 'flex items-center justify-between border-t border-line pt-3' },
                btn('Restore original', 'text-[12px] font-semibold text-muted hover:text-ink', () => commit([{ op: 'revert', instance_id: s.instance_id, path: f.path }], { reload: true, record: false }).catch(() => {})),
                btn('Done', 'rounded-lg bg-ink px-3.5 py-1.5 text-[12px] font-semibold text-canvas hover:opacity-85', clearSelection)),
            h('details', { class: 'rounded-lg border border-line px-3 py-2' }, h('summary', { class: 'cursor-pointer text-[12px] font-semibold text-body' }, 'This section: ' + s.label), h('div', { class: 'mt-3 flex flex-col gap-3' }, layoutControl(s),
                s.optional ? btn('Hide this section', 'self-start text-[12px] font-semibold text-bad', () => { selField = null; commit([{ op: 'hide_section', instance_id: s.instance_id }], { inverse: [{ op: 'show_section', instance_id: s.instance_id }], reload: true }).catch(() => {}); }) : null)));
    }
    // A compact row per element in the list: what it is, a peek at its content, and whether it has been styled or linked.
    function fieldRow(s, f) {
        const st = f.style || {}, styled = st.size || st.font, linked = st.href || (f.type === 'cta' && f.href && f.href !== '#');
        const peek = f.type === 'image' ? (f.url ? 'Picture set' : 'No picture') : (f.value ? f.value : 'Empty');
        return h('button', { type: 'button', class: 'flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left hover:bg-canvas', 'data-row': s.instance_id + '|' + f.path, onclick: () => selectField(s.instance_id, f.path) },
            f.type === 'image' && f.url ? h('img', { src: f.url, alt: '', class: 'h-9 w-12 shrink-0 rounded object-cover ring-1 ring-line' }) : h('span', { class: 'grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-surface-2 text-[11px] font-bold text-muted' }, f.type === 'cta' ? 'BTN' : (f.type === 'image' ? 'IMG' : 'Aa')),
            h('span', { class: 'min-w-0 flex-1' }, h('span', { class: 'block text-[12px] font-semibold text-ink' }, f.label), h('span', { class: 'block truncate text-[12px] ' + (f.value || f.url ? 'text-muted' : 'text-faint italic') }, peek)),
            styled ? h('span', { class: 'shrink-0 rounded bg-accent-soft px-1.5 py-0.5 text-[10px] font-bold text-accent-ink', title: 'Size or font changed' }, 'Aa') : null,
            linked ? h('span', { class: 'shrink-0 rounded bg-accent-soft px-1.5 py-0.5 text-[10px] font-bold text-accent-ink', title: 'Has a link' }, '🔗') : null);
    }
    function sectionCard(s) {
        const open = openSections.has(s.component_id === 'hero' ? 'hero' : s.instance_id);
        const key = s.component_id === 'hero' ? 'hero' : s.instance_id;
        const body = h('div', { class: 'flex flex-col gap-1 px-3 pb-3' }, ...s.fields.map((f) => fieldRow(s, f)),
            h('div', { class: 'flex items-center justify-between px-2.5 pt-2' },
                btn('Restore original wording', 'text-[12px] font-semibold text-muted hover:text-ink', () => commit([{ op: 'revert', instance_id: s.instance_id }], { reload: true, record: false }).catch(() => {})),
                s.optional ? btn('Hide section', 'text-[12px] font-semibold text-bad', () => commit([{ op: 'hide_section', instance_id: s.instance_id }], { inverse: [{ op: 'show_section', instance_id: s.instance_id }], reload: true }).catch(() => {})) : null));
        const head = h('button', { type: 'button', class: 'flex w-full items-center justify-between px-4 py-3 text-left text-[13px] font-bold text-ink', 'aria-expanded': String(open), onclick: () => { open ? openSections.delete(key) : openSections.add(key); renderPage(); toFrame({ type: 'scroll-section', id: s.instance_id }); } },
            h('span', {}, s.label, h('span', { class: 'ml-2 text-[11px] font-medium text-faint' }, s.fields.length + ' items')), h('span', { class: 'text-faint' }, open ? '–' : '+'));
        return h('section', { class: 'border-b border-line', 'data-section': s.instance_id }, head, open ? body : null);
    }
    // ---- Size & font, links, and section layout (width, columns)
    const NUM = 'w-20 rounded-lg border border-line-strong bg-surface px-2 py-1.5 text-[13px] text-ink focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20';
    function styleOps(f, id, size, font) { return [{ op: 'set_style', instance_id: id, path: f.path, size: size === '' || size == null ? null : +size, font: font || null }]; }
    function setStyle(id, f, size, font) {
        const prev = f.style || {};
        return commit(styleOps(f, id, size, font), { inverse: styleOps(f, id, prev.size, prev.font), reload: true }).catch(() => {});
    }
    function styleControl(s, f, expanded) {
        const st = f.style || {};
        const size = h('input', { type: 'number', min: 12, max: 120, step: 1, inputmode: 'numeric', class: NUM, placeholder: 'Auto', 'aria-label': f.label + ' size in pixels' });
        size.value = st.size ?? '';
        const font = h('select', { class: INPUT, 'aria-label': f.label + ' font' }, h('option', { value: '' }, 'Site font'), ...(S.fonts || []).map((x) => h('option', { value: x.id }, x.label)));
        font.value = st.font || '';
        const go = () => { const n = size.value === '' ? '' : Math.max(12, Math.min(120, Math.round(+size.value) || 12)); if (size.value !== '') size.value = n; setStyle(s.instance_id, f, n, font.value); };
        size.addEventListener('change', go); font.addEventListener('change', go);
        const fontLabel = st.font ? ((S.fonts || []).find((x) => x.id === st.font) || {}).label : null;
        const active = st.size || st.font || st.href;
        const sum = [st.size ? st.size + ' px' : null, fontLabel, st.href ? 'linked' : null].filter(Boolean).join(', ');
        return h('details', { class: 'rounded-lg border border-line bg-canvas/40 px-3 py-2', open: (expanded || active) ? '' : null },
            h('summary', { class: 'cursor-pointer text-[12px] font-semibold text-body' }, f.linkable ? 'Size, font & link' : 'Size & font', active ? h('span', { class: 'ml-1.5 font-normal text-accent-ink' }, '· ' + sum) : null),
            h('div', { class: 'mt-2 flex items-center gap-2' }, h('label', { class: 'text-[12px] text-muted' }, 'Size'), size, h('span', { class: 'text-[12px] text-muted' }, 'px'), font),
            h('p', { class: 'mt-1.5 text-[11px] leading-snug text-muted' }, 'Sizes shrink smoothly on phones. Leave empty to keep the design’s own size and font.'),
            (st.size || st.font) ? btn('Reset size & font', 'mt-1 text-[12px] font-semibold text-muted hover:text-ink', () => setStyle(s.instance_id, f, '', '')) : null,
            f.linkable ? h('div', { class: 'mt-3' }, linkControl(s, f)) : null);
    }
    function linkControl(s, f) {
        const cur = (f.style && f.style.href) || '';
        const input = h('input', { type: 'text', class: INPUT, placeholder: 'https://…   mailto:…   tel:…   or #section', 'aria-label': f.label + ' link', maxlength: 500 });
        input.value = cur;
        const save = () => {
            let v = input.value.trim();
            if (v && !/^(https:|mailto:|tel:|#|\/)/i.test(v)) { v = /^[\w.-]+\.[a-z]{2,}(\/|$)/i.test(v) ? 'https://' + v : v; } // "example.com" → https://example.com
            input.value = v;
            if (v === cur) return;
            const ops = (href) => [{ op: 'set_link', instance_id: s.instance_id, path: f.path, href }];
            commit(ops(v), { inverse: ops(cur), reload: true }).catch(() => {});
        };
        input.addEventListener('blur', save);
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); input.blur(); } });
        return h('div', {}, h('label', { class: 'mb-1 block text-[12px] font-semibold text-body' }, 'Link', h('span', { class: 'font-normal text-faint' }, ' (optional — makes this clickable)')), input);
    }
    function layoutControl(s) {
        const L = s.layout || { width: 'standard', split: null, supports_split: false };
        const layoutOps = (w, sp) => [{ op: 'set_layout', instance_id: s.instance_id, width: w, split: sp }];
        const set = (w, sp, prev) => commit(layoutOps(w, sp), { inverse: layoutOps(prev.width, prev.split), reload: false, revert: () => toFrame({ type: 'preview-layout', id: s.instance_id, width: prev.width, split: prev.split }) }).catch(() => {});
        const width = h('select', { class: INPUT, 'aria-label': 'Content width of ' + s.label },
            ...[['standard', 'Standard (design default)'], ['narrow', 'Narrow — focused, easy to read'], ['wide', 'Wide'], ['full', 'Full width']].map(([v, t]) => h('option', { value: v }, t)));
        width.value = L.width || 'standard';
        width.addEventListener('change', () => { toFrame({ type: 'preview-layout', id: s.instance_id, width: width.value, split: L.split }); set(width.value, undefined, L); });
        const rows = [h('label', { class: 'mb-1 block text-[12px] font-semibold text-body' }, 'Container width'), width];
        if (L.supports_split) {
            const val = L.split || 0;
            const range = h('input', { type: 'range', min: 30, max: 70, step: 1, class: 'w-full accent-[var(--accent,#6d28d9)]', 'aria-label': 'Left column width' });
            range.value = val || 50;
            const readout = h('span', { class: 'text-[12px] font-semibold text-ink' }, val ? val + '% / ' + (100 - val) + '%' : 'Design default');
            range.addEventListener('input', () => { readout.textContent = range.value + '% / ' + (100 - range.value) + '%'; toFrame({ type: 'preview-layout', id: s.instance_id, width: width.value, split: +range.value }); });
            range.addEventListener('change', () => set(undefined, +range.value, L));
            rows.push(h('div', { class: 'mt-3 flex items-center justify-between' }, h('label', { class: 'text-[12px] font-semibold text-body' }, 'Column widths (left / right)'), readout), range,
                L.split ? btn('Back to the design’s columns', 'mt-1 text-[12px] font-semibold text-muted hover:text-ink', () => set(undefined, null, L)) : null);
        }
        const changed = (L.width && L.width !== 'standard') || L.split;
        return h('details', { class: 'rounded-lg border border-line bg-canvas/40 px-3 py-2', open: changed ? '' : null },
            h('summary', { class: 'cursor-pointer text-[12px] font-semibold text-body' }, 'Layout: width & columns', changed ? h('span', { class: 'ml-1.5 font-normal text-accent-ink' }, '· customised') : null),
            h('div', { class: 'mt-2' }, ...rows));
    }
    function fieldControl(s, f, expanded) {
        const id = 'f-' + s.instance_id + '-' + f.path.replace(/\./g, '-');
        const label = h('label', { for: id, class: 'mb-1 block text-[12px] font-semibold text-body' }, f.label, f.required ? null : h('span', { class: 'font-normal text-faint' }, ' (optional)'));
        if (f.type === 'string' || f.type === 'text') {
            const input = f.type === 'text' ? h('textarea', { id, rows: Math.min(6, Math.max(2, Math.ceil((f.value || '').length / 44))), maxlength: f.max, class: INPUT }) : h('input', { id, type: 'text', maxlength: f.max, class: INPUT });
            input.value = f.value || '';
            input.addEventListener('input', () => showHint(f, input.value.length));
            input.addEventListener('focus', () => { showHint(f, input.value.length); toFrame({ type: 'locate-field', id: s.instance_id, path: f.path }); });
            input.addEventListener('blur', () => { hideHint(); if (input.value.trim() !== (f.value || '').trim()) setField(s.instance_id, f, input.value).then(() => { const nf = field(s.instance_id, f.path); if (nf) input.value = nf.value; }); });
            return h('div', { 'data-field': s.instance_id + '|' + f.path, class: 'rounded-lg transition-shadow' }, label, input,
                f.value ? h('div', { class: 'mt-2' }, styleControl(s, f, expanded)) : null);
        }
        if (f.type === 'cta') {
            const lab = h('input', { id, type: 'text', maxlength: 50, class: INPUT, placeholder: 'Button text' }); lab.value = f.value || '';
            const known = S.destinations.find((d) => true && false);
            const sel = h('select', { class: INPUT + ' mt-2', 'aria-label': f.label + ' goes to' });
            S.destinations.forEach((d) => sel.append(h('option', { value: 'key:' + d.key }, 'Goes to: ' + d.label)));
            sel.append(h('option', { value: 'href:' + (f.href || '') }, f.href ? 'Keep current: ' + f.href : 'Keep current link'));
            sel.append(h('option', { value: 'custom' }, 'Another link (https://, email or phone)…'));
            sel.value = 'href:' + (f.href || '');
            const custom = h('input', { type: 'text', class: INPUT + ' mt-2 hidden', placeholder: 'https://… or mailto:… or tel:…', 'aria-label': 'Custom link' });
            const save = () => { if (!lab.value.trim() && f.required) { lab.value = f.value; return; } const dest = sel.value === 'custom' ? 'href:' + custom.value.trim() : sel.value; if (sel.value === 'custom' && !custom.value.trim()) return; setCta(s.instance_id, f, lab.value, dest); };
            lab.addEventListener('blur', () => { if (lab.value.trim() !== (f.value || '').trim()) save(); });
            lab.addEventListener('focus', () => toFrame({ type: 'locate-field', id: s.instance_id, path: f.path }));
            sel.addEventListener('change', () => { if (sel.value === 'custom') { custom.classList.remove('hidden'); custom.focus(); } else { custom.classList.add('hidden'); save(); } });
            custom.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); custom.blur(); } });
            custom.addEventListener('blur', () => custom.value.trim() && save());
            return h('div', { 'data-cta': s.instance_id + '|' + f.path, 'data-field': s.instance_id + '|' + f.path, class: 'rounded-lg transition-shadow' }, label, lab, sel, custom, f.value ? h('div', { class: 'mt-2' }, styleControl(s, f, expanded)) : null);
        }
        if (f.type === 'image') {
            return h('div', { 'data-field': s.instance_id + '|' + f.path, class: 'rounded-lg transition-shadow' }, h('p', { class: 'mb-1 text-[12px] font-semibold text-body' }, f.label),
                h('div', { class: 'flex items-center gap-3' },
                    f.url ? h('img', { src: f.url, alt: '', class: 'h-14 w-20 rounded-lg object-cover ring-1 ring-line' }) : h('div', { class: 'flex h-14 w-20 items-center justify-center rounded-lg bg-canvas text-[11px] text-faint' }, 'None'),
                    btn(f.url ? 'Change picture' : 'Add a picture', 'rounded-lg border border-line-strong px-3 py-1.5 text-[12px] font-semibold hover:border-accent', () => openPicker(s.instance_id, f))),
                f.url && f.linkable ? h('details', { class: 'mt-2 rounded-lg border border-line bg-canvas/40 px-3 py-2', open: (expanded || (f.style && f.style.href)) ? '' : null }, h('summary', { class: 'cursor-pointer text-[12px] font-semibold text-body' }, 'Link', (f.style && f.style.href) ? h('span', { class: 'ml-1.5 font-normal text-accent-ink' }, '· linked') : null), h('div', { class: 'mt-2' }, linkControl(s, f))) : null);
        }
        return null;
    }
    // ------------------------------------------------------------------ picture picker
    let pickChoice = null;
    function openPicker(id, f) {
        selected = { id, f }; pickChoice = f.asset_id || null; setSrc('mine');
        $('picker-alt').value = f.alt || ''; $('picker-error').classList.add('hidden');
        $('picker-remove').classList.toggle('hidden', !(f.asset_id && !f.required));
        renderPicker(); $('picker').classList.remove('hidden'); $('picker').classList.add('flex'); $('picker-cancel').focus();
    }
    // ---- Unsplash tab
    let pickSrc = 'mine', usPhoto = null, usPage = 1, usQuery = '';
    function setSrc(src) {
        pickSrc = src; usPhoto = null;
        document.querySelectorAll('.src-btn').forEach((b) => { const on = b.dataset.src === src; b.classList.toggle('bg-ink', on); b.classList.toggle('text-canvas', on); b.classList.toggle('text-body', !on); });
        $('picker-unsplash').classList.toggle('hidden', src !== 'unsplash'); $('picker-ai').classList.toggle('hidden', src !== 'ai'); $('picker-mine').classList.toggle('hidden', src !== 'mine');
        src === 'unsplash' ? ($('us-q').focus(), renderUs()) : src === 'ai' ? openAi() : renderPicker();
        $('picker-use').textContent = src === 'unsplash' ? 'Use this photo' : 'Use this picture';
    }
    document.querySelectorAll('.src-btn').forEach((b) => b.addEventListener('click', () => setSrc(b.dataset.src)));
    function renderUs() { $('picker-use').disabled = !usPhoto; document.querySelectorAll('#us-grid [data-id]').forEach((el) => { const on = usPhoto && usPhoto.id === el.dataset.id; el.classList.toggle('border-accent', on); el.classList.toggle('border-transparent', !on); el.setAttribute('aria-pressed', String(!!on)); }); }
    async function usSearch(more) {
        const q = $('us-q').value.trim(); if (q.length < 2) return;
        if (!more) { usQuery = q; usPage = 1; $('us-grid').replaceChildren(); usPhoto = null; } else usPage++;
        $('us-msg').textContent = 'Searching…'; $('us-more').classList.add('hidden');
        try {
            const d = await api(URLS.unsplash + '?q=' + encodeURIComponent(usQuery) + '&page=' + usPage);
            $('us-msg').textContent = d.results.length || more ? 'Photos from Unsplash. The photographer is credited below each picture.' : 'No photos found. Try a simpler word.';
            $('us-grid').append(...d.results.map((p) => h('div', { class: 'overflow-hidden rounded-xl border-2 border-transparent text-left', 'data-id': p.id, role: 'button', tabindex: '0', 'aria-pressed': 'false', onclick: () => { usPhoto = p; if (!$('picker-alt').value) $('picker-alt').value = p.alt || ''; renderUs(); } },
                h('img', { src: p.thumb, alt: p.alt, loading: 'lazy', class: 'aspect-[4/3] w-full cursor-pointer object-cover' }),
                h('a', { href: p.author_url, target: '_blank', rel: 'noopener', class: 'block truncate px-2 py-1 text-[11px] text-muted hover:text-ink', onclick: (e) => e.stopPropagation() }, 'Photo by ' + p.author + ' / Unsplash'))));
            $('us-more').classList.toggle('hidden', usPage >= d.total_pages); renderUs();
        } catch (e) { $('us-msg').textContent = e.message; }
    }
    $('us-form').addEventListener('submit', (e) => { e.preventDefault(); usSearch(false); });
    $('us-more').addEventListener('click', () => usSearch(true));
    // ---- Meta AI Image tab (Muse Image): a description starts from this section's own words
    let aiQuota = null, aiAsset = null;
    function aiSuggestion() {
        const sec = selected && S.sections.find((x) => x.instance_id === selected.id); if (!sec) return '';
        const texts = sec.fields.filter((f) => (f.type === 'string' || f.type === 'text') && f.value).map((f) => f.value.trim());
        return texts.slice(0, 2).join(' — ').slice(0, 300);
    }
    function aiShowQuota() {
        const q = aiQuota; if (!q) return;
        const left = q.remaining; const go = $('ai-go');
        $('ai-quota').textContent = q.limit === null ? 'Unlimited AI images' : left + ' of ' + q.limit + ' AI image' + (q.limit === 1 ? '' : 's') + ' left' + (q.period === 'month' ? ' this month' : '');
        go.disabled = !q.configured || (left !== null && left < 1);
        const msg = $('ai-msg');
        if (!q.configured) { msg.textContent = 'AI images aren’t set up yet.'; msg.className = 'mt-2 text-[12px] text-muted'; }
        else if (left !== null && left < 1) {
            msg.replaceChildren(document.createTextNode(q.period === 'month' ? 'You’ve used this month’s AI images — they reset on ' + new Date(q.resets_at + 'T00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'long' }) + '. ' : 'You’ve used your free AI images. '), ...(q.can_upgrade ? [h('a', { href: URLS.pricing, class: 'font-semibold text-accent-ink underline' }, 'Upgrade for 5 every month')] : []));
            msg.className = 'mt-2 text-[12px] text-body';
        }
    }
    async function openAi() {
        $('picker-use').disabled = !aiAsset || pickChoice !== aiAsset.asset_id;
        if (!$('ai-prompt').value.trim()) $('ai-prompt').value = aiSuggestion();
        $('ai-prompt').focus();
        try { aiQuota = await api(URLS.aiImage); aiShowQuota(); } catch (e) { $('ai-quota').textContent = ''; }
    }
    $('ai-go').addEventListener('click', async () => {
        const prompt = $('ai-prompt').value.trim(), msg = $('ai-msg'), go = $('ai-go');
        if (prompt.length < 8) { msg.textContent = 'Describe the picture in a few words first.'; msg.className = 'mt-2 text-[12px] text-bad'; return; }
        go.disabled = true; go.textContent = 'Making your image… (up to a minute)'; msg.className = 'mt-2 hidden text-[12px]';
        try {
            const d = await api(URLS.aiImage, 'POST', { prompt, alt: $('picker-alt').value.trim() || prompt.slice(0, 200) });
            aiQuota = Object.assign({}, aiQuota, d); delete aiQuota.asset; aiAsset = d.asset; S.assets.unshift(d.asset); pickChoice = d.asset.asset_id;
            $('ai-img').src = d.asset.url; $('ai-img').alt = d.asset.alt || ''; $('ai-result').classList.remove('hidden');
            if (!$('picker-alt').value.trim()) $('picker-alt').value = d.asset.alt || '';
            $('picker-use').disabled = false;
            msg.textContent = 'Here it is! Press “Use this picture”, or change the description and make another.'; msg.className = 'mt-2 text-[12px] text-ok';
        } catch (e) {
            msg.textContent = e.message; msg.className = 'mt-2 text-[12px] text-bad';
            if (e.data && 'remaining' in e.data) aiQuota = Object.assign({}, aiQuota, e.data);
        }
        go.textContent = '✦ Generate image'; aiShowQuota(); if (msg.className.includes('text-bad')) msg.classList.remove('hidden');
    });
    function closePicker() { $('picker').classList.add('hidden'); $('picker').classList.remove('flex'); selected = null; aiAsset = null; $('ai-result').classList.add('hidden'); $('ai-prompt').value = ''; $('ai-msg').className = 'mt-2 hidden text-[12px]'; }
    function renderPicker() {
        const g = $('picker-grid');
        g.replaceChildren(...S.assets.filter((a) => a.url).map((a) => h('button', { type: 'button', class: 'overflow-hidden rounded-xl border-2 text-left ' + (pickChoice === a.asset_id ? 'border-accent ring-2 ring-[#6D28D9]/30' : 'border-transparent hover:border-line-strong'), 'aria-pressed': String(pickChoice === a.asset_id), onclick: () => { pickChoice = a.asset_id; if (!$('picker-alt').value) $('picker-alt').value = a.alt || ''; renderPicker(); } },
            h('img', { src: a.url, alt: a.alt || '', class: 'aspect-[4/3] w-full object-cover' }),
            h('span', { class: 'block truncate px-2 py-1 text-[11px] text-muted' }, (a.origin === 'user_upload' ? 'Uploaded' : 'From your site') + (a.width ? ' · ' + a.width + '×' + a.height : '')))));
        if (!S.assets.some((a) => a.url)) g.replaceChildren(h('p', { class: 'col-span-full text-[13px] text-muted' }, 'No pictures were found on your site. Upload one below.'));
        $('picker-use').disabled = !pickChoice;
    }
    $('picker-close').addEventListener('click', closePicker); $('picker-cancel').addEventListener('click', closePicker);
    $('picker').addEventListener('keydown', (e) => e.key === 'Escape' && closePicker());
    $('picker-use').addEventListener('click', async () => {
        if (pickSrc === 'unsplash') {
            if (!usPhoto) return;
            const btnUse = $('picker-use'); btnUse.disabled = true; btnUse.textContent = 'Adding…';
            try {
                const d = await api(URLS.unsplash, 'POST', { photo_id: usPhoto.id, alt: $('picker-alt').value.trim() || usPhoto.alt });
                S.assets.unshift(d.asset); pickChoice = d.asset.asset_id; usPhoto = null;
                if (!$('picker-alt').value.trim()) $('picker-alt').value = d.asset.alt || 'Photo';
            } catch (e) { $('us-msg').textContent = e.message; btnUse.textContent = 'Use this photo'; renderUs(); return; }
        }
        if (selected.onPick) { const cb = selected.onPick; const alt0 = $('picker-alt').value.trim() || 'Image'; closePicker(); cb(pickChoice, alt0); return; }
        const { id, f } = selected; const prev = f.asset_id ? [{ op: 'set_image', instance_id: id, path: f.path, asset_id: f.asset_id, alt: f.alt || 'Image' }] : [{ op: 'clear_image', instance_id: id, path: f.path }];
        const alt = $('picker-alt').value.trim() || 'Image';
        closePicker();
        commit([{ op: 'set_image', instance_id: id, path: f.path, asset_id: pickChoice, alt }], { inverse: prev, reload: true }).catch(() => {});
    });
    $('picker-remove').addEventListener('click', () => { if (selected.onClear) { const cb = selected.onClear; closePicker(); cb(); return; } const { id, f } = selected; closePicker(); commit([{ op: 'clear_image', instance_id: id, path: f.path }], { inverse: [{ op: 'set_image', instance_id: id, path: f.path, asset_id: f.asset_id, alt: f.alt || 'Image' }], reload: true }).catch(() => {}); });
    $('picker-file').addEventListener('change', async (e) => {
        const file = e.target.files[0]; if (!file) return;
        const err = $('picker-error'); err.classList.add('hidden');
        const fd = new FormData(); fd.append('image', file); fd.append('alt', $('picker-alt').value);
        $('picker-use').disabled = true;
        try {
            const r = await fetch(URLS.upload, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: fd });
            const d = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(d.message || (d.errors && Object.values(d.errors)[0][0]) || 'Upload failed.');
            S.assets.unshift(d.asset); pickChoice = d.asset.asset_id;
        } catch (x) { err.textContent = x.message; err.classList.remove('hidden'); }
        e.target.value = ''; renderPicker();
    });

    // ------------------------------------------------------------------ ADD-ONS tab: drag a widget onto the page, then set it up
    let openWidget = null;
    const WT = () => S.widget_types || {};
    const defaultAfter = () => { const secs = S.sections; const last = secs[secs.length - 1]; return last && last.component_id === 'footer' && secs.length > 1 ? secs[secs.length - 2].instance_id : (last ? last.instance_id : 'top'); };
    function addWidget(type, after) {
        const d = WT()[type]; if (!d) return;
        const ops = [{ op: 'add_widget', type, after: after || defaultAfter() }];
        return commit(ops, { reload: true, inverse: (data) => [{ op: 'remove_widget', widget_id: data.widgets[data.widgets.length - 1].id }] })
            .then((data) => { openWidget = data.widgets[data.widgets.length - 1].id; tab('addons'); renderAddons(); setTimeout(() => { const c = document.querySelector('[data-wcard="' + openWidget + '"]'); c && c.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); toFrame({ type: 'scroll-widget', id: openWidget }); }, 500); })
            .catch(() => {});
    }
    function renderAddons() {
        const p = $('panel-addons'); if (!p || !S.widgets) return;
        const keep = p.scrollTop, act = document.activeElement && p.contains(document.activeElement) ? { k: document.activeElement.dataset.k, s: document.activeElement.selectionStart, e: document.activeElement.selectionEnd } : null;
        const groups = {}; Object.entries(WT()).forEach(([k, d]) => (groups[d.group] = groups[d.group] || []).push([k, d]));
        p.replaceChildren(
            h('p', { class: 'px-4 pt-3 text-[12px] text-muted' }, 'Drag an add-on onto your page, or press Add. Then set it up right here.'),
            ...Object.entries(groups).map(([g, list]) => h('div', { class: 'px-4 pt-3' }, h('p', { class: 'mb-2 text-[11px] font-bold uppercase tracking-wider text-muted' }, g), h('div', { class: 'grid grid-cols-2 gap-2' }, ...list.map(([k, d]) => paletteCard(k, d))))),
            h('div', { class: 'mt-4 border-t border-line px-4 py-3' }, h('p', { class: 'mb-2 text-[13px] font-bold' }, 'On your page' + (S.widgets.length ? ' (' + S.widgets.length + ')' : '')),
                S.widgets.length ? S.widgets.map(widgetCard) : h('p', { class: 'text-[12px] text-muted' }, 'Nothing added yet.')));
        p.scrollTop = keep;
        if (act && act.k) { const el = p.querySelector('[data-k="' + act.k + '"]'); if (el) { el.focus(); try { el.setSelectionRange(act.s, act.e); } catch (x) { /* not a text control */ } } }
    }
    function paletteCard(k, d) {
        const used = d.single && S.widgets.some((w) => w.type === k);
        return h('div', { class: 'rounded-xl border border-line bg-surface p-3 ' + (used ? 'opacity-55' : 'cursor-grab hover:border-accent'), draggable: used ? null : 'true', title: used ? 'Already on your page' : 'Drag onto your page',
            ondragstart: (e) => { e.dataTransfer.setData('text/plain', 'redo-widget:' + k); e.dataTransfer.effectAllowed = 'copy'; }, ondragend: () => toFrame({ type: 'drag-end' }) },
            h('div', { class: 'text-lg leading-none' }, d.icon), h('p', { class: 'mt-1.5 text-[12px] font-bold' }, d.label), h('p', { class: 'mt-0.5 text-[11px] leading-snug text-muted' }, d.blurb),
            btn(used ? 'Added' : 'Add to page', 'mt-2 w-full rounded-md border border-line-strong px-2 py-1 text-[11px] font-semibold ' + (used ? 'text-faint' : 'hover:border-accent'), () => { if (!used) addWidget(k); }, used ? { disabled: '' } : null));
    }
    function widgetCard(w) {
        const d = WT()[w.type]; if (!d) return null; const open = openWidget === w.id;
        return h('section', { class: 'mb-2 rounded-xl border bg-surface ' + (open ? 'border-accent' : 'border-line'), 'data-wcard': w.id },
            h('button', { type: 'button', class: 'flex w-full items-center gap-2.5 px-3 py-2.5 text-left', 'aria-expanded': String(open), onclick: () => { openWidget = open ? null : w.id; renderAddons(); !open && toFrame({ type: 'scroll-widget', id: w.id }); } },
                h('span', { class: 'text-lg' }, d.icon), h('span', { class: 'min-w-0 flex-1' }, h('span', { class: 'block text-[13px] font-bold' }, d.label), h('span', { class: 'block truncate text-[11px] text-muted' }, w.summary || '')), h('span', { class: 'text-faint' }, open ? '–' : '+')),
            open ? widgetForm(w, d) : null);
    }
    // ---- moving an add-on up or down the page (drag its grip on the page, or use the arrows in the add-on's card)
    const wPlace = (w) => (S.widget_types && S.widget_types[w.type] ? S.widget_types[w.type].place : 'inline');
    const wSlot = (w) => w.after || 'top';
    function moveWidget(id, after, before) {
        const w = S.widgets.find((x) => x.id === id); if (!w) return Promise.resolve();
        const nxt = S.widgets.slice(S.widgets.indexOf(w) + 1).find((x) => wPlace(x) === 'inline' && wSlot(x) === wSlot(w));
        return commit([{ op: 'move_widget', widget_id: id, after, before: before || '' }], { reload: true, inverse: [{ op: 'move_widget', widget_id: id, after: wSlot(w), before: nxt ? nxt.id : '' }] })
            .then(() => { openWidget = id; renderAddons(); setTimeout(() => toFrame({ type: 'scroll-widget', id }), 450); }).catch(() => {});
    }
    // The page top to bottom, as it is drawn: add-ons at the very top, then each section followed by the add-ons placed after it.
    function pageBlocks() {
        const inline = S.widgets.filter((x) => wPlace(x) === 'inline');
        const out = inline.filter((x) => wSlot(x) === 'top').map((x) => ({ kind: 'widget', w: x }));
        S.sections.forEach((sec) => { out.push({ kind: 'section', id: sec.instance_id }); inline.filter((x) => wSlot(x) === sec.instance_id).forEach((x) => out.push({ kind: 'widget', w: x })); });
        return out;
    }
    function stepWidget(w, dir) {
        const blocks = pageBlocks(), i = blocks.findIndex((b) => b.kind === 'widget' && b.w.id === w.id), t = blocks[i + dir];
        if (i < 0 || !t) return Promise.resolve();
        const followers = (slot) => S.widgets.filter((x) => x.id !== w.id && wPlace(x) === 'inline' && wSlot(x) === slot);
        if (dir < 0) { // above the block before it
            if (t.kind === 'widget') return moveWidget(w.id, wSlot(t.w), t.w.id);
            const k = S.sections.findIndex((sec) => sec.instance_id === t.id);
            return moveWidget(w.id, k > 0 ? S.sections[k - 1].instance_id : 'top', '');
        }
        if (t.kind === 'section') return moveWidget(w.id, t.id, (followers(t.id)[0] || {}).id || ''); // right under that section, above its other add-ons
        const after = followers(wSlot(t.w)), n = after[after.findIndex((x) => x.id === t.w.id) + 1];
        return moveWidget(w.id, wSlot(t.w), n ? n.id : '');
    }
    const sectionLabelFor = (id) => { const s = section(id); return s ? s.label : id; };
    function widgetForm(w, d) {
        const save = (patch, extra) => commit([{ op: 'update_widget', widget_id: w.id, config: patch }], Object.assign({ reload: true, inverse: [{ op: 'update_widget', widget_id: w.id, config: Object.fromEntries(Object.keys(patch).map((k) => [k, w.config[k]])) }] }, extra || {})).catch(() => {});
        const box = h('div', { class: 'flex flex-col gap-3 border-t border-line px-3 pb-3 pt-3' });
        if (d.place === 'inline') {
            const sel = h('select', { class: INPUT, 'aria-label': 'Where it sits on the page' }, h('option', { value: 'top' }, 'At the very top of the page'), ...S.sections.map((s) => h('option', { value: s.instance_id }, 'After: ' + s.label)));
            sel.value = w.after || 'top';
            sel.addEventListener('change', () => commit([{ op: 'move_widget', widget_id: w.id, after: sel.value }], { reload: true, inverse: [{ op: 'move_widget', widget_id: w.id, after: w.after || 'top' }] }).catch(() => {}));
            const blocks = pageBlocks(), at = blocks.findIndex((b) => b.kind === 'widget' && b.w.id === w.id);
            const arrow = (label, dir, off) => h('button', { type: 'button', class: 'flex-1 rounded-lg border border-line-strong px-3 py-1.5 text-[12px] font-semibold hover:border-accent disabled:opacity-40', disabled: off ? '' : null, onclick: () => stepWidget(w, dir) }, label);
            box.append(h('div', {}, h('p', { class: 'mb-1 text-[12px] font-semibold text-body' }, 'Move on the page'),
                h('div', { class: 'flex gap-2' }, arrow('↑ Move up', -1, at <= 0), arrow('↓ Move down', 1, at < 0 || at >= blocks.length - 1)),
                h('p', { class: 'mt-1 text-[11px] text-muted' }, 'Or grab “⠿ Drag to move” on the add-on in the page and drop it between any two rows.')));
            box.append(h('div', {}, h('label', { class: 'mb-1 block text-[12px] font-semibold text-body' }, 'Where it goes'), sel));
        } else if (d.place === 'global') box.append(h('p', { class: 'text-[12px] text-muted' }, 'Floats on every screen, bottom of the page. It doesn’t move with the sections.'));
        else if (d.place === 'head') box.append(h('p', { class: 'text-[12px] text-muted' }, 'Works on your published website (not in this editor).'));
        d.fields.forEach((f) => { const c = widgetField(w, f, w.config[f.key], (v) => save({ [f.key]: v }), w.config, 'w|' + w.id + '|' + f.key); c && box.append(c); });
        box.append(h('div', { class: 'flex items-center justify-between pt-1' }, h('span', { class: 'text-[11px] text-faint' }, 'Saved as you go'),
            btn('Remove add-on', 'text-[12px] font-semibold text-bad', () => commit([{ op: 'remove_widget', widget_id: w.id }], { reload: true, inverse: [{ op: 'add_widget', type: w.type, after: w.after || 'top', config: w.config }] }).then(() => { openWidget = null; renderAddons(); }).catch(() => {}))));
        return box;
    }
    // ---- Hero "Design" chooser: a wireframe of every layout, with its name underneath, so the look is clear before choosing
    const HERO_WIRES = (() => {
        const ink = '#3B3558', soft = '#CFC8E8', acc = '#6D28D9', line = '#B9B2D6', bg = '#F5F4F8';
        const bar = (x, y, w, hgt, c) => `<rect x="${x}" y="${y}" width="${w}" height="${hgt}" rx="${Math.min(2, hgt / 2)}" fill="${c || ink}"/>`;
        const pic = (x, y, w, hgt, c) => `<rect x="${x}" y="${y}" width="${w}" height="${hgt}" rx="3" fill="${c || soft}"/><path d="M${x + w * .18} ${y + hgt * .78} L${x + w * .42} ${y + hgt * .42} L${x + w * .6} ${y + hgt * .66} L${x + w * .72} ${y + hgt * .52} L${x + w * .86} ${y + hgt * .78} Z" fill="#fff" opacity=".7"/><circle cx="${x + w * .72}" cy="${y + hgt * .26}" r="${Math.max(2, hgt * .08)}" fill="#fff" opacity=".8"/>`;
        const btn = (x, y, w, two) => two ? `<rect x="${x}" y="${y}" width="${w}" height="6" rx="3" fill="none" stroke="${acc}" stroke-width="1.2"/>` : `<rect x="${x}" y="${y}" width="${w}" height="6" rx="3" fill="${acc}"/>`;
        const txtL = (x, y, w, dark) => bar(x, y, w * .35, 2, line) + bar(x, y + 6, w, 5, dark || ink) + bar(x, y + 13, w * .8, 5, dark || ink) + bar(x, y + 22, w * .9, 2, line) + bar(x, y + 26, w * .65, 2, line) + btn(x, y + 33, w * .38) + btn(x + w * .42, y + 33, w * .32, true);
        const frame = (inner) => `<svg viewBox="0 0 120 76" class="block w-full" aria-hidden="true"><rect x=".6" y=".6" width="118.8" height="74.8" rx="6" fill="${bg}" stroke="#D9D6E0"/>${inner}</svg>`;
        return {
            'split': frame(txtL(10, 16, 48) + pic(66, 12, 44, 52)),
            'image-left': frame(pic(10, 12, 44, 52) + txtL(62, 16, 48)),
            'banner-left': frame(pic(6, 8, 108, 60, '#BDB4E0') + bar(14, 20, 36, 5, '#fff') + bar(14, 28, 30, 5, '#fff') + bar(14, 37, 40, 2, '#E6E2F5') + btn(14, 46, 20)),
            'banner-right': frame(pic(6, 8, 108, 60, '#BDB4E0') + bar(70, 20, 36, 5, '#fff') + bar(70, 28, 30, 5, '#fff') + bar(66, 37, 40, 2, '#E6E2F5') + btn(80, 46, 20)),
            'fullscreen-left': frame(pic(0, 0, 58, 76, '#C5BDE4') + txtL(68, 18, 42)),
            'fullscreen-right': frame(pic(62, 0, 58, 76, '#C5BDE4') + txtL(10, 18, 44)),
            'bold-left': frame(bar(10, 12, 78, 9) + bar(10, 25, 94, 9) + bar(10, 38, 60, 9) + btn(10, 58, 24) + btn(38, 58, 20, true)),
            'bold-right': frame(bar(10, 12, 78, 9) + bar(10, 25, 94, 9) + bar(10, 38, 60, 9) + btn(86, 58, 24) + btn(60, 58, 22, true)),
            'centered': frame(bar(42, 9, 36, 2, line) + bar(24, 15, 72, 6) + bar(34, 24, 52, 6) + bar(40, 33, 40, 2, line) + btn(46, 40, 28) + pic(22, 52, 76, 18)),
            'aerial-fullscreen': frame(pic(0, 0, 120, 76, '#B2A8DC') + bar(30, 24, 60, 6, '#fff') + bar(38, 33, 44, 6, '#fff') + bar(42, 43, 36, 2, '#E6E2F5') + btn(46, 52, 28)),
            'editorial-asymmetric': frame(bar(10, 14, 22, 2, line) + bar(10, 19, 20, 2, line) + bar(10, 24, 24, 2, line) + bar(40, 12, 40, 7) + bar(40, 22, 34, 7) + btn(40, 36, 22) + pic(84, 8, 28, 60)),
            'product-showcase': frame(txtL(10, 16, 44) + `<rect x="62" y="12" width="48" height="50" rx="4" fill="#fff" stroke="${line}"/><circle cx="67" cy="17" r="1.6" fill="${line}"/><circle cx="72" cy="17" r="1.6" fill="${line}"/><circle cx="77" cy="17" r="1.6" fill="${line}"/>${bar(66, 24, 14, 3, soft)}${bar(66, 30, 10, 3, soft)}${pic(84, 24, 22, 32, acc)}`),
            'typography-led': frame(bar(10, 12, 100, 11) + bar(10, 27, 78, 11) + bar(10, 42, 48, 2, line) + btn(10, 47, 24) + pic(0, 60, 120, 16, '#C5BDE4')),
        };
    })();
    function wireframePicker(f, value, onChange, k) {
        const cur = value || f.default || Object.keys(f.options)[0];
        const grid = h('div', { class: 'grid grid-cols-3 gap-2', role: 'radiogroup', 'aria-label': f.label, 'data-k': k });
        Object.entries(f.options).forEach(([v, name]) => {
            const on = v === cur, card = h('button', { type: 'button', role: 'radio', 'aria-checked': String(on), title: name, class: 'flex flex-col items-center gap-1 rounded-lg border-2 p-1 text-center transition hover:border-accent ' + (on ? 'border-accent bg-accent-soft/60 ring-2 ring-accent/25' : 'border-line bg-surface'), onclick: () => { if (v !== cur) onChange(v); } });
            const art = h('span', { class: 'block w-full overflow-hidden rounded' }); art.innerHTML = HERO_WIRES[v] || '';
            card.append(art, h('span', { class: 'block px-0.5 text-[10.5px] font-semibold leading-tight ' + (on ? 'text-accent-ink' : 'text-body') }, name));
            grid.append(card);
        });
        return h('div', {}, h('p', { class: 'mb-1.5 text-[12px] font-semibold text-body' }, f.label, h('span', { class: 'ml-1 font-normal text-faint' }, '— pick a layout')), grid);
    }
    function widgetField(w, f, value, onChange, cfg, k) {
        if (f.showWhen && cfg[f.showWhen[0]] !== f.showWhen[1]) return null;
        const label = h('label', { class: 'mb-1 block text-[12px] font-semibold text-body' }, f.label);
        const help = f.help ? h('p', { class: 'mt-1 text-[11px] leading-snug text-muted' }, f.help) : null;
        if (f.type === 'text' || f.type === 'url') { const i = h('input', { type: 'text', class: INPUT, maxlength: f.max || 500, 'data-k': k }); i.value = value || ''; i.addEventListener('change', () => i.value.trim() !== (value || '') && onChange(i.value)); return h('div', {}, label, i, help); }
        if (f.type === 'textarea' || f.type === 'code') { const i = h('textarea', { rows: f.type === 'code' ? 7 : 3, class: INPUT + (f.type === 'code' ? ' font-mono text-[12px]' : ''), maxlength: f.max || 800, spellcheck: f.type === 'code' ? 'false' : null, 'data-k': k, placeholder: f.type === 'code' ? '<div>…</div>' : null }); i.value = value || ''; i.addEventListener('change', () => i.value !== (value || '') && onChange(i.value)); return h('div', {}, label, i, help); }
        if (f.type === 'number') { const i = h('input', { type: 'number', class: INPUT + ' w-28', min: f.min, max: f.max, 'data-k': k }); i.value = value ?? ''; i.addEventListener('change', () => onChange(+i.value)); return h('div', {}, label, i); }
        if (f.type === 'select' && f.visual === 'hero-wireframes') return wireframePicker(f, value, onChange, k);
        if (f.type === 'select') { const i = h('select', { class: INPUT, 'data-k': k }, ...Object.entries(f.options).map(([v, t]) => h('option', { value: v }, t))); i.value = value ?? f.default ?? Object.keys(f.options)[0]; i.addEventListener('change', () => onChange(i.value)); return h('div', {}, label, i); }
        if (f.type === 'bool') { const i = h('input', { type: 'checkbox', class: 'h-4 w-4 accent-[var(--accent,#6d28d9)]', 'data-k': k }); i.checked = !!value; i.addEventListener('change', () => onChange(i.checked)); return h('label', { class: 'flex items-center gap-2 text-[12px] font-semibold text-body' }, i, f.label); }
        if (f.type === 'image') {
            const url = value && value.asset_id ? (S.assets.find((a) => a.asset_id === value.asset_id) || {}).url : null;
            return h('div', {}, h('p', { class: 'mb-1 text-[12px] font-semibold text-body' }, f.label), h('div', { class: 'flex items-center gap-3' },
                url ? h('img', { src: url, alt: '', class: 'h-14 w-20 rounded-lg object-cover ring-1 ring-line' }) : h('div', { class: 'flex h-14 w-20 items-center justify-center rounded-lg bg-canvas text-[11px] text-faint' }, 'None'),
                btn(url ? 'Change picture' : 'Add a picture', 'rounded-lg border border-line-strong px-3 py-1.5 text-[12px] font-semibold hover:border-accent', () => pickForWidget(value, (id, alt) => onChange({ asset_id: id, alt }), () => onChange(null)))));
        }
        if (f.type === 'list') return listField(w, f, value || [], onChange, k);
        return null;
    }
    function pickForWidget(cur, onPick, onClear) {
        selected = { onPick, onClear }; pickChoice = cur && cur.asset_id || null; setSrc('mine');
        $('picker-alt').value = (cur && cur.alt) || ''; $('picker-error').classList.add('hidden'); $('picker-remove').classList.toggle('hidden', !pickChoice);
        renderPicker(); $('picker').classList.remove('hidden'); $('picker').classList.add('flex'); $('picker-cancel').focus();
    }
    function listField(w, f, rows, onChange, k) {
        const blank = () => Object.fromEntries(f.items.map((x) => [x.key, x.default ?? (x.type === 'bool' ? false : '')]));
        const commitRows = (next) => onChange(next);
        const itemTitle = (r, i) => { const v = r[f.itemLabel]; const o = (f.items.find((x) => x.key === f.itemLabel) || {}).options; return (o && o[v]) || v || ('Item ' + (i + 1)); };
        const cards = rows.map((r, i) => h('div', { class: 'rounded-lg border border-line bg-canvas/40 p-2.5' },
            h('div', { class: 'mb-2 flex items-center justify-between gap-2' }, h('span', { class: 'truncate text-[12px] font-bold text-ink' }, (i + 1) + '. ' + String(itemTitle(r, i)).replace(/ \(.*\)$/, '')),
                h('span', { class: 'flex shrink-0 gap-1 text-[12px]' },
                    i > 0 ? btn('↑', 'rounded border border-line px-1.5', () => { const n = rows.slice(); [n[i - 1], n[i]] = [n[i], n[i - 1]]; commitRows(n); }, { 'aria-label': 'Move up' }) : null,
                    i < rows.length - 1 ? btn('↓', 'rounded border border-line px-1.5', () => { const n = rows.slice(); [n[i + 1], n[i]] = [n[i], n[i + 1]]; commitRows(n); }, { 'aria-label': 'Move down' }) : null,
                    rows.length > (f.min || 0) ? btn('✕', 'rounded border border-line px-1.5 text-bad', () => commitRows(rows.filter((_, j) => j !== i)), { 'aria-label': 'Remove' }) : null)),
            h('div', { class: 'flex flex-col gap-2' }, ...f.items.map((sub) => widgetField(w, sub, r[sub.key], (v) => commitRows(rows.map((x, j) => (j === i ? Object.assign({}, x, { [sub.key]: v }) : x))), r, k + '|' + i + '|' + sub.key)))));
        return h('div', {}, h('p', { class: 'mb-1.5 text-[12px] font-semibold text-body' }, f.label), h('div', { class: 'flex flex-col gap-2' }, ...cards),
            rows.length < (f.max || 10) ? btn('＋ Add ' + (f.label.toLowerCase().replace(/s$/, '')), 'mt-2 w-full rounded-lg border border-dashed border-line-strong px-3 py-2 text-[12px] font-semibold text-body hover:border-accent hover:text-accent-ink', () => commitRows(rows.concat([blank()]))) : null);
    }

    // ------------------------------------------------------------------ HERO COPY tab (positioning → options → approve)
    let heroLoaded = false;
    const api = async (url, method, body) => {
        const r = await fetch(url, { method: method || 'GET', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Idempotency-Key': method === 'POST' ? (crypto.randomUUID ? crypto.randomUUID() : String(Date.now())) : '' }, body: body ? JSON.stringify(body) : undefined });
        const d = await r.json().catch(() => ({})); if (!r.ok) throw Object.assign(new Error(d.message || 'Something went wrong.'), { status: r.status, data: d }); return d;
    };
    async function poll(job, onTick) {
        for (let i = 0; i < 120; i++) {
            await new Promise((r) => setTimeout(r, 2000));
            const j = await api(URLS.jobBase + '/' + job.job_id); onTick && onTick(j);
            if (j.status === 'complete') return j; if (j.status === 'failed') throw new Error(j.error || 'That step failed.');
        }
        throw new Error('This is taking longer than expected. Try again in a moment.');
    }
    async function loadHero(force) {
        const p = $('panel-hero');
        if (heroLoaded && !force) return; heroLoaded = true;
        p.replaceChildren(h('p', { class: 'p-4 text-[13px] text-muted' }, 'Loading…'));
        try {
            const o = await api(URLS.options);
            const running = o.job && ['pending', 'running'].includes(o.job.status);
            if (running) return waitFor(o.job);
            renderHero(o.options);
        } catch (e) { renderHero([]); }
    }
    function heroIntro(extra) {
        return h('div', { class: 'p-4' }, h('p', { class: 'text-[14px] font-bold' }, 'Sharper hero wording'),
            h('p', { class: 'mt-1 text-[12px] leading-snug text-body' }, 'We read what your site actually says, then write up to three different headlines. Every option is checked: anything we can’t back up from your site — numbers, “leading”, “best”, certifications — is rejected, not suggested.'), extra);
    }
    function renderHero(options) {
        const p = $('panel-hero');
        const usable = options.filter((o) => o.status !== 'rejected' || o.hard_gate_status === 'pass');
        const start = btn(options.length ? 'Write new options' : 'Suggest hero wording', 'mt-3 w-full rounded-lg bg-accent px-4 py-2.5 text-[13px] font-semibold text-white hover:bg-accent-strong', () => run());
        p.replaceChildren(fitCard(), heroIntro(start), ...options.map(optionCard));
        refreshFit();
    }

    // ---- Does it fit? Measured in a real browser at desktop, tablet and mobile — never guessed, never fixed by shrinking type.
    const PILL = { pass: ['Fits', 'bg-ok-soft text-ok'], fail: ['Too long', 'bg-bad-soft text-bad'], pending: ['Not checked', 'bg-surface-2 text-muted'], unavailable: ['Can’t measure here', 'bg-warn-soft text-warn'] };
    function fitCard() { return h('div', { id: 'fit-card', class: 'mx-4 mt-4 hidden rounded-xl border border-line p-4' }); }
    function pill(vp, st) { const [t, c] = PILL[st] || PILL.pending; return h('span', { class: 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ' + c }, vp + ': ' + t); }
    function renderFit(status, extra) {
        const card = $('fit-card'); if (!card) return;
        const v = S.hero_fit; if (!v) { card.classList.add('hidden'); return; }
        card.classList.remove('hidden');
        const rows = [h('p', { class: 'text-[13px] font-bold' }, 'Does it fit your layout?'),
            h('div', { class: 'mt-2 flex flex-wrap gap-1.5' }, pill('Desktop', v.desktop_fit), pill('Tablet', v.tablet_fit), pill('Mobile', v.mobile_fit))];
        if (extra && extra.busy) rows.push(h('div', { class: 'mt-3 flex items-center gap-2 text-[12px] text-accent-ink' }, h('span', { class: 'h-3.5 w-3.5 animate-spin rounded-full border-2 border-accent border-t-transparent' }), extra.busy));
        if (extra && extra.message) rows.push(h('p', { class: 'mt-3 text-[12px] leading-snug text-body' }, extra.message));
        (extra && extra.reasons || []).slice(0, 4).forEach((r) => rows.push(h('p', { class: 'mt-1 text-[12px] text-bad' }, '• ' + r.message)));
        if (extra && extra.shots && extra.shots.mobile) rows.push(h('img', { src: extra.shots.mobile, alt: 'How the hero looks on a phone', class: 'mt-3 max-h-48 rounded-lg ring-1 ring-line' }));
        const failing = [v.desktop_fit, v.tablet_fit, v.mobile_fit].includes('fail');
        rows.push(h('div', { class: 'mt-3 flex flex-wrap gap-2' },
            btn('Check fit', 'rounded-lg border border-line-strong px-3 py-1.5 text-[12px] font-semibold hover:border-accent', () => runFit()),
            failing ? btn('Edit the headline myself', 'rounded-lg border border-line-strong px-3 py-1.5 text-[12px] font-semibold', () => { const hs = S.sections.find((x) => x.component_id === 'hero'); hs && selectField(hs.instance_id, 'headline'); }) : null,
            failing && S.hero_status !== 'layout_exception' ? btn('Keep it as it is', 'rounded-lg border border-line-strong px-3 py-1.5 text-[12px] font-semibold text-body', async () => { try { await api(URLS.acceptException, 'POST'); location.reload(); } catch (e) { banner(e.message); } }) : null));
        card.replaceChildren(...rows);
    }
    async function refreshFit() {
        renderFit();
        try { const s = await api(URLS.renderStatus); if (s.validation) S.hero_fit = s.validation; renderFit(null, s.last && s.last.result === 'fail' ? { reasons: s.last.reasons, shots: s.last.screenshots, message: 'Measured on the real page, with fonts loaded. REDOLIO shortens wording rather than shrinking your type.' } : null); } catch (e) { /* the card still shows the saved status */ }
    }
    let fitRunning = false;
    async function runFit() {
        if (fitRunning) return; fitRunning = true;
        renderFit(null, { busy: 'Measuring your hero on desktop, tablet and phone… about 20 seconds' });
        try {
            const j = await api(URLS.validate, 'POST');
            const done = await poll(j, () => {});
            const r = done.result || {};
            await refreshFit();
            if (r.copy_refinements || r.layout_adjustments) { banner('We tightened the hero so it fits' + (r.layout_adjustments ? ' (and moved to a roomier layout)' : '') + '. Reloading your page…'); setTimeout(() => location.reload(), 1200); }
            else if (r.status === 'needs_review') renderFit(null, { reasons: r.reasons, shots: r.screenshots, message: 'We couldn’t make this fit automatically within safe limits. You choose: edit the wording, or keep it as it is.' });
            else if (r.status === 'not_measured') renderFit(null, { message: 'Fit can’t be measured in this environment, so it isn’t claimed.' });
        } catch (e) { renderFit(null, { message: e.message }); }
        fitRunning = false;
    }
    async function run() {
        try {
            const prof = await api(URLS.positioning).catch((e) => (e.status === 404 ? null : Promise.reject(e)));
            if (!prof || !['ready', 'needs_confirmation'].includes(prof.status)) { const j = await api(URLS.analyse, 'POST'); await waitFor(j, 'Reading your site… about 20 seconds'); }
            const g = await api(URLS.generate, 'POST'); await waitFor(g, 'Writing and checking options… about 30 seconds');
        } catch (e) { $('panel-hero').replaceChildren(heroIntro(h('p', { class: 'mt-3 rounded-lg bg-bad-soft p-3 text-[12px] text-bad' }, e.message)), btn('Try again', 'm-4 rounded-lg border border-line-strong px-3 py-2 text-[13px] font-semibold', () => run())); }
    }
    async function waitFor(job, msg) {
        $('panel-hero').replaceChildren(heroIntro(h('div', { class: 'mt-4 flex items-center gap-3 rounded-xl bg-accent-soft p-3 text-[13px] text-accent-ink' }, h('span', { class: 'h-4 w-4 animate-spin rounded-full border-2 border-accent border-t-transparent' }), msg || 'Working…')));
        const done = await poll(job);
        if (done.kind === 'positioning') return done;
        const o = await api(URLS.options); renderHero(o.options); return done;
    }
    const LABEL = { recommended: ['Recommended', 'bg-ok-soft text-ok'], needs_review: ['Needs your review', 'bg-warn-soft text-warn'], rewrite: ['Needs a rewrite', 'bg-warn-soft text-warn'], rejected: ['Can’t be used', 'bg-bad-soft text-bad'] };
    function optionCard(o) {
        const [lab, cls] = LABEL[o.recommendation] || LABEL.needs_review;
        const blocked = o.hard_gate_status !== 'pass';
        return h('article', { class: 'mx-4 mb-4 rounded-xl border p-4 ' + (o.status === 'approved' ? 'border-accent bg-accent-soft' : 'border-line') },
            h('div', { class: 'mb-2 flex items-center justify-between gap-2' }, h('span', { class: 'rounded-full px-2 py-0.5 text-[11px] font-bold ' + cls }, lab), h('span', { class: 'text-[11px] text-faint', title: 'An editorial estimate, not a measured conversion prediction.' }, o.strategy_id.replace('-', ' ') + ' · ' + Math.round(o.score.total) + '/100')),
            o.eyebrow ? h('p', { class: 'text-[11px] font-bold uppercase tracking-wider text-muted' }, o.eyebrow) : null,
            h('p', { class: 'text-[17px] font-extrabold leading-snug' }, o.headline),
            h('p', { class: 'mt-1.5 text-[13px] leading-snug text-body' }, o.description),
            o.primary_cta ? h('p', { class: 'mt-2 text-[12px] font-semibold text-accent-ink' }, 'Button: ' + o.primary_cta.label) : null,
            o.rationale ? h('p', { class: 'mt-2 text-[12px] italic text-muted' }, o.rationale) : null,
            (o.issues || []).length ? h('ul', { class: 'mt-2 list-disc pl-4 text-[12px] ' + (blocked ? 'text-bad' : 'text-warn') }, ...o.issues.slice(0, 3).map((i) => h('li', {}, i.message))) : null,
            (o.relocate || []).length ? h('p', { class: 'mt-2 text-[12px] text-muted' }, 'Better placed elsewhere: ' + o.relocate.slice(0, 2).map((r) => (r.content.length > 48 ? r.content.slice(0, 48) + '…' : r.content) + ' → ' + r.belongs_in.split(/[.:;]/)[0]).join('; ')) : null,
            h('div', { class: 'mt-3 flex gap-2' },
                o.status === 'approved' ? h('span', { class: 'text-[12px] font-bold text-accent-ink' }, '✓ In use on your page')
                    : btn('Use this wording', 'flex-1 rounded-lg px-3 py-2 text-[13px] font-semibold ' + (blocked ? 'cursor-not-allowed bg-surface-2 text-faint' : 'bg-ink text-canvas hover:opacity-85'), () => approve(o), { disabled: blocked ? '' : null }),
                !blocked && o.status !== 'approved' ? btn('Dismiss', 'rounded-lg border border-line px-3 py-2 text-[12px] font-semibold text-body', () => reject(o)) : null));
    }
    async function approve(o) {
        try { await api(URLS.approveBase + '/' + o.option_id + '/approve', 'POST'); location.reload(); }
        catch (e) { banner(e.message); setTimeout(() => banner(), 7000); }
    }
    async function reject(o) { try { await api(URLS.approveBase + '/' + o.option_id + '/reject', 'POST'); loadHero(true); } catch (e) { banner(e.message); } }

    // ------------------------------------------------------------------ HISTORY tab
    function renderHistory() {
        $('panel-history').replaceChildren(h('p', { class: 'px-4 pt-3 text-[12px] text-muted' }, 'Every change is kept. Restoring an older version makes a new one, so nothing is ever lost.'),
            ...S.versions.map((v) => h('div', { class: 'flex items-start justify-between gap-3 border-b border-[#F0F0F0] px-4 py-3' },
                h('div', {}, h('p', { class: 'text-[13px] font-bold' }, 'Version ' + v.version + (v.version === S.version ? ' · current' : '')), h('p', { class: 'text-[12px] text-body' }, v.summary), h('p', { class: 'text-[11px] text-faint' }, v.at)),
                v.version === S.version ? null : btn('Restore', 'rounded-lg border border-line-strong px-3 py-1 text-[12px] font-semibold hover:border-accent', () => restore(v.version)))));
    }
    async function restore(version) {
        setSave('saving', 'Restoring…');
        try {
            const r = await fetch(URLS.restore + '/' + version, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } });
            const d = await r.json(); if (!r.ok) throw new Error(d.message);
            Object.assign(S, d); undoStack.length = redoStack.length = 0; setSave('saved', 'Restored version ' + version); refreshChrome(); renderPage(); renderHistory(); reloadFrame(); notes([]);
        } catch (e) { setSave('error', 'Not restored'); banner(e.message || 'Could not restore that version.'); setTimeout(() => banner(), 6000); }
    }

    renderPage(); renderHistory(); refreshChrome(); fit(); renderAddons();
    if (location.hash === '#hero') tab('hero');
    // A freshly approved hero is measured once, automatically (guarded so a reload never loops).
    try { if (S.hero_fit && ['approved'].includes(S.hero_status) && Object.values(S.hero_fit).slice(2).every((x) => x === 'pending') && !sessionStorage.getItem('fit-' + S.version)) { sessionStorage.setItem('fit-' + S.version, '1'); tab('hero'); setTimeout(runFit, 600); } } catch (e) { /* storage unavailable: skip auto-run */ }
})();
</script>
@endpush
