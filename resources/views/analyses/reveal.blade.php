@extends('layouts.app')

@section('title', 'REDOLIO — Before & after')

@php
    $hasAfter = ! empty($after);
    $canPublish = $user?->canPublish();
    $canExport = $user?->canExportStatic();
    $devices = array_values(array_filter(['desktop', 'tablet', 'mobile'], fn ($d) => isset($after[$d])));
    $primary = $after['desktop'] ?? ($after[array_key_first($after)] ?? null);
@endphp

@section('content')
    <x-app-nav :run="$run" />
    <x-stage-bar active="redo" />

    <div class="mx-auto flex max-w-[1400px] flex-col gap-8 px-6 py-8 lg:px-10">
        {{-- Heading --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-accent-ink">✨ Your REDO is ready</p>
                <h1 class="mt-1 text-[30px] font-black leading-tight tracking-tight text-ink sm:text-[38px]">Here’s your <span class="grad-text">new website</span></h1>
                <p class="mt-1 text-[13px] text-muted">{{ parse_url($run->normalized_url, PHP_URL_HOST) ?: $run->normalized_url }}
                    @if ($directionName) · <strong class="text-ink">{{ $directionName }}</strong> direction @endif
                    @if ($blueprintName) · <strong class="text-ink">{{ $blueprintName }}</strong> layout @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($v15)
                    <a href="{{ route('analyses.edit', $run->public_id) }}" class="btn btn-primary !px-5 !py-2.5 text-[13px]" title="Change wording, pictures, layout and add-ons right on the page. Free.">✎ Edit the design{{ $editCount ? ' · '.$editCount.' '.\Illuminate\Support\Str::plural('edit', $editCount) : '' }}</a>
                @else
                    <form method="POST" action="{{ route('analyses.make-editable', $run->public_id) }}" class="contents">@csrf
                        <button type="submit" class="btn btn-primary !px-5 !py-2.5 text-[13px]" title="Rebuild this design so you can change wording, pictures, layout and add-ons right on the page. Free.">✎ Edit the design</button>
                    </form>
                @endif
                <a href="{{ route('analyses.preview', $run->public_id) }}" class="rounded-lg border border-line bg-surface px-4 py-2 text-[13px] font-medium text-ink hover:border-line-strong">Open interactive preview</a>
                <a href="{{ ($canPublish || $canExport) ? route('analyses.launch', $run->public_id) : route('analyses.upgrade', [$run->public_id, 'intent' => 'approve']) }}" class="btn btn-dark !px-5 !py-2.5 text-[13px]">{{ ($canPublish || $canExport) ? 'Publish or download →' : 'Publish this website →' }}</a>
            </div>
        </div>

        @if (! $hasAfter)
            {{-- No real screenshots yet: capture them now. --}}
            <div id="reveal-preparing" class="flex flex-col items-center gap-4 rounded-2xl border border-line bg-surface px-8 py-20 text-center" data-run-url="{{ route('analyses.preview.visual-qa.run', [$run->public_id, $spec->version]) }}" data-status-base="{{ url('/analyses/'.$run->public_id.'/visual-qa') }}" data-pending="{{ $qaPending?->id }}">
                <svg class="h-9 w-9 animate-spin text-accent-ink" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <p class="text-lg font-bold text-ink">Preparing your before &amp; after…</p>
                <p class="max-w-md text-sm text-muted">We're rendering your redesign in a real browser at desktop, tablet and mobile sizes. This takes about a minute.</p>
                <p id="reveal-error" class="hidden max-w-md rounded-lg bg-bad-soft px-4 py-3 text-sm text-bad"></p>
            </div>
        @else
            {{-- The stage --}}
            <section aria-label="Before and after comparison" class="flex flex-col gap-4" id="reveal" data-has-before="{{ $before ? '1' : '0' }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="inline-flex rounded-full border border-line bg-surface p-1 text-[12px] font-semibold" role="tablist" aria-label="Comparison mode">
                        @foreach (['side' => 'Side by side', 'slider' => 'Slide to compare', 'after' => 'After only'] as $mode => $label)
                            @continue($mode !== 'after' && ! $before)
                            <button type="button" role="tab" data-mode="{{ $mode }}" class="reveal-mode rounded-full px-4 py-1.5 {{ $mode === ($before ? 'side' : 'after') ? 'bg-ink text-canvas' : 'text-body' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    @if (count($devices) > 1)
                        <div class="inline-flex rounded-full border border-line bg-surface p-1 text-[12px] font-semibold" role="tablist" aria-label="Device size for the redesign">
                            @foreach ($devices as $device)
                                <button type="button" role="tab" data-device="{{ $device }}" class="reveal-device rounded-full px-4 py-1.5 {{ $device === 'desktop' ? 'bg-accent text-white' : 'text-body' }}">{{ ucfirst($device) }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($before && $run->source_mode === 'fixture')
                    <p class="rounded-lg bg-warn-soft px-4 py-2.5 text-[12px] text-warn">Demo mode: the “before” is an illustrative rendering of this fictional business’s original site, built from its recorded content.</p>
                @endif

                @if (! $before)
                    <p class="rounded-lg bg-accent-soft px-4 py-2.5 text-[12px] text-accent-ink">An image of the original site could not be captured, so only the redesign is shown. The list below still explains what changed.</p>
                @endif

                {{-- Slider mode: both layers scroll together; the handle stays put. --}}
                <div class="reveal-stage {{ $before ? 'hidden' : '' }} relative overflow-hidden rounded-2xl border border-line bg-canvas shadow-sm" data-stage="slider" style="--pos: 50%;">
                    <div class="reveal-scroll h-[72vh] min-h-[420px] overflow-y-auto">
                        <div class="relative mx-auto" id="reveal-device-frame" style="max-width: 100%;">
                            <img id="reveal-after" src="{{ $primary }}" alt="The redesigned website" class="block w-full select-none" draggable="false">
                            @if ($before)
                                {{-- The original page, then a neutral field: where it ends, it ends (the redesign never bleeds over it). --}}
                                <div id="reveal-before" class="absolute inset-0 bg-[#E9EDF3]" style="clip-path: inset(0 calc(100% - var(--pos)) 0 0);">
                                    <img src="{{ $before }}" alt="The original website" class="block h-auto w-full select-none" draggable="false">
                                    <p class="px-4 py-8 text-center text-[12px] font-medium text-muted">The original page ends here</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    @if ($before)
                        <span class="pointer-events-none absolute left-4 top-4 rounded-full bg-black/70 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-white">Before</span>
                        <span class="pointer-events-none absolute right-4 top-4 rounded-full bg-accent px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-white">After — REDO</span>
                        <div id="reveal-handle" role="slider" tabindex="0" aria-label="Drag to compare before and after" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50"
                             class="absolute inset-y-0 z-10 w-10 -translate-x-1/2 cursor-ew-resize touch-none" style="left: var(--pos);">
                            <span class="absolute inset-y-0 left-1/2 w-[3px] -translate-x-1/2 bg-surface shadow-[0_0_0_1px_rgba(0,0,0,.15)]"></span>
                            <span class="absolute left-1/2 top-1/2 flex h-11 w-11 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-surface text-accent-ink shadow-lg ring-1 ring-line" aria-hidden="true">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6-6 6 6 6M15 6l6 6-6 6"/></svg>
                            </span>
                        </div>
                    @endif
                </div>

                {{-- Side by side --}}
                @if ($before)
                    <div class="reveal-stage grid grid-cols-1 gap-4 lg:grid-cols-2" data-stage="side">
                        <div class="overflow-hidden rounded-2xl border border-line bg-surface"><p class="bg-canvas px-4 py-2 text-[11px] font-bold uppercase tracking-widest text-muted">Before</p><div class="side-scroll h-[72vh] overflow-y-auto"><img src="{{ $before }}" alt="The original website" class="block w-full object-cover object-top"></div></div>
                        <div class="overflow-hidden rounded-2xl border-2 border-accent bg-surface"><p class="bg-accent-soft px-4 py-2 text-[11px] font-bold uppercase tracking-widest text-accent-ink">After — REDO</p><div class="side-scroll h-[72vh] overflow-y-auto"><img class="reveal-after-copy block w-full" src="{{ $primary }}" alt="The redesigned website"></div></div>
                    </div>
                @endif

                {{-- After only (device frames) --}}
                <div class="reveal-stage {{ $before ? 'hidden' : '' }} overflow-hidden rounded-2xl border border-line bg-canvas p-4" data-stage="after">
                    <div class="mx-auto h-[72vh] min-h-[420px] overflow-y-auto rounded-xl bg-surface shadow-md" id="reveal-after-only-frame" style="max-width: 100%;"><img class="reveal-after-copy block w-full" src="{{ $primary }}" alt="The redesigned website"></div>
                </div>
            </section>

            {{-- What changed --}}
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="What changed">
                <article class="rounded-2xl border border-line bg-surface p-5">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-accent-ink">Problems fixed</p>
                    <p class="mt-2 text-3xl font-extrabold text-ink">{{ count($addressed) }}<span class="text-base font-semibold text-faint"> / {{ $issuesTotal }}</span></p>
                    <ul class="mt-3 flex flex-col gap-1.5 text-[13px] leading-snug text-body">
                        @forelse (array_slice($addressed, 0, 4) as $title)<li class="flex gap-2"><span class="text-ok" aria-hidden="true">✓</span><span>{{ $title }}</span></li>@empty<li class="text-faint">No diagnosed issues recorded.</li>@endforelse
                    </ul>
                </article>

                <article class="rounded-2xl border border-line bg-surface p-5">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-accent-ink">The call to action</p>
                    @if ($oldCta && $newCta)
                        <p class="mt-3 text-[13px] text-muted">Was</p>
                        <p class="text-lg font-bold text-faint line-through decoration-[#D4D4D8]">{{ $oldCta }}</p>
                        <p class="mt-2 text-[13px] text-muted">Now</p>
                        <p class="text-lg font-extrabold text-ink">{{ $newCta }}</p>
                    @elseif ($newCta)
                        <p class="mt-3 text-lg font-extrabold text-ink">{{ $newCta }}</p>
                        <p class="mt-1 text-[13px] text-muted">A single, clear primary action.</p>
                    @endif
                </article>

                <article class="rounded-2xl border border-line bg-surface p-5">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-accent-ink">Built from your real content</p>
                    <p class="mt-2 text-3xl font-extrabold text-ink">{{ $sectionCount }}<span class="text-base font-semibold text-faint"> sections</span></p>
                    <p class="mt-1 text-[13px] text-body">{{ $assetsPreserved }} of your own {{ \Illuminate\Support\Str::plural('image', $assetsPreserved) }} kept.</p>
                    @if ($omitted)
                        <details class="mt-3 text-[12px] text-muted"><summary class="cursor-pointer font-semibold text-warn">{{ count($omitted) }} left out — nothing invented</summary>
                            <ul class="mt-2 flex flex-col gap-1.5">@foreach ($omitted as $o)<li>{{ $o['reason'] }}</li>@endforeach</ul>
                        </details>
                    @endif
                </article>

                <article class="rounded-2xl border border-line bg-surface p-5">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-accent-ink">Checked in a real browser</p>
                    <p class="mt-2 text-3xl font-extrabold text-ink">{{ $qa['passed'] }}<span class="text-base font-semibold text-faint"> / {{ $qa['total'] }} checks</span></p>
                    <p class="mt-1 text-[13px] text-body">Desktop, tablet and mobile.</p>
                    @if ($qa['fixes'])<p class="mt-2 text-[12px] text-accent-ink">{{ count($qa['fixes']) }} issue(s) fixed automatically.</p>@endif
                    @if ($qa['failed'])<p class="mt-2 text-[12px] text-warn">{{ count($qa['failed']) }} item(s) still need a look — see the preview page.</p>@endif
                </article>
            </section>
        @endif

        {{-- The analysis behind the REDO: what Meta AI understood about the business, and the issues it found --}}
        @if ($dna || $issues)
            <section class="grid gap-4 lg:grid-cols-2" aria-label="The analysis behind your REDO" id="analysis">
                @if ($dna)
                    <article class="flex flex-col rounded-2xl border border-line bg-surface p-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-[11px] font-bold uppercase tracking-widest text-accent-ink">✦ Meta AI · Analysed Website DNA</p>
                            <a href="{{ route('analyses.website-dna', $run->public_id) }}" class="text-[12px] font-semibold text-accent-ink hover:text-ink">Full Website DNA →</a>
                        </div>
                        <p class="mt-3 text-[19px] font-extrabold leading-tight tracking-tight text-ink">{{ $dna['business_name'] ?? parse_url($run->normalized_url, PHP_URL_HOST) }}</p>
                        @if (! empty($dna['industry']))<p class="text-[13px] text-muted">{{ $dna['industry'] }}</p>@endif
                        @if (! empty($dna['business_summary']))<p class="mt-3 text-[13px] leading-relaxed text-body">{{ \Illuminate\Support\Str::limit($dna['business_summary'], 320) }}</p>@endif
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if (! empty($dna['primary_conversion_goal']))<span class="rounded-full bg-surface-2 px-3 py-1.5 text-xs font-medium text-body">Goal: {{ ucfirst($dna['primary_conversion_goal']) }}</span>@endif
                            @foreach (array_slice($dna['tone_of_voice'] ?? [], 0, 4) as $tone)<span class="rounded-full bg-surface-2 px-3 py-1.5 text-xs font-medium text-body">{{ ucfirst($tone) }}</span>@endforeach
                        </div>
                        <dl class="mt-4 grid gap-3 border-t border-line pt-4 text-[13px] sm:grid-cols-2">
                            @if (! empty($dna['value_proposition']['text']))
                                <div class="sm:col-span-2"><dt class="font-semibold text-ink">Value proposition</dt><dd class="mt-0.5 leading-snug text-body">{{ \Illuminate\Support\Str::limit($dna['value_proposition']['text'], 220) }}</dd></div>
                            @endif
                            @if (! empty($dna['target_audience']))
                                <div><dt class="font-semibold text-ink">Who it serves</dt><dd class="mt-0.5 leading-snug text-body">{{ collect($dna['target_audience'])->pluck('segment')->filter()->take(3)->implode(' · ') }}</dd></div>
                            @endif
                            @if (! empty($dna['offerings']))
                                <div><dt class="font-semibold text-ink">What it offers</dt><dd class="mt-0.5 leading-snug text-body">{{ collect($dna['offerings'])->pluck('name')->filter()->take(4)->implode(' · ') }}</dd></div>
                            @endif
                            @if (! empty($dna['trust_signals']))
                                <div class="sm:col-span-2"><dt class="font-semibold text-ink">Trust signals found</dt><dd class="mt-0.5 leading-snug text-body">{{ collect($dna['trust_signals'])->pluck('description')->filter()->take(3)->map(fn ($t) => \Illuminate\Support\Str::limit($t, 70))->implode(' · ') }}</dd></div>
                            @endif
                        </dl>
                    </article>
                @endif

                @if ($issues)
                    <article class="flex flex-col rounded-2xl border border-line bg-surface p-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-[11px] font-bold uppercase tracking-widest text-accent-ink">Evidence-backed issues per analysis</p>
                            <a href="{{ route('analyses.diagnosis', $run->public_id) }}" class="text-[12px] font-semibold text-accent-ink hover:text-ink">Full diagnosis →</a>
                        </div>
                        <p class="mt-3 text-3xl font-extrabold text-ink">{{ count($issues) }}<span class="text-base font-semibold text-faint"> {{ \Illuminate\Support\Str::plural('issue', count($issues)) }} found</span></p>
                        <p class="text-[13px] text-muted">Every one is linked to evidence taken from your website{{ $addressed ? ' — '.count($addressed).' addressed in your REDO.' : '.' }}</p>
                        <ul class="mt-4 flex flex-col divide-y divide-line text-[13px]">
                            @foreach ($issues as $issue)
                                @php $sev = match ($issue['severity']) { 'high' => 'bg-bad-soft text-bad', 'medium' => 'bg-warn-soft text-warn', default => 'bg-accent-soft text-accent-ink' }; @endphp
                                <li class="flex items-start gap-3 py-2.5">
                                    <span class="mt-0.5 shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide {{ $sev }}">{{ strtoupper($issue['severity']) }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold leading-snug text-ink">{{ $issue['title'] }}</span>
                                        <span class="block text-[11.5px] text-faint">{{ $issue['category'] }}@if ($issue['evidence_ids']) · {{ implode(', ', array_slice($issue['evidence_ids'], 0, 3)) }}@endif</span>
                                    </span>
                                    @if ($issue['addressed'])<span class="shrink-0 text-[11.5px] font-semibold text-ok" title="Addressed in your REDO">✓ Fixed</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @endif
            </section>
        @endif

        {{-- Next step: generate HTML / publish (paid plans) --}}
        <section class="rounded-2xl border border-line bg-surface p-6 lg:p-8" aria-label="Get your website">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-xl">
                    <h2 class="text-xl font-extrabold tracking-tight text-ink">Love it? Take it live.</h2>
                    <p class="mt-1 text-[14px] leading-relaxed text-body">Download the complete website as clean HTML you can host anywhere, or publish it on a REDOLIO web address in one click.</p>
                </div>
                @if ($canPublish || $canExport)
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('analyses.launch', $run->public_id) }}" class="rounded-lg bg-accent px-6 py-3 text-sm font-semibold text-white hover:bg-accent-strong">{{ $canPublish ? 'Publish or download HTML →' : 'Download HTML →' }}</a>
                    </div>
                @else
                    <div class="flex flex-col gap-3 sm:flex-row">
                        @foreach ($plans as $plan)
                            <a href="{{ route('analyses.upgrade', [$run->public_id, 'intent' => 'approve']) }}" class="flex min-w-[210px] flex-col gap-0.5 rounded-xl border {{ $loop->first ? 'border-accent bg-accent-soft' : 'border-line' }} px-5 py-4 hover:border-accent">
                                <span class="text-sm font-bold text-ink">{{ $plan->name }} — ${{ number_format($plan->price_monthly_cents / 100) }}/mo</span>
                                <span class="text-[12px] text-muted">Publish + download HTML</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>

    @unless ($canPublish || $canExport)
        <div class="sticky bottom-0 z-30 border-t border-line bg-surface/95 px-4 py-3 backdrop-blur sm:px-8" role="region" aria-label="Go live">
            <div class="mx-auto flex max-w-[1400px] flex-wrap items-center justify-between gap-3">
                <p class="text-[14px] font-semibold text-ink"><span class="hidden sm:inline">Love it? </span>Make it yours, then take it live.</p>
                <div class="flex gap-2"><a href="{{ route('analyses.edit', $run->public_id) }}" class="btn btn-ghost !px-4 !py-2 text-[13px]">Edit free</a><a href="{{ route('analyses.upgrade', [$run->public_id, 'intent' => 'approve']) }}" class="btn btn-primary !px-4 !py-2 text-[13px]">Publish — from ${{ number_format(($plans->first()->price_monthly_cents ?? 2900) / 100) }}/mo</a></div>
            </div>
        </div>
    @endunless

    <script>
    (function () {
        const root = document.getElementById('reveal');
        const prep = document.getElementById('reveal-preparing');

        // ---- Prepare real screenshots (and poll until they exist).
        if (prep) {
            const csrf = @json(csrf_token());
            const err = document.getElementById('reveal-error');
            const fail = (m) => { err.textContent = m; err.classList.remove('hidden'); };
            const poll = async (id) => {
                for (let i = 0; i < 90; i++) {
                    await new Promise((r) => setTimeout(r, 2500));
                    const res = await fetch(prep.dataset.statusBase + '/' + id, { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    if (data.status === 'complete') return location.reload();
                    if (data.status === 'failed') return fail(data.error_message || 'Could not capture screenshots.');
                }
                fail('This is taking longer than expected. Please refresh in a minute.');
            };
            (async () => {
                if (prep.dataset.pending) return poll(prep.dataset.pending);
                const res = await fetch(prep.dataset.runUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
                if (!res.ok) return fail('Could not start the capture.');
                poll((await res.json()).report_id);
            })();
            return;
        }
        if (!root) return;

        const stages = root.querySelectorAll('.reveal-stage');
        const afterImgs = document.querySelectorAll('#reveal-after, .reveal-after-copy');
        const urls = @json($after);
        const widths = { desktop: '100%', tablet: '768px', mobile: '390px' };
        let mode = root.dataset.hasBefore === '1' ? 'side' : 'after';
        let device = 'desktop';

        function show() {
            stages.forEach((s) => {
                const on = s.dataset.stage === mode;
                s.classList.toggle('hidden', !on);
                if (s.dataset.stage === 'side') s.classList.toggle('grid', on);
            });
            root.querySelectorAll('.reveal-mode').forEach((b) => {
                const active = b.dataset.mode === mode;
                b.classList.toggle('bg-ink', active); b.classList.toggle('text-canvas', active); b.classList.toggle('text-body', !active);
            });
            root.querySelectorAll('.reveal-device').forEach((b) => {
                const active = b.dataset.device === device;
                b.classList.toggle('bg-accent', active); b.classList.toggle('text-white', active); b.classList.toggle('text-body', !active);
            });
            afterImgs.forEach((i) => { i.src = urls[device]; });
            // Phone/tablet widths only make sense when viewing the redesign on its own.
            const frame = document.getElementById('reveal-after-only-frame');
            if (frame) frame.style.maxWidth = widths[device];
        }
        root.querySelectorAll('.reveal-mode').forEach((b) => b.addEventListener('click', () => { mode = b.dataset.mode; show(); }));
        root.querySelectorAll('.reveal-device').forEach((b) => b.addEventListener('click', () => {
            device = b.dataset.device;
            if (device !== 'desktop' && mode !== 'after') mode = 'after';
            show();
        }));
        show();

        // ---- Draggable, keyboard-accessible slider.
        const stage = root.querySelector('[data-stage="slider"]');
        const handle = document.getElementById('reveal-handle');
        if (stage && handle) {
            const set = (pct) => {
                pct = Math.max(0, Math.min(100, pct));
                stage.style.setProperty('--pos', pct + '%');
                handle.setAttribute('aria-valuenow', Math.round(pct));
            };
            const move = (e) => { const r = stage.getBoundingClientRect(); set(((e.clientX - r.left) / r.width) * 100); };
            let dragging = false;
            handle.addEventListener('pointerdown', (e) => { dragging = true; handle.setPointerCapture(e.pointerId); e.preventDefault(); });
            handle.addEventListener('pointermove', (e) => dragging && move(e));
            handle.addEventListener('pointerup', () => { dragging = false; });
            stage.addEventListener('click', (e) => { if (e.target.closest('#reveal-handle')) return; if (e.clientX) move(e); });
            handle.addEventListener('keydown', (e) => {
                const cur = parseFloat(handle.getAttribute('aria-valuenow'));
                if (e.key === 'ArrowLeft') { set(cur - 5); e.preventDefault(); }
                if (e.key === 'ArrowRight') { set(cur + 5); e.preventDefault(); }
                if (e.key === 'Home') { set(0); e.preventDefault(); }
                if (e.key === 'End') { set(100); e.preventDefault(); }
            });
            // A gentle intro so people discover the interaction (skipped for reduced motion).
            if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
                let t = 0; const from = 50;
                const id = setInterval(() => { t += 0.06; set(from + Math.sin(t) * 22 * Math.max(0, 1 - t / 6)); if (t > 6) clearInterval(id); }, 16);
                stage.addEventListener('pointerdown', () => clearInterval(id), { once: true });
            }
        }

        // ---- Keep the two side-by-side panes scrolling together.
        const panes = root.querySelectorAll('.side-scroll');
        if (panes.length === 2) {
            let lock = false;
            panes.forEach((p, i) => p.addEventListener('scroll', () => {
                if (lock) return; lock = true; panes[1 - i].scrollTop = p.scrollTop; requestAnimationFrame(() => { lock = false; });
            }));
        }
    })();
    </script>
@endsection
