@php
    $statusClasses = match ($statusColor) {
        'success' => 'bg-ok-soft text-ok',
        'danger' => 'bg-bad-soft text-bad',
        default => 'bg-accent-soft text-accent-ink',
    };
    $statusDotClasses = match ($statusColor) {
        'success' => 'bg-emerald-600',
        'danger' => 'bg-bad',
        default => 'bg-violet-700',
    };
@endphp

<header class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-b border-line bg-surface px-4 py-3 sm:px-8 lg:h-[60px] lg:flex-nowrap lg:py-0">
    <div class="flex min-w-0 items-center gap-4 sm:gap-6">
        <a href="{{ route('landing') }}" class="text-base font-extrabold tracking-tight text-ink"><x-brand /></a>
        <span class="hidden h-5 w-px bg-line sm:block"></span>
        <div class="flex min-w-0 items-center gap-2 text-[13px]">
            <span class="text-faint">Analyses</span>
            <span class="text-faint">/</span>
            <span class="truncate font-medium text-ink">{{ $hostLabel }}</span>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
            <span class="h-1.5 w-1.5 rounded-full {{ $statusDotClasses }}"></span>{{ $statusLabel }}
        </span>
        @if ($runTime)
            <span class="text-[13px] text-faint">{{ $runTime }}</span>
        @endif
        <a href="{{ route('landing') }}" class="rounded-lg bg-ink px-4 py-2 text-[13px] font-semibold text-canvas hover:opacity-85">
            + New analysis
        </a>
    </div>
</header>
