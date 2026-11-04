@extends('layouts.app')

@section('title', 'REDOLIO — Next REDO')

@php
    use Illuminate\Support\Str;
@endphp

@section('content')
    <x-app-nav :run="$run" />
    <x-stage-bar active="next_redo" />

    <div class="mx-auto flex max-w-4xl flex-col gap-7 px-12 py-9">
        <div class="flex items-start justify-between gap-6">
            <div class="flex flex-col gap-1.5">
                <div class="flex items-center gap-2.5">
                    <h1 class="text-[22px] font-extrabold tracking-tight text-ink">Next REDO</h1>
                    <span class="rounded-full border border-amber-300 bg-warn-soft px-2.5 py-1 text-[10px] font-bold text-warn">
                        Hypothesis · Not a guarantee
                    </span>
                </div>
                <p class="text-[13px] text-faint">
                    Based on {{ $eventCount }} preview {{ Str::plural('interaction', $eventCount) }} · Signals labelled — simulated data
                </p>
            </div>
            <a href="{{ route('landing') }}" class="shrink-0 whitespace-nowrap rounded-lg bg-ink px-5 py-2.5 text-[13px] font-semibold text-canvas hover:opacity-85">
                + New analysis
            </a>
        </div>

        @if (! $recommendation)
            <div class="flex flex-col gap-4 rounded-2xl border border-line bg-surface p-10 text-center">
                <p class="text-base font-bold text-ink">Not enough signal yet</p>
                <p class="text-sm text-body">
                    Next REDO needs real preview interactions (CTA clicks, section views) before it can suggest the
                    highest-impact next improvement. {{ $eventCount }} event(s) recorded so far for this redesign.
                </p>
                @if ($designSpec && $eventCount > 0)
                    <form method="POST" action="{{ route('analyses.next-redo.generate', $run->public_id) }}" class="mx-auto">
                        @csrf
                        <button type="submit" class="rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-white hover:bg-accent-strong">
                            Generate Next REDO hypothesis
                        </button>
                    </form>
                @endif
            </div>
        @else
            <div class="flex flex-col gap-7 rounded-2xl border border-line bg-surface p-10">
                <div class="flex items-start gap-6">
                    <span class="flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-2xl bg-accent-soft">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><path d="M16 7h6v6"/><path d="m22 7-8.5 8.5-5-5L2 17"/></svg>
                    </span>
                    <div class="flex flex-col gap-3">
                        <p class="text-[11px] font-bold tracking-[0.15em] text-accent-ink">HIGHEST-IMPACT NEXT IMPROVEMENT</p>
                        <p class="text-[22px] font-extrabold tracking-tight text-ink">{{ $recommendation->recommendation }}</p>
                        <p class="text-[15px] leading-relaxed text-body">{{ $recommendation->rationale }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @php
                        $summary = $recommendation->signal_summary_json;
                        $counts = $summary['counts_by_event_name'] ?? [];
                    @endphp
                    @foreach ([
                        ['mouse-pointer-click', $counts['cta_click'] ?? 0, 'CTA clicks'],
                        ['eye', $counts['section_view'] ?? 0, 'Section views'],
                        ['sparkles', $counts['preview_opened'] ?? 0, 'Preview opens'],
                        ['users', $summary['distinct_sessions'] ?? 0, 'Sessions'],
                    ] as [$icon, $value, $label])
                        <div class="flex flex-col gap-2 rounded-xl bg-canvas p-5">
                            @if ($icon === 'mouse-pointer-click')
                                <svg viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-[18px] w-[18px]"><path d="M9 9a3 3 0 1 1 6 0"/><path d="M12 12v6"/><path d="m8 15 2 2"/><path d="m16 15-2 2"/></svg>
                            @elseif ($icon === 'eye')
                                <svg viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-[18px] w-[18px]"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            @elseif ($icon === 'sparkles')
                                <svg viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-[18px] w-[18px]"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-[18px] w-[18px]"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            @endif
                            <span class="text-[26px] font-extrabold tracking-tight text-ink">{{ $value }}</span>
                            <span class="text-xs text-faint">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-start gap-2.5 rounded-xl border border-warn/30 bg-warn-soft px-5 py-3.5">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-4 w-4 shrink-0"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                    <p class="text-[13px] leading-relaxed text-warn">
                        Signal data above is labelled 'simulated' — based on synthetic preview interactions, not real
                        end-user traffic. Treat the Next REDO recommendation as a hypothesis until you have real
                        measurement data.
                    </p>
                </div>

                @if ($designSpec)
                    <form method="POST" action="{{ route('analyses.next-redo.generate', $run->public_id) }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-line px-5 py-2.5 text-sm font-medium text-ink hover:border-line-strong">
                            Regenerate from latest signals
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>
@endsection
