{{-- Allowlisted template: modern_corporate. Every value here has already
     passed DesignSpecValidator — this component never executes or
     interpolates raw model output as markup. --}}
<div class="@container overflow-hidden rounded-xl border border-[#E2E8F0] font-sans" style="background-color: var(--redo-background, #FFFFFF); color: var(--redo-text, #0F172A);">
    <nav class="flex items-center justify-between border-b border-[#E2E8F0] px-8 py-5 @lg:px-12">
        <span class="flex items-center gap-2.5 text-sm font-bold tracking-wide">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $spec['business_name'] }}" class="h-6 w-auto object-contain">
            @endif
            {{ $spec['business_name'] }}
        </span>
        <span class="rounded-md px-4 py-2 text-xs font-semibold" style="background-color: var(--redo-accent, #1D4ED8); color: var(--redo-accent-contrast, #FFFFFF);">
            {{ $spec['hero']['primary_cta_label'] }}
        </span>
    </nav>

    <section class="grid grid-cols-1 items-center gap-10 px-8 py-16 @lg:grid-cols-2 @lg:gap-14 @lg:px-12 @lg:py-24">
        <div class="min-w-0">
            @if ($spec['hero']['eyebrow'])
                <p class="inline-block rounded px-2.5 py-1 text-xs font-semibold uppercase tracking-wide" style="background-color: var(--redo-surface, #F1F5F9); color: var(--redo-accent, #1D4ED8);">
                    {{ $spec['hero']['eyebrow'] }}
                </p>
            @endif
            <h1 class="mt-5 text-3xl font-bold leading-[1.15] tracking-tight @lg:text-5xl">
                {{ $spec['hero']['headline'] }}
            </h1>
            <p class="mt-5 max-w-lg text-base leading-relaxed text-[#475569] @lg:text-lg">
                {{ $spec['hero']['subheadline'] }}
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <span class="inline-block rounded-md px-6 py-3 text-sm font-semibold" style="background-color: var(--redo-accent, #1D4ED8); color: var(--redo-accent-contrast, #FFFFFF);">
                    {{ $spec['hero']['primary_cta_label'] }}
                </span>
                @if ($spec['hero']['secondary_cta_label'])
                    <span class="inline-block rounded-md border border-[#CBD5E1] px-6 py-3 text-sm font-semibold text-[#0F172A]">
                        {{ $spec['hero']['secondary_cta_label'] }}
                    </span>
                @endif
            </div>
        </div>

        <div class="aspect-[4/3] w-full min-w-0 overflow-hidden rounded-lg" style="background-color: var(--redo-surface, #F1F5F9);">
            @if ($heroPortraitUrl)
                <img src="{{ $heroPortraitUrl }}" alt="{{ $spec['business_name'] }}" class="h-full w-full object-cover object-top">
            @else
                <div class="flex h-full w-full items-center justify-center text-xs font-semibold uppercase tracking-widest text-[#94A3B8]">
                    Feature image
                </div>
            @endif
        </div>
    </section>

    @php $sectionImageIndex = 0; @endphp
    @foreach ($spec['sections'] as $section)
        <section class="border-t border-[#E2E8F0] px-8 py-16 @lg:px-12" style="background-color: {{ $loop->even ? 'var(--redo-surface, #F8FAFC)' : 'var(--redo-background, #FFFFFF)' }};">
                <h2 class="text-2xl font-bold @lg:text-3xl">{{ $section['heading'] }}</h2>
                @if ($section['body'])
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-[#475569] @lg:text-base">{{ $section['body'] }}</p>
                @endif

                @if (! empty($section['items']))
                    <div class="mt-8 grid gap-5 @sm:grid-cols-2 @5xl:grid-cols-3">
                        @foreach ($section['items'] as $item)
                            @php $itemImageUrl = $sectionImageUrls[$sectionImageIndex] ?? null; $sectionImageIndex++; @endphp
                            <div class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white p-6">
                                @if ($itemImageUrl)
                                    <div class="-mx-6 -mt-6 mb-5 aspect-[16/10] w-[calc(100%+3rem)] min-w-0 overflow-hidden" style="background-color: var(--redo-surface, #F1F5F9);">
                                        <img src="{{ $itemImageUrl }}" alt="{{ $item['title'] }}" class="h-full w-full object-cover">
                                    </div>
                                @elseif ($item['icon'] ?? null)
                                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full" style="background-color: var(--redo-surface, #F1F5F9); color: var(--redo-accent, #1D4ED8);">
                                        <x-icon :name="$item['icon']" class="h-6 w-6"/>
                                    </div>
                                @endif
                                @if ($item['label'])
                                    <p class="text-xs font-semibold uppercase tracking-wide" style="color: var(--redo-accent, #1D4ED8);">{{ $item['label'] }}</p>
                                @endif
                                <h3 class="mt-2 font-semibold">{{ $item['title'] }}</h3>
                                <p class="mt-1 text-sm leading-relaxed text-[#475569]">{{ $item['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
        </section>
    @endforeach

    <footer class="border-t border-[#E2E8F0] px-8 py-10 @lg:px-12">
        <div class="flex flex-col gap-4 @sm:flex-row @sm:items-center @sm:justify-between">
            <div>
                @if ($spec['footer']['summary'])
                    <p class="text-sm text-[#475569]">{{ $spec['footer']['summary'] }}</p>
                @endif
                @if ($spec['footer']['contact_text'])
                    <p class="mt-1 text-sm font-medium">{{ $spec['footer']['contact_text'] }}</p>
                @endif
            </div>
            @if ($spec['footer']['cta_label'])
                <span class="inline-block w-fit rounded-md px-6 py-2.5 text-sm font-semibold" style="background-color: var(--redo-accent, #1D4ED8); color: var(--redo-accent-contrast, #FFFFFF);">
                    {{ $spec['footer']['cta_label'] }}
                </span>
            @endif
        </div>
    </footer>
</div>
