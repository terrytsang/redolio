@if (! $report)
    <p class="rounded-lg border border-dashed border-line px-4 py-6 text-center text-sm text-faint">
        Not run yet for this version. Visual QA builds the real static artifact, captures desktop, tablet and mobile
        screenshots with a headless browser, and checks for overflow, broken images and page errors.
    </p>
@elseif (in_array($report->status, ['pending', 'running']))
    <div class="flex items-center gap-3 rounded-lg border border-line bg-canvas px-4 py-6">
        <svg class="h-5 w-5 animate-spin text-accent-ink" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <p class="text-sm text-body">Capturing screenshots and running checks…</p>
    </div>
@elseif ($report->status === 'failed')
    <p class="rounded-lg bg-bad-soft px-4 py-3 text-sm text-bad">
        {{ $report->error_message ?? 'Visual QA failed.' }}
    </p>
@else
    @php
        $findings = $report->report_json['findings'] ?? [];
        $screenshotUrls = $report->report_json['screenshot_urls'] ?? [];
        $aiReview = $report->report_json['ai_review'] ?? null;
        $failed = collect($findings)->where('status', 'failed');
        $findings = collect($findings)->sortBy(fn ($f) => $f['status'] === 'failed' ? 0 : 1)->values()->all();
        $lineage = $report->report_json ?? [];
        $previousReport = ! empty($lineage['previous_report_id']) ? \App\Models\VisualQaReport::find($lineage['previous_report_id']) : null;
        $beforeUrls = $previousReport?->report_json['screenshot_urls'] ?? [];
        $severityClasses = fn ($s) => match ($s) {
            'critical' => 'bg-bad-soft text-bad',
            'high' => 'bg-warn-soft text-warn',
            'medium' => 'bg-warn-soft text-warn',
            default => 'bg-surface-2 text-muted',
        };
    @endphp

    <div class="flex flex-col gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="rounded-full {{ $failed->isEmpty() ? 'bg-ok-soft text-ok' : 'bg-warn-soft text-warn' }} px-3 py-1 text-xs font-bold">
                    {{ $failed->isEmpty() ? 'All checks passed' : $failed->count().' issue(s) found' }}
                </span>
                @if ($aiReview)
                    <span class="rounded-full bg-accent-soft px-3 py-1 text-xs font-bold text-accent-ink">AI-reviewed</span>
                @endif
            </div>

            @if ($failed->isNotEmpty())
                <form method="POST" action="{{ route('analyses.preview.visual-qa.refine', [$run->public_id, $report]) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-accent px-4 py-2 text-[13px] font-semibold text-white hover:bg-accent-strong">
                        Refine from these findings →
                    </button>
                </form>
            @endif
        </div>

        @if ($aiReview)
            <p class="rounded-lg bg-accent-soft px-4 py-3 text-sm text-accent-ink">{{ $aiReview }}</p>
        @endif

        @if (! empty($lineage['blueprint']))
            <p class="text-xs text-muted">
                Checked against the <strong>{{ app(\App\Services\Blueprint\BlueprintCatalog::class)->find($lineage['blueprint']['blueprint_id'])['name'] ?? \Illuminate\Support\Str::headline($lineage['blueprint']['blueprint_id']) }}</strong> blueprint at desktop, tablet and mobile
                using the <strong>{{ $lineage['provider'] ?? 'local' }}</strong> browser.
                @if (($lineage['blueprint']['content_mapping_status'] ?? '') === 'partial') Some sections were left out for lack of verified content. @endif
            </p>
        @endif

        @if (! empty($lineage['refined_to_version']))
            <div class="rounded-lg border border-accent/30 bg-accent-soft px-4 py-3 text-sm text-accent-ink">
                <p class="font-semibold">Fixed automatically in <a class="underline" href="{{ route('analyses.preview.version', [$run->public_id, $lineage['refined_to_version']]) }}">v{{ $lineage['refined_to_version'] }} →</a></p>
                <ul class="mt-1 list-disc pl-5 text-xs">
                    @foreach ($lineage['fixes'] ?? [] as $fix)<li>{{ $fix['action'] }} ({{ $fix['target'] }})</li>@endforeach
                </ul>
            </div>
        @endif

        @if ($previousReport)
            <div class="rounded-lg border border-accent/30 bg-surface px-4 py-3">
                <p class="text-sm font-semibold text-accent-ink">Refinement pass {{ $lineage['attempt'] - 1 }} of {{ config('redo.qa_max_refinements') }} — from v{{ $lineage['refined_from_version'] }}</p>
                <ul class="mt-1 list-disc pl-5 text-xs text-body">
                    @foreach ($lineage['fixes'] ?? [] as $fix)<li>{{ $fix['action'] }} ({{ $fix['target'] }})</li>@endforeach
                </ul>
                @if ($beforeUrls)
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach (array_intersect_key($beforeUrls, ['desktop' => 1, 'mobile' => 1]) as $viewport => $url)
                            <div>
                                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-muted">Before · {{ $viewport }}</p>
                                <img src="{{ $url }}" alt="Before refinement, {{ $viewport }}" class="w-full rounded border border-line" loading="lazy">
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if (! empty($screenshotUrls))
            <div class="grid gap-4 sm:grid-cols-{{ count($screenshotUrls) >= 3 ? 3 : 2 }}">
                @foreach ($screenshotUrls as $viewport => $url)
                    <div class="overflow-hidden rounded-lg border border-line">
                        <div class="flex items-center justify-between bg-canvas px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted">
                            <span>{{ ucfirst($viewport) }}</span>
                        </div>
                        <img src="{{ $url }}" alt="{{ ucfirst($viewport) }} screenshot" class="w-full" loading="lazy">
                    </div>
                @endforeach
            </div>
        @endif

        @php
            $failedFindings = collect($findings)->where('status', 'failed');
            $passedFindings = collect($findings)->where('status', '!=', 'failed');
        @endphp

        @if ($failedFindings->isNotEmpty())
            <div class="flex flex-col gap-2">
                @foreach ($failedFindings as $finding)
                    <div class="flex items-start gap-3 rounded-lg border border-line px-4 py-3">
                        <span class="shrink-0 rounded-full {{ $severityClasses($finding['severity']) }} px-2 py-0.5 text-[10px] font-bold uppercase">{{ $finding['severity'] }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-ink">
                                {{ $finding['check'] }} <span class="text-faint">· {{ ucfirst($finding['viewport']) }}</span>
                                <span class="ml-1 {{ $finding['status'] === 'passed' ? 'text-ok' : 'text-bad' }}">{{ $finding['status'] === 'passed' ? '✓' : '✗' }}</span>
                                @if (($finding['source'] ?? null) === 'ai')
                                    <span class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-accent-ink">AI</span>
                                @endif
                            </p>
                            <p class="text-xs text-muted">{{ $finding['evidence'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($passedFindings->isNotEmpty())
            <details class="rounded-lg border border-line">
                <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-body">{{ $passedFindings->count() }} checks passed</summary>
                <div class="flex flex-col gap-2 border-t border-line p-3">
                    @foreach ($passedFindings as $finding)
                        <div class="flex items-start gap-3 rounded-lg border border-line px-4 py-3">
                            <span class="shrink-0 rounded-full {{ $severityClasses($finding['severity']) }} px-2 py-0.5 text-[10px] font-bold uppercase">{{ $finding['severity'] }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-ink">
                                    {{ $finding['check'] }} <span class="text-faint">· {{ ucfirst($finding['viewport']) }}</span>
                                    <span class="ml-1 {{ $finding['status'] === 'passed' ? 'text-ok' : 'text-bad' }}">{{ $finding['status'] === 'passed' ? '✓' : '✗' }}</span>
                                    @if (($finding['source'] ?? null) === 'ai')
                                        <span class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-accent-ink">AI</span>
                                    @endif
                                </p>
                                <p class="text-xs text-muted">{{ $finding['evidence'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
@endif
