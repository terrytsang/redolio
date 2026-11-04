@extends('layouts.app')

@section('title', 'REDOLIO — Website DNA')

@section('content')
    <x-app-nav :run="$run" />
    <x-stage-bar active="website_dna" />

    @if (! $dna)
        <div class="px-20 py-16 text-sm text-body">Website DNA has not been generated yet.</div>
    @else
        @php $data = $dna->dna_json; @endphp

        <div class="flex min-h-[calc(100vh-108px)]">
            {{-- Sidebar --}}
            <aside class="flex w-[260px] shrink-0 flex-col gap-1 border-r border-line bg-surface py-6">
                <p class="px-6 pb-3 text-[11px] font-bold tracking-[0.15em] text-faint">WEBSITE DNA</p>
                @foreach ([
                    'business-summary' => 'Business Summary',
                    'target-audience' => 'Target Audience',
                    'offerings' => 'Offerings',
                    'value-proposition' => 'Value Proposition',
                    'tone-of-voice' => 'Tone of Voice',
                    'conversion-goal' => 'Conversion Goal',
                    'trust-signals' => 'Trust Signals',
                    'observed-vs-inferred' => 'Observed vs Inferred',
                ] as $anchor => $label)
                    <a href="#{{ $anchor }}"
                       class="mx-0 px-6 py-2.5 text-[13px] {{ $loop->first ? 'bg-accent-soft font-semibold text-accent-ink' : 'text-body hover:bg-canvas' }}">
                        {{ $label }}
                    </a>
                @endforeach
                <span class="my-3 h-px w-full bg-line"></span>
                <button type="button" onclick="document.getElementById('edit-dna').open = true; document.getElementById('edit-dna').scrollIntoView({behavior:'smooth'})"
                        class="mx-6 flex items-center justify-center gap-2 rounded-lg border border-line px-3 py-2.5 text-[13px] font-medium text-body hover:border-line-strong">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                    Edit Website DNA
                </button>
            </aside>

            {{-- Main --}}
            <main class="flex-1 overflow-y-auto px-12 py-8">
                <div class="flex flex-col gap-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-[22px] font-extrabold tracking-tight text-ink">Website DNA</h1>
                            <p class="text-[13px] text-faint">
                                v{{ $dna->version }}{{ $dna->edited_by_user ? ' · edited by you' : '' }}
                                — extracted from {{ $run->siteSnapshot?->page_count ?? '—' }} pages
                            </p>
                        </div>
                        @if ($diagnosis)
                            <a href="{{ route('analyses.diagnosis', $run->public_id) }}"
                               class="flex items-center gap-1.5 rounded-lg bg-accent px-5 py-2.5 text-[13px] font-semibold text-white hover:bg-accent-strong">
                                Continue to Diagnosis →
                            </a>
                        @endif
                    </div>

                    <div id="business-summary" class="flex flex-col gap-5 rounded-xl border border-line bg-surface p-7">
                        <h2 class="text-sm font-bold text-ink">Business Summary</h2>
                        <div>
                            <p class="font-medium text-ink">{{ $data['business_name'] ?? 'Unknown' }}</p>
                            <p class="text-sm text-body">{{ $data['industry'] ?? 'Industry unknown' }}</p>
                        </div>
                        <p class="text-sm leading-relaxed text-body">{{ $data['business_summary'] ?? '' }}</p>
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full bg-surface-2 px-3 py-1.5 text-xs font-medium text-body">Industry: {{ $data['industry'] ?? 'Unknown' }}</span>
                            <span class="rounded-full bg-surface-2 px-3 py-1.5 text-xs font-medium text-body">Goal: {{ ucfirst($data['primary_conversion_goal'] ?? 'unknown') }}</span>
                            <span class="rounded-full bg-surface-2 px-3 py-1.5 text-xs font-medium text-body">Tone: {{ implode(', ', $data['tone_of_voice'] ?? []) ?: 'Unspecified' }}</span>
                        </div>
                    </div>

                    <div id="target-audience" class="flex flex-col gap-4 rounded-xl border border-line bg-surface p-7">
                        <h2 class="text-sm font-bold text-ink">Target Audience</h2>
                        <div class="grid gap-4 sm:grid-cols-3">
                            @forelse ($data['target_audience'] ?? [] as $segment)
                                @php
                                    $confidence = $segment['confidence'] ?? null;
                                    $confClasses = match ($confidence) {
                                        'high' => 'bg-ok-soft text-ok',
                                        'medium' => 'bg-warn-soft text-warn',
                                        'low' => 'bg-bad-soft text-bad',
                                        default => null,
                                    };
                                @endphp
                                <div class="flex flex-col gap-2.5 rounded-lg bg-canvas p-4">
                                    <p class="text-[13px] font-semibold text-ink">{{ $segment['segment'] }}</p>
                                    <p class="text-xs leading-relaxed text-body">{{ implode(', ', $segment['needs'] ?? []) }}</p>
                                    @if ($confClasses)
                                        <span class="w-fit rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $confClasses }}">{{ $confidence }} confidence</span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-faint">No audience segment identified from evidence yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <div id="offerings" class="flex flex-col gap-3 rounded-xl border border-line bg-surface p-7">
                        <h2 class="text-sm font-bold text-ink">Offerings</h2>
                        @forelse ($data['offerings'] ?? [] as $offering)
                            <div class="border-b border-line pb-3 last:border-b-0 last:pb-0">
                                <p class="text-[13px] font-semibold text-ink">{{ $offering['name'] }}</p>
                                <p class="text-[13px] text-body">{{ $offering['description'] }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-faint">No offerings identified from evidence yet.</p>
                        @endforelse
                    </div>

                    <div id="value-proposition" class="flex flex-col gap-3 rounded-xl border border-line bg-surface p-7">
                        <h2 class="text-sm font-bold text-ink">Value Proposition</h2>
                        <p class="text-sm leading-relaxed text-body">{{ $data['value_proposition']['text'] ?? 'Not yet established from evidence.' }}</p>
                        <p class="text-xs text-faint">Confidence: {{ $data['value_proposition']['confidence'] ?? 'low' }}</p>
                    </div>

                    <div id="tone-of-voice" class="flex flex-col gap-3 rounded-xl border border-line bg-surface p-7">
                        <h2 class="text-sm font-bold text-ink">Tone of Voice</h2>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($data['tone_of_voice'] ?? [] as $tone)
                                <span class="rounded-full bg-surface-2 px-3 py-1.5 text-xs font-medium text-body">{{ $tone }}</span>
                            @empty
                                <span class="text-sm text-faint">Unspecified</span>
                            @endforelse
                        </div>
                    </div>

                    <div id="conversion-goal" class="flex flex-col gap-2 rounded-xl border border-line bg-surface p-7">
                        <h2 class="text-sm font-bold text-ink">Conversion Goal</h2>
                        <p class="text-sm font-medium text-ink">{{ ucfirst($data['primary_conversion_goal'] ?? 'unknown') }}</p>
                    </div>

                    <div id="trust-signals" class="flex flex-col gap-3 rounded-xl border border-line bg-surface p-7">
                        <h2 class="text-sm font-bold text-ink">Trust Signals</h2>
                        <ul class="flex flex-col gap-1.5">
                            @forelse ($data['trust_signals'] ?? [] as $signal)
                                <li class="text-sm text-body">{{ $signal['description'] }} <span class="text-xs text-faint">({{ $signal['type'] }})</span></li>
                            @empty
                                <li class="text-sm text-faint">No trust signals found.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div id="observed-vs-inferred" class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-3 rounded-xl bg-canvas p-6">
                            <h3 class="text-sm font-bold text-ink">Observed facts</h3>
                            <ul class="flex flex-col gap-1.5">
                                @forelse ($data['observed_facts'] ?? [] as $fact)
                                    <li class="text-sm text-body">{{ $fact['claim'] }}</li>
                                @empty
                                    <li class="text-sm text-faint">None recorded.</li>
                                @endforelse
                            </ul>
                        </div>
                        <div class="flex flex-col gap-3 rounded-xl bg-canvas p-6">
                            <h3 class="text-sm font-bold text-ink">Model inferences</h3>
                            <ul class="flex flex-col gap-1.5">
                                @forelse ($data['inferences'] ?? [] as $inference)
                                    <li class="text-sm text-body">{{ $inference['claim'] }} <span class="text-xs text-faint">({{ $inference['confidence'] }})</span></li>
                                @empty
                                    <li class="text-sm text-faint">None recorded.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>

                    @if (! empty($data['uncertainties']))
                        <div class="rounded-xl bg-warn-soft p-5 text-sm text-warn">
                            <p class="font-semibold">Uncertain</p>
                            <ul class="mt-1 list-inside list-disc">
                                @foreach ($data['uncertainties'] as $u)
                                    <li>{{ $u }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <details id="edit-dna" class="rounded-xl border border-line bg-surface p-7">
                        <summary class="cursor-pointer text-sm font-bold text-ink">Edit before redoing</summary>

                        <form method="POST" action="{{ route('analyses.website-dna.update', $run->public_id) }}" class="mt-5 flex flex-col gap-4">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label class="text-[13px] font-medium text-ink" for="business_name">Business name</label>
                                <input id="business_name" name="business_name" type="text" value="{{ old('business_name', $data['business_name'] ?? '') }}"
                                       class="mt-1.5 w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-accent focus:outline-none">
                            </div>

                            <div>
                                <label class="text-[13px] font-medium text-ink" for="business_summary">Business summary</label>
                                <textarea id="business_summary" name="business_summary" rows="3"
                                          class="mt-1.5 w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-accent focus:outline-none">{{ old('business_summary', $data['business_summary'] ?? '') }}</textarea>
                            </div>

                            <div>
                                <label class="text-[13px] font-medium text-ink" for="value_proposition_text">Value proposition</label>
                                <input id="value_proposition_text" name="value_proposition_text" type="text"
                                       value="{{ old('value_proposition_text', $data['value_proposition']['text'] ?? '') }}"
                                       class="mt-1.5 w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-accent focus:outline-none">
                            </div>

                            <div>
                                <label class="text-[13px] font-medium text-ink" for="primary_conversion_goal">Primary conversion goal</label>
                                <select id="primary_conversion_goal" name="primary_conversion_goal" class="mt-1.5 w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-accent focus:outline-none">
                                    @foreach (['lead', 'booking', 'purchase', 'inquiry', 'awareness', 'unknown'] as $goal)
                                        <option value="{{ $goal }}" @selected(($data['primary_conversion_goal'] ?? null) === $goal)>{{ ucfirst($goal) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="text-[13px] font-medium text-ink" for="tone_of_voice">Tone of voice (comma separated)</label>
                                <input id="tone_of_voice" name="tone_of_voice" type="text" value="{{ old('tone_of_voice', implode(', ', $data['tone_of_voice'] ?? [])) }}"
                                       class="mt-1.5 w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-accent focus:outline-none">
                            </div>

                            <button type="submit" class="w-fit rounded-lg bg-ink px-5 py-2.5 text-sm font-semibold text-canvas hover:opacity-85">
                                Save edits
                            </button>
                        </form>
                    </details>

                    <form method="POST" action="{{ route('analyses.redo', $run->public_id) }}">
                        @csrf
                        <button type="submit" class="w-fit rounded-lg border border-accent px-5 py-2.5 text-sm font-semibold text-accent-ink hover:bg-accent hover:text-white">
                            Generate REDO from this DNA
                        </button>
                    </form>
                </div>
            </main>
        </div>
    @endif
@endsection
