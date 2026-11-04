@extends('layouts.app')

@section('title', 'Design Intelligence — REDOLIO Admin')

@php use Illuminate\Support\Str; @endphp

@section('content')
    @include('admin.partials.header', ['active' => 'design-intelligence'])

    <main class="mx-auto max-w-5xl px-8 py-10">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-ink">Design Intelligence</h1>
                <p class="mt-1 text-sm text-muted">Prebuilt Design Directions available to signed-up users in the REDOLIO gallery.</p>
            </div>
            <a href="{{ route('admin.design-directions.create') }}" class="rounded-full border border-line px-5 py-2.5 text-sm font-semibold text-ink hover:border-line-strong">
                New direction
            </a>
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-lg {{ session('status_error') ? 'bg-bad-soft text-bad' : 'bg-ok-soft text-ok' }} px-4 py-3 text-sm">{{ session('status') }}</div>
        @endif

        {{-- Generate from URL --}}
        <div class="mt-8 rounded-xl border border-line bg-surface p-6">
            <p class="text-sm font-bold text-ink">Generate a new direction from a reference website</p>
            <p class="mt-1 text-xs text-faint">
                REDOLIO will crawl the site and ask the active AI provider to capture its design taste — color palette, typography,
                layout composition, and traits — as a new prebuilt Design Direction. The direction renders through the closest
                matching REDOLIO template; captured colors/typography/traits are stored as its guideline metadata.
            </p>
            <form method="POST" action="{{ route('admin.design-directions.generate') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div class="flex-1 min-w-[200px]">
                    <label for="gen_name" class="text-xs font-medium text-muted">Direction name (optional)</label>
                    <input id="gen_name" name="name" type="text" placeholder="Leave blank to auto-name from the captured industry"
                           class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
                </div>
                <div class="flex-[2] min-w-[280px]">
                    <label for="gen_url" class="text-xs font-medium text-muted">Reference website URL</label>
                    <input id="gen_url" name="source_url" type="text" required placeholder="https://www.stripe.com"
                           class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
                </div>
                <button type="submit" class="rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-white hover:bg-accent-strong">
                    Generate →
                </button>
            </form>

            {{-- Import a DESIGN.md --}}
            <div class="mt-6 border-t border-line pt-5">
                <p class="text-sm font-bold text-ink">Import a DESIGN.md style (GradientDeck UI)</p>
                <p class="mt-1 text-xs text-faint">
                    <a href="https://ui.gradientdeck.com/" target="_blank" rel="noopener" class="font-semibold text-accent-ink underline">ui.gradientdeck.com</a> publishes free
                    DESIGN.md files written from real websites. Paste a style address and REDOLIO converts it into a Design Direction (palette,
                    typography, spacing and layout approach) with a screenshot of the site that inspired it. The new direction starts hidden for your review.
                </p>
                <form method="POST" action="{{ route('admin.design-directions.import-design-md') }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="min-w-[200px] flex-1">
                        <label for="md_name" class="text-xs font-medium text-muted">Direction name (optional)</label>
                        <input id="md_name" name="name" type="text" value="{{ old('name') }}" placeholder="Leave blank to use the style's name"
                               class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
                    </div>
                    <div class="min-w-[280px] flex-[2]">
                        <label for="md_url" class="text-xs font-medium text-muted">GradientDeck style address</label>
                        <input id="md_url" name="design_md_url" type="text" required value="{{ old('design_md_url') }}" placeholder="https://ui.gradientdeck.com/styles/kobbe"
                               class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
                        @error('design_md_url')<p class="mt-1 text-xs text-bad">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-white hover:bg-accent-strong">Import →</button>
                </form>
            </div>

            @if ($generations->isNotEmpty())
                <div class="mt-5 flex flex-col gap-2 border-t border-line pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-faint">Recent generations</p>
                    @foreach ($generations as $generation)
                        @php
                            $genStatusClasses = match ($generation->status) {
                                'complete' => 'bg-ok-soft text-ok',
                                'failed' => 'bg-bad-soft text-bad',
                                default => 'bg-accent-soft text-accent-ink',
                            };
                        @endphp
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-ink">{{ $generation->designDirection?->name ?: ($generation->requested_name ?: 'Auto-named from industry') }} <span class="text-faint">— {{ $generation->source_url }}</span></span>
                            <span class="rounded-full {{ $genStatusClasses }} px-2.5 py-0.5 text-xs font-semibold">{{ ucfirst($generation->status) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Direction catalog --}}
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($directions as $direction)
                <div class="flex flex-col gap-3 rounded-2xl border border-line bg-surface p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-base font-bold text-ink">{{ $direction->name }}</p>
                            <p class="text-xs text-faint">{{ $direction->direction_key }} · template: {{ $direction->recipe_json['template_key'] ?? $direction->direction_key }}</p>
                        </div>
                        <span class="shrink-0 rounded-full {{ $direction->is_selectable ? 'bg-ok-soft text-ok' : 'bg-surface-2 text-muted' }} px-2.5 py-0.5 text-xs font-semibold">
                            {{ $direction->is_selectable ? 'In gallery' : 'Not in gallery' }}
                        </span>
                    </div>

                    @if (! empty($direction->recipe_json['preview_thumbnail']))
                        <div class="overflow-hidden rounded-lg border border-line">
                            <img src="{{ $direction->recipe_json['preview_thumbnail'] }}" alt="Homepage screenshot of {{ $direction->recipe_json['source_url'] ?? 'the reference site' }}" class="aspect-video w-full object-cover object-top" loading="lazy">
                            <p class="truncate bg-surface-2 px-2.5 py-1 text-[11px] text-faint">Captured from {{ $direction->recipe_json['source_url'] ?? 'reference site' }}</p>
                        </div>
                    @endif

                    @if (! empty($direction->recipe_json['color_system']))
                        <div class="flex gap-1.5">
                            @foreach (array_slice($direction->recipe_json['color_system'], 0, 6) as $hex)
                                <span class="h-5 w-5 rounded-full border border-line" style="background-color: {{ $hex }}"></span>
                            @endforeach
                        </div>
                    @endif

                    <p class="text-[13px] leading-relaxed text-body">{{ Str::limit($direction->recipe_json['description'] ?? '', 140) }}</p>

                    <div class="mt-auto flex items-center gap-3 border-t border-line pt-3">
                        <a href="{{ route('admin.design-directions.edit', $direction) }}" class="text-xs font-semibold text-accent-ink hover:text-accent-ink">Edit</a>
                        <a href="{{ route('admin.design-directions.create', ['remix' => $direction->direction_key]) }}" class="text-xs font-semibold text-ink hover:text-[#27272A]">Remix</a>
                        <form method="POST" action="{{ route('admin.design-directions.toggle-selectable', $direction) }}" class="ml-auto">
                            @csrf
                            <button type="submit" class="text-xs font-semibold {{ $direction->is_selectable ? 'text-bad hover:text-bad' : 'text-ok hover:text-ok' }}">
                                {{ $direction->is_selectable ? 'Remove from gallery' : 'Add to gallery' }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </main>
@endsection
