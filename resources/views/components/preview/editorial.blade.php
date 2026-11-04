{{-- Allowlisted template: editorial (Premium Editorial direction). Every
     value here has already passed DesignSpecValidator — this component
     never executes or interpolates raw model output as markup. Container
     queries, not viewport breakpoints, since this always renders inside a
     width-constrained preview pane. --}}
<div class="@container overflow-hidden rounded-xl border border-[#E7DFD0] font-serif" style="background-color: var(--redo-background, #FAF6EF); color: var(--redo-text, #221E1A);">
    <nav class="flex items-center justify-between border-b border-[#E7DFD0] px-8 py-5 @lg:px-12">
        <span class="flex items-center gap-2.5 font-sans text-sm font-semibold tracking-wide">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $spec['business_name'] }}" class="h-6 w-auto object-contain">
            @endif
            {{ $spec['business_name'] }}
        </span>
        @if ($spec['hero']['secondary_cta_label'])
            <span class="font-sans text-xs text-[#6B6358]">{{ $spec['hero']['secondary_cta_label'] }}</span>
        @endif
    </nav>

    <section class="grid grid-cols-1 items-center gap-10 px-8 py-16 @lg:grid-cols-[1.2fr_1fr] @lg:gap-16 @lg:px-12 @lg:py-24">
        <div class="min-w-0">
            @if ($spec['hero']['eyebrow'])
                <p class="font-sans text-xs font-semibold uppercase tracking-[0.2em]" style="color: var(--redo-accent, #6D3B2E);">
                    {{ $spec['hero']['eyebrow'] }}
                </p>
            @endif
            <h1 class="mt-5 text-4xl font-semibold leading-[1.08] @lg:text-6xl">
                {{ $spec['hero']['headline'] }}
            </h1>
            <p class="mt-6 max-w-xl font-sans text-base leading-relaxed text-[#6B6358] @lg:text-lg">
                {{ $spec['hero']['subheadline'] }}
            </p>
            <div class="mt-8">
                <span class="inline-block rounded-full px-7 py-3 font-sans text-sm font-semibold" style="background-color: var(--redo-accent, #6D3B2E); color: var(--redo-accent-contrast, #FAF6EF);">
                    {{ $spec['hero']['primary_cta_label'] }}
                </span>
            </div>
        </div>

        <div class="relative aspect-[4/5] w-full min-w-0 overflow-hidden rounded-2xl" style="background-color: var(--redo-surface, #F1EADC);">
            @if ($heroPortraitUrl)
                <img src="{{ $heroPortraitUrl }}" alt="{{ $spec['business_name'] }}" class="h-full w-full object-cover object-top">
            @else
                <div class="flex h-full w-full items-center justify-center font-sans text-xs uppercase tracking-widest text-[#6B6358]">
                    Portrait
                </div>
            @endif
        </div>
    </section>

    @php $sectionImageIndex = 0; @endphp
    @foreach ($spec['sections'] as $section)
        <section class="border-t border-[#E7DFD0] px-8 py-16 @lg:px-12" style="background-color: {{ $loop->even ? 'var(--redo-surface, #F1EADC)' : 'var(--redo-background, #FAF6EF)' }};">
                <h2 class="text-2xl font-semibold @lg:text-3xl">{{ $section['heading'] }}</h2>
                @if ($section['body'])
                    <p class="mt-3 max-w-2xl font-sans text-sm leading-relaxed text-[#6B6358] @lg:text-base">{{ $section['body'] }}</p>
                @endif

                @if (! empty($section['items']))
                    <div class="mt-8 grid gap-8 @sm:grid-cols-2 @5xl:grid-cols-3">
                        @foreach ($section['items'] as $item)
                            @php $itemImageUrl = $sectionImageUrls[$sectionImageIndex] ?? null; $sectionImageIndex++; @endphp
                            <div class="overflow-hidden border-t-2 pt-4" style="border-color: var(--redo-accent, #6D3B2E);">
                                @if ($itemImageUrl)
                                    <div class="mb-4 aspect-[4/3] w-full min-w-0 overflow-hidden rounded-lg" style="background-color: var(--redo-surface, #F1EADC);">
                                        <img src="{{ $itemImageUrl }}" alt="{{ $item['title'] }}" class="h-full w-full object-cover">
                                    </div>
                                @elseif ($item['icon'] ?? null)
                                    <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-full" style="background-color: var(--redo-surface, #F1EADC); color: var(--redo-accent, #6D3B2E);">
                                        <x-icon :name="$item['icon']" class="h-5 w-5"/>
                                    </div>
                                @endif
                                @if ($item['label'])
                                    <p class="font-sans text-xs font-semibold uppercase tracking-wide" style="color: var(--redo-accent, #6D3B2E);">{{ $item['label'] }}</p>
                                @endif
                                <h3 class="mt-2 text-lg font-semibold">{{ $item['title'] }}</h3>
                                <p class="mt-1 font-sans text-sm leading-relaxed text-[#6B6358]">{{ $item['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
        </section>
    @endforeach

    <footer class="border-t border-[#E7DFD0] px-8 py-10 @lg:px-12">
        <div class="flex flex-col gap-4 @sm:flex-row @sm:items-center @sm:justify-between">
            <div>
                @if ($spec['footer']['summary'])
                    <p class="font-sans text-sm text-[#6B6358]">{{ $spec['footer']['summary'] }}</p>
                @endif
                @if ($spec['footer']['contact_text'])
                    <p class="mt-1 font-sans text-sm font-medium">{{ $spec['footer']['contact_text'] }}</p>
                @endif
            </div>
            @if ($spec['footer']['cta_label'])
                <span class="inline-block w-fit rounded-full px-6 py-2.5 font-sans text-sm font-semibold" style="background-color: var(--redo-accent, #6D3B2E); color: var(--redo-accent-contrast, #FAF6EF);">
                    {{ $spec['footer']['cta_label'] }}
                </span>
            @endif
        </div>
    </footer>
</div>
