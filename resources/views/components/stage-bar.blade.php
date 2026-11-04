<nav class="flex h-12 items-center gap-3 overflow-x-auto whitespace-nowrap border-b border-line bg-surface px-4 sm:px-8" aria-label="Progress">
    @foreach ($stages as $i => $stage)
        <span @class([
            'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs',
            'bg-ok-soft text-ok' => $stage['state'] === 'complete',
            'bg-accent font-bold text-white' => $stage['state'] === 'current',
            'text-faint' => $stage['state'] === 'pending',
        ])>
            @if ($stage['state'] === 'complete')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="h-2.5 w-2.5"><path d="M20 6 9 17l-5-5"/></svg>
            @endif
            {{ $stage['label'] }}
        </span>

        @if (! $loop->last)
            <span class="h-px min-w-3 flex-1 {{ $stage['state'] === 'complete' ? 'bg-accent' : 'bg-line' }}"></span>
        @endif
    @endforeach
</nav>
