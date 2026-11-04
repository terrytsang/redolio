@extends('layouts.marketing')

@section('title', 'Design Direction Gallery — REDOLIO')

@section('content')
    <div class="mx-auto max-w-6xl px-6 py-16">
        <nav class="mb-12 flex items-center justify-between">
            <a href="{{ route('landing') }}" class="text-xl font-extrabold tracking-tight text-ink"><x-brand /></a>
            <div class="flex items-center gap-4 text-sm">
                @auth
                    <a href="{{ route('dashboard') }}" class="font-medium text-muted hover:text-ink">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="font-medium text-muted hover:text-ink">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-full bg-accent px-4 py-2 font-semibold text-white hover:bg-accent">Start free</a>
                @endauth
            </div>
        </nav>

        <div class="max-w-2xl">
            <h1 class="text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">Design direction gallery</h1>
            <p class="mt-4 text-lg text-muted">
                Every design direction REDOLIO can generate, open to every plan — Free, Starter, and Agency alike.
                Remix any direction's style on your own site: your content, your photos, your business, in this
                direction's composition and type system.
            </p>
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($directions as $direction)
                <div class="flex flex-col overflow-hidden rounded-2xl border border-line bg-surface">
                    <div class="p-3">
                        <x-direction-screenshot :direction="$direction" />
                    </div>
                    <div class="flex flex-1 flex-col gap-3 p-5">
                        <h2 class="text-base font-bold text-ink">{{ $direction->name }}</h2>
                        <p class="text-[13px] leading-relaxed text-muted">{{ $direction->recipe_json['description'] ?? '' }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (array_slice($direction->recipe_json['brand_traits'] ?? [], 0, 3) as $trait)
                                <span class="rounded-full bg-ink/10 px-2.5 py-1 text-[11px] text-body">{{ $trait }}</span>
                            @endforeach
                        </div>

                        @if (! empty($direction->recipe_json['preview_thumbnail']))
                            <div class="flex items-center gap-2 border-t border-line pt-3">
                                <img src="{{ $direction->recipe_json['preview_thumbnail'] }}" alt="Reference site screenshot" class="h-9 w-14 shrink-0 rounded border border-line object-cover object-top">
                                <p class="truncate text-[11px] text-faint">Inspired by a real {{ parse_url($direction->recipe_json['source_url'] ?? '', PHP_URL_HOST) ?: 'reference' }} site</p>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('directions.remix', $direction->direction_key) }}" class="mt-auto">
                            @csrf
                            <button type="submit" class="w-full rounded-full bg-ink/10 py-2.5 text-sm font-semibold text-white hover:bg-ink/15">
                                Remix this direction →
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-10 text-center text-xs text-faint">
            Remixing applies a direction's style — type, color, layout — to your own site. It never copies another
            user's business content, copy, or images onto your site.
        </p>
    </div>
@endsection
