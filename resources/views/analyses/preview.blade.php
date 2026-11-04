@extends('layouts.app')

@section('title', 'REDOLIO — Preview')

@php
    use Illuminate\Support\Str;
@endphp

@section('content')
    <x-app-nav :run="$run" />
    <x-stage-bar active="redo" />

    @guest
        @if (config('redo.edition') === 'selfhosted')
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-accent-soft px-8 py-3">
                <p class="text-[13px] text-accent-ink">
                    This REDO isn't saved yet — create your admin account to save it and unlock the full dashboard.
                </p>
                <a href="{{ route('setup.index', ['run' => $run->public_id]) }}" class="rounded-lg bg-accent px-3.5 py-1.5 text-[13px] font-semibold text-white hover:bg-accent-strong">
                    Create admin account
                </a>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-accent-soft px-8 py-3">
                <p class="text-[13px] text-accent-ink">
                    This REDO isn't saved yet — it'll be lost if you close this page.
                </p>
                <div class="flex items-center gap-2">
                    <a href="{{ route('login', ['claim' => $run->public_id]) }}" class="rounded-lg px-3.5 py-1.5 text-[13px] font-medium text-accent-ink hover:bg-surface">
                        Log in
                    </a>
                    <a href="{{ route('register', ['claim' => $run->public_id]) }}" class="rounded-lg bg-accent px-3.5 py-1.5 text-[13px] font-semibold text-white hover:bg-accent-strong">
                        Sign up to save this REDO
                    </a>
                </div>
            </div>
        @endif
    @endguest

    {{-- Toolbar --}}
    <div class="flex h-14 items-center justify-between border-b border-line bg-surface px-8">
        <div class="flex items-center gap-5">
            <span class="text-[15px] font-bold text-ink">REDO Preview</span>

            {{-- Direction mega-menu: switch the REDO to another design direction in place --}}
            <div class="relative" id="direction-switcher">
                <button
                    type="button"
                    id="direction-switcher-button"
                    class="flex items-center gap-1.5 rounded-lg border border-line px-3 py-1.5 text-[13px] font-medium text-ink hover:border-line-strong"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    <span id="direction-switcher-label">{{ $directions->get($spec->template_key)?->name ?? Str::headline($spec->template_key) }}</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div id="direction-switcher-menu" class="absolute left-0 top-full z-30 mt-2 hidden w-72 rounded-xl border border-line bg-surface p-2 shadow-xl">
                    @foreach ($directions as $d)
                        <button
                            type="button"
                            class="direction-option flex w-full flex-col items-start gap-0.5 rounded-lg px-3 py-2 text-left hover:bg-canvas {{ $d->direction_key === $spec->template_key ? 'bg-accent-soft' : '' }}"
                            data-direction-key="{{ $d->direction_key }}"
                        >
                            <span class="flex w-full items-center justify-between text-[13px] font-semibold text-ink">
                                {{ $d->name }}
                                @if ($d->direction_key === $spec->template_key)
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-accent-ink">Current</span>
                                @endif
                            </span>
                            <span class="text-[11px] text-faint">{{ $d->recipe_json['description'] ?? '' }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($run->source_mode === 'fixture')
                <span class="rounded-full bg-warn-soft px-2.5 py-1 text-[11px] font-semibold text-warn">Fixture demo</span>
            @endif

            <div id="preview-toolbar-meta" class="flex items-center gap-5">
                @include('analyses.partials.preview-toolbar-meta', ['run' => $run, 'spec' => $spec, 'otherVersions' => $otherVersions])
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            @if (! empty($spec->design_spec_json['v15']))
                <a href="{{ route('analyses.edit', $run->public_id) }}" class="flex items-center gap-1.5 rounded-lg border border-line px-4 py-2 text-[13px] font-medium text-ink hover:border-line-strong" title="Change wording, pictures, layout and add-ons right on the page. Free.">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                    Edit the design
                </a>
            @else
                <form method="POST" action="{{ route('analyses.make-editable', $run->public_id) }}">@csrf
                    <button type="submit" class="flex items-center gap-1.5 rounded-lg border border-line px-4 py-2 text-[13px] font-medium text-ink hover:border-line-strong" title="Rebuild this design so you can edit it on the page. Free.">✎ Edit the design</button>
                </form>
            @endif
            <a href="{{ route('analyses.reveal', $run->public_id) }}" class="rounded-lg border border-accent bg-accent-soft px-4 py-2 text-[13px] font-semibold text-accent-ink hover:bg-accent-soft">
                Before &amp; after ✦
            </a>
            @auth
                <a href="{{ route('analyses.launch', $run->public_id) }}" class="rounded-lg bg-accent px-4 py-2 text-[13px] font-semibold text-white hover:bg-accent-strong">
                    Launch →
                </a>
            @endauth
            <a href="{{ route('analyses.next-redo', $run->public_id) }}" class="rounded-lg border border-line px-4 py-2 text-[13px] font-medium text-ink hover:border-line-strong">
                View Next REDO
            </a>
        </div>
    </div>

    <div class="flex flex-col xl:flex-row">
        {{-- Split view: "Recreate any website instantly" browser-chrome comparison --}}
        <div class="grid flex-1 grid-cols-1 gap-6 bg-canvas p-6 lg:grid-cols-2">
            <div class="flex flex-col gap-2">
                <p class="text-center text-xs font-bold uppercase tracking-[0.2em] text-faint">Before</p>
                <div class="flex h-[65vh] flex-col overflow-hidden rounded-xl border border-line bg-surface shadow-sm xl:h-[calc(100vh-200px)]">
                    <div class="flex shrink-0 items-center gap-3 border-b border-line bg-canvas px-4 py-2.5">
                        <span class="flex gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-[#FF5F57]"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-[#FEBC2E]"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-[#28C840]"></span>
                        </span>
                        <span class="mx-auto flex max-w-[70%] items-center truncate rounded-full bg-surface px-3 py-1 text-[11px] text-muted">
                            {{ $run->normalized_url }}
                        </span>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        @if ($siteSnapshot?->realScreenshotUrl())
                            <img
                                src="{{ $siteSnapshot->realScreenshotUrl() }}"
                                alt="Screenshot of {{ $run->normalized_url }}"
                                class="w-full object-cover object-top"
                                loading="lazy"
                            >
                        @else
                            <div class="flex h-full flex-col items-center justify-center gap-2 p-8 text-center">
                                <p class="text-sm font-semibold text-body">A screenshot of your current website isn't available.</p>
                                <p class="max-w-xs text-xs text-faint">It could not be captured when the site was analysed. The redesign on the right is still built from the content REDOLIO read.</p>
                                <a href="{{ $run->normalized_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 text-xs font-semibold text-accent-ink hover:underline">Open your current website ↗</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <p class="text-center text-xs font-bold uppercase tracking-[0.2em] text-accent-ink">After — REDO</p>
                <div class="relative flex h-[65vh] flex-col overflow-hidden rounded-xl border-2 border-accent bg-surface shadow-md xl:h-[calc(100vh-200px)]">
                    <div class="flex shrink-0 items-center gap-3 border-b border-line bg-accent-soft px-4 py-2.5">
                        <span class="flex gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-[#FF5F57]"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-[#FEBC2E]"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-[#28C840]"></span>
                        </span>
                        <span class="mx-auto flex max-w-[70%] items-center truncate rounded-full bg-surface px-3 py-1 text-[11px] font-medium text-accent-ink">
                            {{ $run->normalized_url }} · redesigned
                        </span>
                    </div>
                    <div id="redo-preview-pane" class="flex-1 overflow-y-auto" data-frame-viewport>
                        @include('analyses.partials.preview-redo-pane', ['renderedHtml' => $renderedHtml])
                    </div>
                    <div id="direction-switcher-loading" class="absolute inset-0 z-20 hidden flex-col items-center justify-center gap-3 bg-surface/90">
                        <svg class="h-8 w-8 animate-spin text-accent-ink" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <p class="text-sm font-medium text-ink">Regenerating your REDO…</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Rationale + coverage panel --}}
        <aside id="preview-aside" class="flex w-full flex-col border-t border-line bg-surface xl:h-[calc(100vh-152px)] xl:w-[320px] xl:shrink-0 xl:overflow-y-auto xl:border-l xl:border-t-0">
            @include('analyses.partials.preview-aside', ['spec' => $spec, 'evaluation' => $evaluation, 'diagnosis' => $diagnosis])
        </aside>
    </div>

    {{-- Visual QA: PRD v1.4 screenshot-based evaluation of the real generated artifact --}}
    <div class="border-t border-line bg-surface px-8 py-8">
        <div class="mx-auto max-w-5xl">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-ink">Visual QA</h2>
                    <p class="text-xs text-muted">Captures real desktop, tablet + mobile screenshots of this REDO and checks them for layout, overflow and loading issues.</p>
                </div>
                <button type="button" id="run-visual-qa" class="shrink-0 rounded-lg border border-line px-4 py-2 text-[13px] font-medium text-ink hover:border-line-strong">
                    Run visual QA →
                </button>
            </div>

            <div id="visual-qa-results" class="mt-5">
                @include('analyses.partials.visual-qa-report', ['report' => $visualQaReport, 'run' => $run])
            </div>
        </div>
    </div>

    <script>
    // Desktop-width previews: the redesign is a real 1440px page; scale it to fit its panel so both
    // BEFORE and AFTER show the desktop view, and let the page scroll inside the frame.
    (function () {
        window.redoFitFrames = function () {
            document.querySelectorAll('.bp-frame-host').forEach(function (host) {
                var viewport = host.closest('[data-frame-viewport]') || host.parentElement;
                var frame = host.querySelector('iframe');
                if (!frame || !viewport) return;
                var designWidth = parseInt(host.dataset.desktopWidth || '1440', 10);
                var scale = host.clientWidth / designWidth;
                var height = Math.max(viewport.clientHeight, 320);
                host.style.height = height + 'px';
                frame.style.transform = 'scale(' + scale + ')';
                frame.style.height = (height / scale) + 'px';
            });
        };
        window.addEventListener('resize', window.redoFitFrames);
        document.addEventListener('DOMContentLoaded', window.redoFitFrames);
        window.redoFitFrames();
        var pane = document.getElementById('redo-preview-pane');
        if (pane && window.ResizeObserver) new ResizeObserver(window.redoFitFrames).observe(pane);
    })();
    </script>
@endsection

@push('scripts')
    <script>
        (function () {
            const switcherButton = document.getElementById('direction-switcher-button');
            const switcherMenu = document.getElementById('direction-switcher-menu');
            const switcherLabel = document.getElementById('direction-switcher-label');
            const loadingOverlay = document.getElementById('direction-switcher-loading');
            const switchUrl = @json(route('analyses.preview.switch-direction', $run->public_id));
            const csrfToken = @json(csrf_token());

            if (!switcherButton || !switcherMenu) return;

            switcherButton.addEventListener('click', () => {
                const isOpen = !switcherMenu.classList.contains('hidden');
                switcherMenu.classList.toggle('hidden', isOpen);
                switcherButton.setAttribute('aria-expanded', String(!isOpen));
            });

            document.addEventListener('click', (e) => {
                if (!document.getElementById('direction-switcher').contains(e.target)) {
                    switcherMenu.classList.add('hidden');
                    switcherButton.setAttribute('aria-expanded', 'false');
                }
            });

            switcherMenu.querySelectorAll('.direction-option').forEach((option) => {
                option.addEventListener('click', async () => {
                    const directionKey = option.dataset.directionKey;
                    const directionName = option.querySelector('span').textContent.trim();

                    switcherMenu.classList.add('hidden');
                    switcherButton.setAttribute('aria-expanded', 'false');
                    loadingOverlay.classList.remove('hidden');
                    loadingOverlay.classList.add('flex');

                    try {
                        const response = await fetch(switchUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ direction_key: directionKey }),
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            alert(data.message || 'Could not regenerate this REDO. Please try again.');
                            return;
                        }

                        document.getElementById('redo-preview-pane').innerHTML = data.redoPaneHtml;
                        window.redoFitFrames && window.redoFitFrames();
                        document.getElementById('preview-aside').innerHTML = data.asideHtml;
                        document.getElementById('preview-toolbar-meta').innerHTML = data.toolbarHtml;
                        switcherLabel.textContent = data.directionName;

                        const refineInput = document.getElementById('refine-from-version');
                        if (refineInput) refineInput.value = data.version;

                        switcherMenu.querySelectorAll('.direction-option').forEach((opt) => {
                            opt.classList.toggle('bg-accent-soft', opt === option);
                        });

                        window.history.pushState({}, '', data.url);
                    } catch (err) {
                        alert('Could not regenerate this REDO. Please try again.');
                    } finally {
                        loadingOverlay.classList.add('hidden');
                        loadingOverlay.classList.remove('flex');
                    }
                });
            });
        })();

        (function () {
            const runButton = document.getElementById('run-visual-qa');
            const resultsEl = document.getElementById('visual-qa-results');
            if (!runButton) return;

            const runUrl = @json(route('analyses.preview.visual-qa.run', [$run->public_id, $spec->version]));
            const csrfToken = @json(csrf_token());
            const statusUrlBase = @json(route('analyses.preview.visual-qa.status', [$run->public_id, '__ID__']));

            function renderLoading() {
                resultsEl.innerHTML = '<div class="flex items-center gap-3 rounded-lg border border-line bg-canvas px-4 py-6"><svg class="h-5 w-5 animate-spin text-accent-ink" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg><p class="text-sm text-body">Capturing screenshots and running checks…</p></div>';
            }

            async function poll(reportId) {
                const statusUrl = statusUrlBase.replace('__ID__', reportId);
                const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                const data = await response.json();

                if (data.status === 'pending' || data.status === 'running') {
                    setTimeout(() => poll(reportId), 2500);
                    return;
                }

                window.location.reload();
            }

            runButton.addEventListener('click', async () => {
                runButton.disabled = true;
                renderLoading();

                try {
                    const response = await fetch(runUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        alert(data.message || 'Could not start visual QA.');
                        runButton.disabled = false;
                        return;
                    }

                    poll(data.report_id);
                } catch (err) {
                    alert('Could not start visual QA.');
                    runButton.disabled = false;
                }
            });
        })();
    </script>
@endpush
