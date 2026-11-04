@extends('layouts.app')

@section('title', 'REDOLIO — Choose a design direction')

@section('content')
    <x-app-nav :run="$run" />
    <x-stage-bar active="redo" />

    <div class="mx-auto flex max-w-6xl flex-col gap-7 px-12 py-9">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-col gap-1.5">
                <h1 class="text-[22px] font-extrabold tracking-tight text-ink">Choose how your website should look.</h1>
                <p class="text-[13px] text-faint">
                    We analysed your website and matched {{ $recommendations->count() }} design directions to your business identity. Pick one to generate your full redesign.
                </p>
            </div>
            <a href="{{ route('directions.index') }}" class="shrink-0 rounded-lg border border-line bg-surface px-4 py-2 text-[13px] font-medium text-ink hover:border-line-strong">
                Browse direction gallery →
            </a>
        </div>

        <nav class="flex flex-wrap items-center gap-2" aria-label="Filter by website type">
            <span class="text-xs font-semibold uppercase tracking-wide text-faint">Website type</span>
            <a href="{{ route('analyses.design-directions', $run->public_id) }}" class="rounded-full border px-3 py-1 text-[12px] font-medium {{ $categoryId ? 'border-line bg-surface text-body hover:border-line-strong' : 'border-accent bg-accent-soft text-accent-ink' }}">All</a>
            @foreach ($categories as $category)
                <a href="{{ route('analyses.design-directions', [$run->public_id, 'category' => $category['id']]) }}" class="rounded-full border px-3 py-1 text-[12px] font-medium {{ $categoryId === $category['id'] ? 'border-accent bg-accent-soft text-accent-ink' : 'border-line bg-surface text-body hover:border-line-strong' }}">
                    {{ $category['name'] }}@if ($suggestedCategory === $category['id']) <span class="text-[10px] opacity-70">· looks like yours</span>@endif
                </a>
            @endforeach
            <span class="text-[11px] text-faint">Types only narrow what's listed — every direction can suit any website.</span>
        </nav>

        @if ($remixDirection)
            <div class="rounded-xl bg-accent-soft px-4 py-3 text-[13px] text-accent-ink">
                You're remixing the <strong>{{ $directions->get($remixDirection)?->name ?? $remixDirection }}</strong> direction from the gallery — pre-selected below.
            </div>
        @endif

        @if ($recommendations->isEmpty())
            <div class="rounded-2xl border border-line bg-surface p-10 text-center text-sm text-faint">
                {{ $categoryId ? 'No directions are listed under this website type — choose All to see every direction.' : "Design directions aren't ready yet — Website DNA must complete first." }}
            </div>
        @else
            @php
                $recommended = $recommendations->where('is_recommended', true);
                $others = $recommendations->where('is_recommended', false);
            @endphp

            <form method="GET" action="{{ route('analyses.design-directions.layout', $run->public_id) }}" class="flex flex-col gap-8">
                @if ($categoryId)<input type="hidden" name="category" value="{{ $categoryId }}">@endif

                <div class="flex flex-col gap-4 rounded-2xl border border-line bg-surface p-6">
                    <p class="text-sm font-bold text-ink">Preservation preferences</p>
                    <div class="flex flex-col gap-3">
                        @foreach ([
                            ['preserve_logo', 'Preserve original logo', 'Keep your existing logo/brand name in the new design.'],
                            ['preserve_images', 'Preserve selected images', 'Reuse your real photos/portraits instead of placeholders.'],
                            ['preserve_wording', 'Preserve original wording where appropriate', 'Keep close to your existing phrasing rather than rewriting it.'],
                        ] as [$name, $label, $help])
                            <label class="flex items-start gap-3">
                                <input type="checkbox" name="{{ $name }}" value="1" checked class="mt-1 h-4 w-4 accent-accent">
                                <span>
                                    <span class="block text-[13px] font-medium text-ink">{{ $label }}</span>
                                    <span class="block text-xs text-faint">{{ $help }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                @if ($recommended->isNotEmpty())
                    <div class="flex flex-col gap-3">
                        <h2 class="text-sm font-bold text-ink">Recommended concepts</h2>
                        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($recommended as $rec)
                                @php $direction = $directions->get($rec->direction_key); @endphp
                                <label class="group relative flex cursor-pointer flex-col gap-0 overflow-hidden rounded-2xl border-2 border-line bg-surface transition has-[:checked]:border-accent has-[:checked]:ring-2 has-[:checked]:ring-accent/20">
                                    <input
                                        type="radio"
                                        name="direction_key"
                                        value="{{ $rec->direction_key }}"
                                        class="absolute right-5 top-5 z-20 h-4 w-4 accent-accent"
                                        @checked($remixDirection ? $remixDirection === $rec->direction_key : ($currentSelection ? $currentSelection->direction_key === $rec->direction_key : $loop->first))
                                        required
                                    >

                                    @if ($direction)
                                        <div class="relative p-3 pb-0">
                                            <x-direction-screenshot :direction="$direction" />
                                            <div class="pointer-events-none absolute inset-3 hidden items-center justify-center rounded-xl bg-black/35 group-has-[:checked]:flex">
                                                <button type="submit" class="pointer-events-auto rounded-full bg-surface px-5 py-2.5 text-sm font-bold text-ink shadow-lg hover:bg-canvas">
                                                    Choose layout →
                                                </button>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="flex flex-col gap-3 p-5">
                                        <div class="flex items-center gap-2">
                                            <span class="text-base font-bold text-ink">{{ $direction?->name ?? $rec->direction_key }}</span>
                                            <span class="rounded-full bg-accent-soft px-2.5 py-0.5 text-[10px] font-bold text-accent-ink">Recommended</span>
                                        </div>

                                        <p class="text-[13px] leading-relaxed text-body">{{ $rec->rationale }}</p>
                                        @include('analyses.partials.direction-evidence', ['rec' => $rec])

                                        <div class="mt-auto flex flex-wrap gap-1.5 pt-1">
                                            @foreach (array_slice($direction?->recipe_json['brand_traits'] ?? [], 0, 3) as $trait)
                                                <span class="rounded-full bg-surface-2 px-2.5 py-1 text-[11px] text-muted">{{ $trait }}</span>
                                            @endforeach
                                        </div>

                                        @if (! empty($direction?->recipe_json['preview_thumbnail']))
                                            <div class="flex items-center gap-2 border-t border-line pt-3">
                                                <img src="{{ $direction->recipe_json['preview_thumbnail'] }}" alt="Reference site screenshot" class="h-9 w-14 shrink-0 rounded border border-line object-cover object-top">
                                                <p class="truncate text-[11px] text-faint">Inspired by a real {{ parse_url($direction->recipe_json['source_url'] ?? '', PHP_URL_HOST) ?: 'reference' }} site</p>
                                            </div>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($others->isNotEmpty())
                    <div class="flex flex-col gap-3">
                        <h2 class="text-sm font-bold text-ink">More directions</h2>
                        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($others as $rec)
                                @php $direction = $directions->get($rec->direction_key); @endphp
                                <label class="group relative flex cursor-pointer flex-col gap-0 overflow-hidden rounded-2xl border-2 border-line bg-surface transition has-[:checked]:border-accent has-[:checked]:ring-2 has-[:checked]:ring-accent/20">
                                    <input
                                        type="radio"
                                        name="direction_key"
                                        value="{{ $rec->direction_key }}"
                                        class="absolute right-5 top-5 z-20 h-4 w-4 accent-accent"
                                        @checked($remixDirection ? $remixDirection === $rec->direction_key : $currentSelection?->direction_key === $rec->direction_key)
                                    >

                                    @if ($direction)
                                        <div class="relative p-3 pb-0">
                                            <x-direction-screenshot :direction="$direction" />
                                            <div class="pointer-events-none absolute inset-3 hidden items-center justify-center rounded-xl bg-black/35 group-has-[:checked]:flex">
                                                <button type="submit" class="pointer-events-auto rounded-full bg-surface px-5 py-2.5 text-sm font-bold text-ink shadow-lg hover:bg-canvas">
                                                    Choose layout →
                                                </button>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="flex flex-col gap-3 p-5">
                                        <span class="text-base font-bold text-ink">{{ $direction?->name ?? $rec->direction_key }}</span>
                                        <p class="text-[13px] leading-relaxed text-body">{{ $rec->rationale }}</p>
                                        @include('analyses.partials.direction-evidence', ['rec' => $rec])
                                        <div class="mt-auto flex flex-wrap gap-1.5 pt-1">
                                            @foreach (array_slice($direction?->recipe_json['brand_traits'] ?? [], 0, 3) as $trait)
                                                <span class="rounded-full bg-surface-2 px-2.5 py-1 text-[11px] text-muted">{{ $trait }}</span>
                                            @endforeach
                                        </div>

                                        @if (! empty($direction?->recipe_json['preview_thumbnail']))
                                            <div class="flex items-center gap-2 border-t border-line pt-3">
                                                <img src="{{ $direction->recipe_json['preview_thumbnail'] }}" alt="Reference site screenshot" class="h-9 w-14 shrink-0 rounded border border-line object-cover object-top">
                                                <p class="truncate text-[11px] text-faint">Inspired by a real {{ parse_url($direction->recipe_json['source_url'] ?? '', PHP_URL_HOST) ?: 'reference' }} site</p>
                                            </div>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <button type="submit" class="w-fit rounded-lg bg-accent px-6 py-3 text-sm font-semibold text-white hover:bg-accent-strong">
                    Choose layout →
                </button>
            </form>
        @endif
    </div>
@endsection
