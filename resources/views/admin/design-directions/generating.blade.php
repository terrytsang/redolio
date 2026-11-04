@extends('layouts.app')

@section('title', 'Capturing design direction — REDOLIO Admin')

@section('content')
    @include('admin.partials.header', ['active' => 'design-intelligence'])

    <div class="flex flex-col items-center justify-center gap-8 px-20 py-24">
        <div class="flex w-full max-w-[560px] flex-col items-center gap-6 rounded-[20px] border border-line bg-surface p-12 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full border-[3px] border-accent bg-accent-soft">
                <svg viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 animate-spin">
                    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                </svg>
            </span>

            <div class="flex flex-col gap-1.5">
                @php $isDesignMd = str_contains($generation->source_url, 'ui.gradientdeck.com'); @endphp
                <p class="text-lg font-bold text-ink">{{ $isDesignMd ? 'Importing' : 'Capturing' }} {{ $generation->requested_name !== '' ? '“'.$generation->requested_name.'”' : parse_url($generation->source_url, PHP_URL_HOST) }}…</p>
                <p class="text-sm text-body">
                    @if ($isDesignMd)
                        Reading the DESIGN.md from {{ $generation->source_url }}, converting it into a Design Direction and taking a screenshot of the
                        site that inspired it. This usually takes one to two minutes — you’ll land on the new direction when it’s ready.
                    @else
                        Crawling {{ $generation->source_url }} and asking the active AI provider to capture its design
                        taste — colors, typography, layout composition, and traits. This usually takes two to three minutes — you’ll land on the
                        new direction when it’s ready.
                    @endif
                </p>
            </div>

            <div class="h-1.5 w-full overflow-hidden rounded-full bg-line">
                <div class="h-full w-1/3 animate-pulse rounded-full bg-accent"></div>
            </div>
        </div>
    </div>

    <script>
        setTimeout(() => window.location.reload(), 3000);
    </script>
@endsection
