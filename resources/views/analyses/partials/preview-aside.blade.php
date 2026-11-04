@if (! empty($spec->design_spec_json['rationale']))
    <div class="border-b border-line px-5 py-4">
        <p class="text-[13px] font-bold text-ink">Design rationale</p>
        <p class="mt-0.5 text-[11px] leading-relaxed text-faint">Why this REDO made these choices</p>
        <ul class="mt-3 flex flex-col gap-2 text-sm">
            @foreach ($spec->design_spec_json['rationale'] as $r)
                <li class="rounded-lg bg-canvas p-3 text-[13px] leading-relaxed text-ink">{{ $r['decision'] }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($evaluation)
    <div class="flex flex-col gap-1 border-b border-line px-5 py-4">
        <p class="text-[13px] font-bold text-ink">Issue Coverage</p>
        <p class="text-[11px] leading-relaxed text-faint">How this REDO addresses the diagnosed issues</p>
    </div>
    @foreach ($evaluation->evaluation_json['items'] as $item)
        @php
            $diagnosisIssue = collect($diagnosis?->issues_json['issues'] ?? [])->firstWhere('issue_id', $item['issue_id']);
            $statusClasses = match ($item['status']) {
                'addressed' => ['bg-ok-soft', 'text-ok', 'Addressed'],
                'partially_addressed' => ['bg-warn-soft', 'text-warn', 'Partial'],
                default => ['bg-surface-2', 'text-muted', 'Not addressed'],
            };
        @endphp
        <div class="flex flex-col gap-1.5 border-b border-line px-5 py-3.5">
            <span class="w-fit rounded-full {{ $statusClasses[0] }} {{ $statusClasses[1] }} px-2.5 py-0.5 text-[10px] font-bold tracking-wide">{{ $statusClasses[2] }}</span>
            <p class="text-xs leading-relaxed text-body">{{ $diagnosisIssue['title'] ?? $item['issue_id'] }}</p>
        </div>
    @endforeach
@endif
