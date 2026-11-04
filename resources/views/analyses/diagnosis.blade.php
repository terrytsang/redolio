@extends('layouts.app')

@section('title', 'REDOLIO — Diagnosis')

@section('content')
    <x-app-nav :run="$run" />
    <x-stage-bar active="diagnose" />

    @if (! $diagnosis)
        <div class="px-20 py-16 text-sm text-body">Diagnosis has not been generated yet.</div>
    @else
        @php $data = $diagnosis->issues_json; @endphp

        <div class="mx-auto flex max-w-4xl flex-col gap-5 px-12 py-7">
            <div class="flex items-start justify-between gap-6">
                <div>
                    <h1 class="text-[22px] font-extrabold tracking-tight text-ink">Website Diagnosis</h1>
                    <p class="text-[13px] text-faint">
                        {{ count($data['issues']) }} issues · All evidence-backed
                        @if (! empty($data['summary'])) · {{ $data['summary'] }} @endif
                    </p>
                </div>
                @if ($dna)
                    <form method="POST" action="{{ route('analyses.redo', $run->public_id) }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-accent px-5 py-2.5 text-[13px] font-semibold text-white hover:bg-accent-strong">
                            Generate REDO →
                        </button>
                    </form>
                @endif
            </div>

            @forelse ($data['issues'] as $issue)
                @php
                    $severityClasses = match ($issue['severity']) {
                        'high' => ['bg-bad-soft', 'text-bad'],
                        'medium' => ['bg-warn-soft', 'text-warn'],
                        default => ['bg-accent-soft', 'text-accent-ink'],
                    };
                @endphp
                <details class="group rounded-[10px] border border-line bg-surface px-6 py-5">
                    <summary class="flex cursor-pointer list-none flex-col gap-3.5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="rounded-full {{ $severityClasses[0] }} {{ $severityClasses[1] }} px-2.5 py-1 text-[10px] font-bold tracking-wide">
                                    {{ strtoupper($issue['severity']) }}
                                </span>
                                <span class="text-xs text-faint">{{ str_replace('_', ' ', $issue['category']) }}</span>
                            </div>
                            <span class="flex items-center gap-1 rounded-full bg-accent-soft px-2.5 py-1 text-[10px] font-semibold text-accent-ink">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-2.5 w-2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                {{ implode(', ', $issue['evidence_ids']) }}
                            </span>
                        </div>
                        <p class="text-[15px] font-bold text-ink">{{ $issue['title'] }}</p>
                        <p class="text-[13px] leading-relaxed text-body">{{ $issue['problem'] }}</p>
                    </summary>

                    <div class="mt-4 flex flex-col gap-3 border-t border-line pt-4 text-sm">
                        <p><span class="font-semibold text-ink">Why it matters:</span> <span class="text-body">{{ $issue['why_it_matters'] }}</span></p>
                        <p><span class="font-semibold text-ink">Recommendation:</span> <span class="text-body">{{ $issue['recommended_solution'] }}</span></p>
                        <p class="text-xs text-faint">
                            Confidence: {{ $issue['confidence'] }} · Verification: {{ str_replace('_', ' ', $issue['verification_type']) }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($issue['evidence_ids'] as $evidenceId)
                                <a href="{{ route('analyses.evidence', [$run->public_id, $evidenceId]) }}"
                                   class="rounded-full border border-line px-3 py-1 text-xs text-body hover:border-line-strong">
                                    Evidence: {{ $evidenceId }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </details>
            @empty
                <p class="text-sm text-faint">No evidence-backed issues were identified.</p>
            @endforelse

            @if (! empty($data['limitations']))
                <div class="rounded-xl bg-warn-soft p-5 text-sm text-warn">
                    <p class="font-semibold">Limitations</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($data['limitations'] as $limitation)
                            <li>{{ $limitation }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
@endsection
