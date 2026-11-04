@extends('layouts.app')

@section('title', 'REDOLIO — Analysis status')

@php
    $stages = [
        'validating_url' => 'Validate URL',
        'crawling' => 'Crawl pages',
        'building_dna' => 'Build DNA',
        'diagnosing' => 'Diagnose',
        'preparing_redesign' => 'REDO',
        'ready' => 'Next REDO',
    ];

    $stageKeys = array_keys($stages);
    $currentIndex = array_search($run->current_stage, $stageKeys, true);

    $stageStates = [];
    foreach ($stageKeys as $index => $key) {
        $log = $run->stageLogs->where('stage', $key)->last();

        $stageStates[$key] = match (true) {
            $log !== null => $log->status,
            in_array($run->status, ['complete', 'partial'], true) => 'complete',
            $run->status === 'failed' && $currentIndex !== false && $index < $currentIndex => 'complete',
            $run->status === 'failed' && $key === $run->current_stage => 'failed',
            $key === $run->current_stage => 'running',
            default => 'pending',
        };
    }

    $currentLog = $run->stageLogs->where('stage', $run->current_stage)->last();
    $completeCount = collect($stageStates)->filter(fn ($s) => in_array($s, ['complete', 'partial']))->count();
@endphp

@section('content')
    <x-app-nav :run="$run" />

    {{-- Stage dots --}}
    <div class="flex h-16 items-center gap-0 border-b border-line bg-surface px-8">
        @foreach ($stages as $key => $label)
            @php $state = $stageStates[$key]; @endphp
            <div class="flex items-center gap-2">
                <span @class([
                    'flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full border',
                    'border-accent bg-accent' => $state === 'complete' || $state === 'partial',
                    'border-accent border-2 bg-accent-soft' => $state === 'running',
                    'border-red-600 bg-bad' => $state === 'failed',
                    'border-line bg-surface' => $state === 'pending',
                ])>
                    @if ($state === 'complete' || $state === 'partial')
                        <svg viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3"><path d="M20 6 9 17l-5-5"/></svg>
                    @elseif ($state === 'failed')
                        <svg viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    @endif
                </span>
                <span @class([
                    'whitespace-nowrap text-xs',
                    'text-accent-ink' => $state === 'complete' || $state === 'partial',
                    'font-bold text-ink' => $state === 'running',
                    'text-bad' => $state === 'failed',
                    'text-faint' => $state === 'pending',
                ])>{{ $label }}</span>
            </div>
            @if (! $loop->last)
                <span class="mx-3 h-px flex-1 {{ in_array($state, ['complete', 'partial']) ? 'bg-accent' : 'bg-line' }}"></span>
            @endif
        @endforeach
    </div>

    <div class="flex flex-col items-center gap-8 px-20 py-16">
        <div class="flex w-full max-w-[680px] flex-col gap-8 rounded-[20px] border border-line bg-surface p-12">
            @if ($run->source_mode === 'fixture')
                <div class="inline-flex w-fit items-center rounded-full bg-warn-soft px-3 py-1 text-xs font-semibold text-warn">
                    Fixture demo mode — not a live crawl
                </div>
            @endif

            <div class="flex items-center gap-4">
                <span @class([
                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-[3px]',
                    'border-accent bg-accent-soft' => $run->status !== 'failed',
                    'border-red-600 bg-bad-soft' => $run->status === 'failed',
                ])>
                    @if (in_array($run->status, ['complete', 'partial'], true))
                        <svg viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M20 6 9 17l-5-5"/></svg>
                    @elseif ($run->status === 'failed')
                        <svg viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    @endif
                </span>
                <div class="flex flex-col gap-1">
                    <p class="text-lg font-bold text-ink">
                        {{ in_array($run->status, ['complete', 'partial'], true) ? 'Analysis ready' : ($stages[$run->current_stage] ?? ucfirst(str_replace('_', ' ', $run->current_stage))) }}
                    </p>
                    <p class="text-sm text-body">
                        @if ($run->status === 'failed')
                            {{ $run->safe_error_message ?? 'This analysis could not be completed.' }}
                        @elseif (in_array($run->status, ['complete', 'partial'], true))
                            Website DNA, diagnosis, and a redesign preview are ready to review.
                        @else
                            {{ $currentLog?->safe_message ?? 'Meta AI is working through your analysis…' }}
                        @endif
                    </p>
                </div>
            </div>

            <p class="text-sm text-faint">🔗 {{ $run->submitted_url }}</p>

            @php
                $isDone = in_array($run->status, ['complete', 'partial'], true);
                $inProgress = ! $isDone && $run->status !== 'failed';
                $p0 = $progress['percent'];
            @endphp
            <div class="flex flex-col gap-2" id="scan-progress" data-url="{{ route('analyses.progress', $run->public_id) }}" data-signature="{{ $progress['signature'] }}" data-active="{{ $inProgress ? '1' : '0' }}">
                <div class="flex items-baseline justify-between">
                    <p class="text-3xl font-extrabold tabular-nums text-ink"><span id="scan-percent">{{ $p0 }}</span><span class="text-lg font-bold text-faint">%</span></p>
                    <p id="scan-eta" class="text-sm font-medium text-body" aria-live="polite">{{ $inProgress ? $progress['eta_text'] : '' }}</p>
                </div>
                <div class="relative h-2 w-full overflow-hidden rounded-full bg-line" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $p0 }}" id="scan-bar-track">
                    <div id="scan-bar" class="absolute inset-y-0 left-0 rounded-full bg-accent transition-all duration-700 ease-out" style="width: {{ $p0 }}%"></div>
                </div>
                <p class="text-xs text-faint">Time left is an estimate based on recent scans.</p>
            </div>

            @if ($inProgress)
                <div id="scan-busy" class="{{ $progress['queue']['busy'] || $progress['queue']['waiting'] ? '' : 'hidden' }} flex flex-col gap-3 rounded-xl border border-warn/30 bg-warn-soft p-4 text-[13px] text-warn">
                    <p class="font-semibold" id="scan-busy-title">
                        @if ($progress['queue']['waiting'])
                            We're busy right now — you're number {{ $progress['queue']['position'] }} in line.
                        @else
                            Lots of people are scanning right now, so this may take a little longer than usual.
                        @endif
                    </p>
                    <p>You don't need to wait here. Leave us your email and we'll send you a link to your REDO the moment it's ready.</p>
                    <form id="scan-notify" method="POST" action="{{ route('analyses.notify', $run->public_id) }}" class="{{ $run->notify_email ? 'hidden' : '' }} flex flex-wrap gap-2">
                        @csrf
                        <input type="email" name="email" required maxlength="255" value="{{ $run->notify_email ?? auth()->user()?->email }}" placeholder="you@company.com" class="min-w-0 flex-1 rounded-lg border border-amber-300 bg-surface px-3 py-2 text-sm text-ink" aria-label="Email address">
                        <button type="submit" class="rounded-lg bg-ink px-4 py-2 text-sm font-semibold text-canvas hover:opacity-85">Email me when ready</button>
                    </form>
                    <p id="scan-notify-done" class="{{ $run->notify_email ? '' : 'hidden' }} font-semibold text-ok">✓ We'll email {{ $run->notify_email ?? 'you' }} when it's ready. You can close this tab.</p>
                </div>
            @endif

            <div class="flex flex-col gap-2.5">
                @foreach ($stages as $key => $label)
                    @php $state = $stageStates[$key]; @endphp
                    <div class="flex items-center gap-2.5">
                        @if ($state === 'complete' || $state === 'partial')
                            <svg viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                        @elseif ($state === 'running')
                            <svg viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                        @elseif ($state === 'failed')
                            <svg viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="#A1A1AA" stroke-width="2" class="h-4 w-4 shrink-0"><circle cx="12" cy="12" r="10"/></svg>
                        @endif
                        <span class="text-[13px] {{ $state === 'running' ? 'font-semibold text-ink' : ($state === 'pending' ? 'text-faint' : 'text-ink') }}">
                            {{ $run->stageLogs->where('stage', $key)->last()?->safe_message ?? $label }}
                        </span>
                    </div>
                @endforeach
            </div>

            @if ($run->status === 'failed')
                <form method="POST" action="{{ route('analyses.retry', $run->public_id) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-ink px-5 py-2.5 text-sm font-semibold text-canvas hover:opacity-85">
                        Retry
                    </button>
                </form>
            @endif

            @if ($run->siteSnapshot && ! empty($run->siteSnapshot->warnings_json))
                <div class="rounded-lg bg-warn-soft p-4 text-sm text-warn">
                    <p class="font-semibold">Crawl warnings</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($run->siteSnapshot->warnings_json as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @if (! $dna && $run->status !== 'failed')
            <div class="flex w-full max-w-[680px] items-center gap-3 rounded-xl border border-warn/30 bg-warn-soft px-6 py-3.5">
                <svg viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                <p class="text-[13px] text-warn">If the live crawl fails, we'll offer a prepared demo example with the same full journey.</p>
            </div>
        @endif

        @if ($dna)
            <div class="flex flex-wrap justify-center gap-3">
                @if ($designSelection && $designSpec)
                    <a href="{{ route('analyses.reveal', $run->public_id) }}" class="rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-white hover:bg-accent-strong">
                        See your REDO →
                    </a>
                    <a href="{{ route('analyses.design-directions', $run->public_id) }}" class="rounded-lg border border-line bg-surface px-5 py-2.5 text-sm font-medium text-ink hover:border-line-strong">
                        Choose a different design
                    </a>
                @elseif ($diagnosis)
                    <a href="{{ auth()->check() ? route('analyses.express', $run->public_id) : route('analyses.design-directions', $run->public_id) }}" class="rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-white hover:bg-accent-strong">
                        Build my REDO →
                    </a>
                    <a href="{{ route('analyses.design-directions', $run->public_id) }}" class="rounded-lg border border-line bg-surface px-5 py-2.5 text-sm font-medium text-ink hover:border-line-strong">Choose a design myself</a>
                @endif
                <a href="{{ route('analyses.website-dna', $run->public_id) }}" class="rounded-lg border border-line bg-surface px-5 py-2.5 text-sm font-medium text-ink hover:border-line-strong">
                    Website DNA
                </a>
                @if ($diagnosis)
                    <a href="{{ route('analyses.diagnosis', $run->public_id) }}" class="rounded-lg border border-line bg-surface px-5 py-2.5 text-sm font-medium text-ink hover:border-line-strong">
                        Diagnosis
                    </a>
                @endif
            </div>
        @endif
    </div>

    @if (! in_array($run->status, ['complete', 'partial', 'failed'], true))
        <script>
            (() => {
                const root = document.getElementById('scan-progress');
                const $ = (id) => document.getElementById(id);
                let shown = Number($('scan-percent').textContent) || 0;
                async function tick() {
                    try {
                        const r = await fetch(root.dataset.url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                        if (!r.ok) throw new Error(r.status);
                        const d = await r.json();
                        if (d.signature !== root.dataset.signature) { window.location.reload(); return; } // a stage changed: redraw the stage list
                        shown = Math.max(shown, d.percent); // the bar never goes backwards
                        $('scan-percent').textContent = shown;
                        $('scan-bar').style.width = shown + '%';
                        $('scan-bar-track').setAttribute('aria-valuenow', shown);
                        $('scan-eta').textContent = d.eta_text || '';
                        const busy = $('scan-busy');
                        if (busy) {
                            busy.classList.toggle('hidden', !(d.queue.busy || d.queue.waiting));
                            $('scan-busy-title').textContent = d.queue.waiting
                                ? `We're busy right now — you're number ${d.queue.position} in line.`
                                : 'Lots of people are scanning right now, so this may take a little longer than usual.';
                        }
                    } catch (e) { /* transient: try again on the next tick */ }
                    setTimeout(tick, 2000);
                }
                setTimeout(tick, 2000);
                const form = $('scan-notify');
                if (form) form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const res = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value }, body: new FormData(form) });
                    if (res.ok) { form.classList.add('hidden'); $('scan-notify-done').textContent = `✓ We'll email ${form.email.value} when it's ready. You can close this tab.`; $('scan-notify-done').classList.remove('hidden'); }
                    else { form.email.setCustomValidity('Please enter a valid email address.'); form.reportValidity(); form.email.setCustomValidity(''); }
                });
            })();
        </script>
    @elseif ($diagnosis && ! $designSelection)
        <script>
            setTimeout(() => {
                window.location.href = "{{ auth()->check() ? route('analyses.express', $run->public_id) : route('analyses.design-directions', $run->public_id) }}";
            }, 1200);
        </script>
    @endif
@endsection
