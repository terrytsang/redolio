{{-- Self-evolving watches + their Watch reports (Starter and Agency) --}}
@php $paid = (bool) ($plan && $plan->auto_rescan_frequency_days); @endphp
<section class="mt-12" aria-labelledby="watches-h">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h2 id="watches-h" class="text-xl font-extrabold tracking-tight text-ink">Self-evolving watches</h2>
            <p class="mt-1 max-w-2xl text-[13.5px] text-muted">Automatically rescans a website on a schedule and updates its diagnosis. After each rescan you get a Watch report here: what changed on the page, which issues are new or fixed, and what to do next.</p>
        </div>
        @if ($paid)<span class="text-[13px] text-faint">{{ $siteWatches->count() }} of {{ $plan->site_limit ?? '∞' }} websites</span>@endif
    </div>

    @unless ($paid)
        <div class="card mt-5 flex flex-wrap items-center justify-between gap-4 border-dashed p-6">
            <p class="max-w-xl text-[14px] text-body"><strong class="text-ink">Watch reports are on the Starter and Agency plans.</strong> Pick a site, choose once a week or once a month, and REDOLIO rescans it and tells you what changed and what to fix.</p>
            <a href="{{ route('plan.show') }}" class="btn btn-primary">See plans →</a>
        </div>
    @else
        <form method="POST" action="{{ route('site-watches.store') }}" class="card mt-5 flex flex-wrap items-center gap-3 p-4">
            @csrf
            <input type="url" name="url" required placeholder="https://example.com" class="field !w-auto min-w-[14rem] flex-1" aria-label="Website to watch">
            <select name="frequency" class="field !w-auto" aria-label="How often to rescan">
                <option value="weekly">Once a week</option>
                <option value="monthly" selected>Once a month</option>
            </select>
            <button type="submit" class="btn btn-dark">Watch this website</button>
        </form>

        @forelse ($siteWatches as $watch)
            @php
                $r = $watch->latestReport; $sum = $r?->summary_json ?? [];
                $chip = match ($r?->trend) { 'improved' => ['pill-ok', 'Improving'], 'attention' => ['pill-warn', 'Needs attention'], 'same' => ['pill-accent', 'No change'], 'baseline' => ['pill-accent', 'Baseline'], default => $r && $r->status === 'failed' ? ['pill-bad', 'Scan failed'] : ['pill-accent', 'Waiting for first scan'] };
            @endphp
            <article class="card mt-4 overflow-hidden" aria-label="Watch report for {{ $watch->normalized_url }}">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <div class="min-w-0">
                        <p class="truncate text-[15px] font-bold text-ink">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($watch->normalized_url, '/')) }}</p>
                        <p class="mt-0.5 text-[12.5px] text-muted">
                            {{ $watch->frequencyLabel() }}
                            @if ($watch->last_scanned_at) · scanned {{ $watch->last_scanned_at->diffForHumans() }}@endif
                            @if ($watch->is_enabled && $watch->next_scan_at) · next scan {{ $watch->next_scan_at->isPast() ? 'any minute' : $watch->next_scan_at->diffForHumans() }}@endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($watch->last_status === 'running')<span class="pill pill-accent">⟳ Scanning…</span>@else<span class="pill {{ $chip[0] }}">{{ $chip[1] }}</span>@endif
                        <span class="pill {{ $watch->is_enabled ? 'pill-ok' : '' }}">{{ $watch->is_enabled ? 'Active' : 'Paused' }}</span>
                    </div>
                </div>

                @if ($r && $r->status === 'ok')
                    <div class="border-t border-line bg-surface-2/50 px-5 py-5">
                        <p class="text-[15px] font-bold text-ink">{{ $r->headline }}</p>
                        <p class="mt-0.5 text-[12px] text-faint">Report from {{ $r->scanned_at->format('j M Y, g:ia') }}@if ($r->analysisRun) · <a href="{{ route('analyses.show', $r->analysisRun->public_id) }}" class="font-semibold text-accent-ink hover:text-ink">open the full scan →</a>@endif</p>

                        <div class="mt-4 grid gap-5 md:grid-cols-2">
                            @if (! ($sum['baseline'] ?? false))
                                <div>
                                    <h3 class="text-[12px] font-bold uppercase tracking-wider text-muted">What changed on your page</h3>
                                    @forelse (($sum['changes'] ?? []) as $key => $c)
                                        <div class="mt-2 text-[13px] text-body">
                                            <p class="font-semibold text-ink">{{ $c['label'] }}</p>
                                            @if ($key === 'value_proposition')
                                                <p class="mt-0.5 text-muted line-through">{{ $c['old'] }}</p><p class="mt-0.5">{{ $c['new'] }}</p>
                                            @elseif ($key === 'pages')
                                                <p class="mt-0.5">{{ $c['old'] }} → {{ $c['new'] }}</p>
                                            @else
                                                @foreach ($c['added'] ?? [] as $t)<p class="mt-0.5 text-ok">＋ {{ \Illuminate\Support\Str::limit($t, 110) }}</p>@endforeach
                                                @foreach ($c['removed'] ?? [] as $t)<p class="mt-0.5 text-bad">－ {{ \Illuminate\Support\Str::limit($t, 110) }}</p>@endforeach
                                            @endif
                                        </div>
                                    @empty
                                        <p class="mt-2 text-[13px] text-muted">Nothing meaningful changed on the page since the last scan.</p>
                                    @endforelse
                                </div>
                                <div>
                                    <h3 class="text-[12px] font-bold uppercase tracking-wider text-muted">Issues</h3>
                                    <p class="mt-2 text-[13px] text-body">{{ $sum['issue_count'] ?? 0 }} open · {{ count($sum['issues']['resolved'] ?? []) }} fixed · {{ count($sum['issues']['new'] ?? []) }} new</p>
                                    @foreach ($sum['issues']['new'] ?? [] as $i)<p class="mt-1.5 text-[13px] text-bad">● {{ $i['title'] }} <span class="text-faint">({{ $i['severity'] }})</span></p>@endforeach
                                    @foreach ($sum['issues']['resolved'] ?? [] as $i)<p class="mt-1.5 text-[13px] text-ok">✓ Fixed: {{ $i['title'] }}</p>@endforeach
                                </div>
                            @endif

                            <div class="{{ ($sum['baseline'] ?? false) ? '' : 'md:col-span-2' }}">
                                <h3 class="text-[12px] font-bold uppercase tracking-wider text-muted">Do this next</h3>
                                <ol class="mt-2 space-y-2.5 text-[13px] text-body">
                                    @foreach ($sum['actions'] ?? [] as $a)
                                        <li><p class="font-semibold text-ink">{{ $a['title'] }} @if ($a['is_new'] ?? false)<span class="pill pill-warn !py-0">new</span>@endif</p>@if (! empty($a['solution']))<p class="mt-0.5 text-muted">{{ $a['solution'] }}</p>@endif</li>
                                    @endforeach
                                </ol>
                            </div>

                            <div class="md:col-span-2 rounded-xl bg-surface px-4 py-3 text-[13px] text-body ring-1 ring-line">
                                @if ($sum['visits']['published'] ?? false)
                                    <strong class="text-ink">Visits to your REDO site (last {{ $sum['visits']['days'] }} days):</strong> {{ number_format($sum['visits']['views']) }} views from {{ number_format($sum['visits']['visitors']) }} visitors
                                    @if (($sum['visits']['previous_views'] ?? 0) > 0)<span class="text-muted">· {{ $sum['visits']['views'] >= $sum['visits']['previous_views'] ? '▲' : '▼' }} vs {{ number_format($sum['visits']['previous_views']) }} the period before</span>@endif
                                @else
                                    <span class="text-muted">Publish your REDO website to see how many people visit it here. (REDOLIO can’t see visits to your original site — connect its own analytics for that.)</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @elseif ($r && $r->status === 'failed')
                    <div class="border-t border-line bg-bad-soft/60 px-5 py-4 text-[13px] text-bad">{{ $sum['error'] ?? 'The last rescan didn’t finish.' }}</div>
                @else
                    <div class="border-t border-line bg-surface-2/50 px-5 py-4 text-[13px] text-muted">{{ $watch->last_status === 'running' ? 'Scanning now — your first report will appear here in a few minutes.' : 'Your first report appears after the first scan (within the hour), or press “Scan now”.' }}</div>
                @endif

                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line px-5 py-3 text-[12.5px] font-semibold">
                    <form method="POST" action="{{ route('site-watches.update', $watch) }}" class="flex items-center gap-2">@csrf @method('PATCH')
                        <label for="f-{{ $watch->id }}" class="font-medium text-muted">Rescan</label>
                        <select id="f-{{ $watch->id }}" name="frequency" onchange="this.form.submit()" class="field !w-auto !py-1 !text-[12.5px]"><option value="weekly" @selected($watch->frequency_days <= 7)>Once a week</option><option value="monthly" @selected($watch->frequency_days > 7)>Once a month</option></select>
                    </form>
                    <form method="POST" action="{{ route('site-watches.scan', $watch) }}">@csrf<button class="text-accent-ink hover:text-ink">⟳ Scan now</button></form>
                    <form method="POST" action="{{ route('site-watches.toggle', $watch) }}">@csrf<button class="text-body hover:text-ink">{{ $watch->is_enabled ? 'Pause' : 'Resume' }}</button></form>
                    <form method="POST" action="{{ route('site-watches.destroy', $watch) }}" class="ml-auto">@csrf @method('DELETE')<button class="text-faint hover:text-bad">Remove</button></form>
                </div>

                @if ($watch->reports->count() > 1)
                    <details class="border-t border-line px-5 py-3 text-[12.5px]"><summary class="cursor-pointer font-semibold text-muted">Earlier reports</summary>
                        <ul class="mt-2 space-y-1.5 text-body">@foreach ($watch->reports->skip(1) as $old)<li><span class="text-faint">{{ $old->scanned_at->format('j M Y') }}</span> — {{ $old->headline }}</li>@endforeach</ul>
                    </details>
                @endif
            </article>
        @empty
            <div class="card mt-4 border-dashed px-6 py-10 text-center text-[14px] text-muted">No websites watched yet. Add one above — REDOLIO scans it, then reports what changed every week or month.</div>
        @endforelse
    @endunless
</section>
