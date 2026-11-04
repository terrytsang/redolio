@extends('layouts.app')

@section('title', 'REDOLIO — Choose a page layout')

@php
    $hidden = collect($preferences)->filter(fn ($v) => $v !== null);
@endphp

@section('content')
    <x-app-nav :run="$run" />
    <x-stage-bar active="redo" />

    <div class="mx-auto flex max-w-6xl flex-col gap-7 px-5 py-9 sm:px-12">
        <div class="flex flex-col gap-1.5">
            <a href="{{ route('analyses.design-directions', array_filter([$run->public_id, 'category' => $categoryId])) }}" class="text-[13px] font-medium text-accent-ink hover:underline">← Change direction</a>
            <h1 class="text-[22px] font-extrabold tracking-tight text-ink">Choose a page layout for {{ $direction->name }}.</h1>
            <p class="text-[13px] text-faint">
                A layout sets the order and structure of your homepage. Previews below use sample content to show layout and art direction — they are not your website.
            </p>
        </div>

        @php $groups = [['Recommended layouts', $recommended, true], ['Other compatible layouts', $others, false]]; @endphp

        @if ($recommended === [] && $others === [])
            <div class="rounded-2xl border border-line bg-surface p-8">
                <p class="text-sm font-bold text-ink">Layouts for {{ $direction->name }} are coming soon.</p>
                <p class="mt-1 text-[13px] text-body">You can still continue — we'll build your homepage with this direction's standard structure.</p>
                <form method="POST" action="{{ route('analyses.design-directions.select', $run->public_id) }}" class="mt-5">
                    @csrf
                    <input type="hidden" name="direction_key" value="{{ $direction->direction_key }}">
                    @if ($categoryId)<input type="hidden" name="category_id" value="{{ $categoryId }}">@endif
                    @foreach ($hidden as $name => $value)<input type="hidden" name="{{ $name }}" value="1">@endforeach
                    <button type="submit" class="rounded-lg bg-accent px-6 py-3 text-sm font-semibold text-white hover:bg-accent-strong">Generate preview →</button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('analyses.design-directions.select', $run->public_id) }}" class="flex flex-col gap-8">
                @csrf
                <input type="hidden" name="direction_key" value="{{ $direction->direction_key }}">
                @if ($categoryId)<input type="hidden" name="category_id" value="{{ $categoryId }}">@endif
                @foreach ($hidden as $name => $value)<input type="hidden" name="{{ $name }}" value="1">@endforeach

                @foreach ($groups as [$title, $items, $isRecommended])
                    @continue(count($items) === 0)
                    <div class="flex flex-col gap-3">
                        <h2 class="text-sm font-bold text-ink">{{ $title }}</h2>
                        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($items as $item)
                                @php
                                    $bp = $isRecommended ? $item['blueprint'] : $item;
                                    $reasons = $isRecommended ? $item['reasons'] : [];
                                    $missing = $isRecommended ? $item['missing_content'] : [];
                                    $manifest = $catalog->previewManifest($bp['id'], $direction->direction_key);
                                    $shot = ($manifest['passed'] ?? false) ? asset('storage/blueprint-previews/'.$bp['id'].'/'.$direction->direction_key.'/desktop.png') : null;
                                @endphp
                                <label class="group relative flex cursor-pointer flex-col overflow-hidden rounded-2xl border-2 border-line bg-surface transition has-[:checked]:border-accent has-[:checked]:ring-2 has-[:checked]:ring-accent/20">
                                    <input type="radio" name="blueprint_id" value="{{ $bp['id'] }}" class="absolute right-5 top-5 z-20 h-4 w-4 accent-accent" required @checked($isRecommended && $loop->first && $title === 'Recommended layouts')>
                                    <div class="relative h-52 overflow-hidden border-b border-line bg-surface-2">
                                        @if ($shot)
                                            <img src="{{ $shot }}" alt="Sample preview of the {{ $bp['name'] }} layout in {{ $direction->name }}" class="w-full object-cover object-top" loading="lazy">
                                        @else
                                            <div class="flex h-full items-center justify-center text-xs text-faint">Preview image not generated yet</div>
                                        @endif
                                    </div>
                                    <div class="flex flex-col gap-3 p-5">
                                        <div class="flex items-center gap-2">
                                            <span class="text-base font-bold text-ink">{{ $bp['name'] }}</span>
                                            @if ($isRecommended)<span class="rounded-full bg-accent-soft px-2.5 py-0.5 text-[10px] font-bold text-accent-ink">Recommended</span>@endif
                                        </div>
                                        <p class="text-[13px] leading-relaxed text-body">{{ $bp['description'] }}</p>
                                        <ul class="flex flex-col gap-1 text-[12px] leading-snug">
                                            @foreach ($reasons as $r)<li class="flex gap-1.5 text-body"><span class="text-ok" aria-hidden="true">✓</span><span>{{ $r }}</span></li>@endforeach
                                            @foreach ($missing as $m)<li class="flex gap-1.5 text-warn"><span aria-hidden="true">!</span><span>{{ $m }}</span></li>@endforeach
                                        </ul>
                                        <a href="{{ route('blueprints.preview', ['blueprint' => $bp['id'], 'direction' => $direction->direction_key]) }}" target="_blank" rel="noopener" class="mt-auto text-[12px] font-medium text-accent-ink hover:underline">Open full sample preview ↗</a>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- Hero layout: how the very top of the page is arranged --}}
                <div class="flex flex-col gap-3" x-data>
                    <div>
                        <h2 class="text-sm font-bold text-ink">Choose your hero layout</h2>
                        <p class="mt-0.5 text-[12px] text-muted">The first thing visitors see. You can change the wording and pictures later in the editor.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="group relative flex cursor-pointer flex-col gap-3 rounded-2xl border-2 border-line bg-surface p-4 transition has-[:checked]:border-accent has-[:checked]:ring-2 has-[:checked]:ring-accent/20">
                            <input type="radio" name="hero_layout" value="" class="absolute right-4 top-4 h-4 w-4 accent-accent" checked>
                            <div class="grid h-24 place-items-center rounded-xl bg-surface-2 text-[12px] font-semibold text-muted">{{ $direction->name }} default</div>
                            <div><p class="text-[14px] font-bold text-ink">Match the design</p><p class="mt-0.5 text-[12px] leading-snug text-muted">Let the design direction pick the hero that suits it best.</p></div>
                        </label>
                        @foreach (\App\Services\Blueprint\HeroLayoutChoices::CHOICES as $key => $c)
                            <label class="group relative flex cursor-pointer flex-col gap-3 rounded-2xl border-2 border-line bg-surface p-4 transition has-[:checked]:border-accent has-[:checked]:ring-2 has-[:checked]:ring-accent/20">
                                <input type="radio" name="hero_layout" value="{{ $key }}" class="absolute right-4 top-4 z-10 h-4 w-4 accent-accent">
                                @include('analyses.partials.hero-wireframe', ['key' => $key])
                                <div>
                                    <p class="text-[14px] font-bold text-ink">{{ $c['label'] }}</p>
                                    <p class="mt-0.5 text-[12px] leading-snug text-muted">{{ $c['blurb'] }}</p>
                                </div>
                                @if ($c['sides'])
                                    <fieldset class="mt-auto flex items-center gap-2 text-[12px] font-semibold text-body" aria-label="{{ $c['sides'] }} left or right">
                                        <span class="text-muted">{{ $c['sides'] }}</span>
                                        <label class="cursor-pointer rounded-full border border-line px-3 py-1 has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink"><input type="radio" name="hero_side" value="left" class="sr-only" checked> left</label>
                                        <label class="cursor-pointer rounded-full border border-line px-3 py-1 has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink"><input type="radio" name="hero_side" value="right" class="sr-only"> right</label>
                                    </fieldset>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="w-fit rounded-lg bg-accent px-6 py-3 text-sm font-semibold text-white hover:bg-accent-strong">Generate preview →</button>
            </form>
        @endif
    </div>
@endsection
