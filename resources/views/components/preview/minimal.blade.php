{{-- Allowlisted template: minimal (Modern Minimal direction). Every value
     here has already passed DesignSpecValidator — this component never
     executes or interpolates raw model output as markup. --}}
<div class="@container overflow-hidden rounded-xl border border-[#E4E4E7] font-sans" style="background-color: var(--redo-background, #FFFFFF); color: var(--redo-text, #18181B);">
    <nav class="flex items-center justify-between px-8 py-6 @lg:px-12">
        <span class="flex items-center gap-2.5 text-sm font-semibold tracking-wide">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $spec['business_name'] }}" class="h-6 w-auto object-contain">
            @endif
            {{ $spec['business_name'] }}
        </span>
        @if ($spec['hero']['secondary_cta_label'])
            <span class="text-xs text-[#71717A]">{{ $spec['hero']['secondary_cta_label'] }}</span>
        @endif
    </nav>

    <section class="flex flex-col items-center px-8 py-20 text-center @lg:px-12 @lg:py-28">
        @if ($spec['hero']['eyebrow'])
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#71717A]">{{ $spec['hero']['eyebrow'] }}</p>
        @endif
        <h1 class="mx-auto mt-5 max-w-2xl text-4xl font-semibold leading-[1.1] tracking-tight @lg:text-6xl">
            {{ $spec['hero']['headline'] }}
        </h1>
        <p class="mx-auto mt-6 max-w-lg text-base leading-relaxed text-[#71717A] @lg:text-lg">
            {{ $spec['hero']['subheadline'] }}
        </p>
        <span class="mt-8 inline-block rounded-full px-7 py-3 text-sm font-semibold" style="background-color: var(--redo-accent, #18181B); color: var(--redo-accent-contrast, #FFFFFF);">
            {{ $spec['hero']['primary_cta_label'] }}
        </span>

        @if ($heroPortraitUrl)
            <div class="mx-auto mt-14 aspect-[16/10] w-full max-w-2xl overflow-hidden rounded-xl" style="background-color: var(--redo-surface, #F7F7F5);">
                <img src="{{ $heroPortraitUrl }}" alt="{{ $spec['business_name'] }}" class="h-full w-full object-cover object-top">
            </div>
        @endif
    </section>

    @php $sectionImageIndex = 0; @endphp
    @foreach ($spec['sections'] as $section)
        <section class="border-t border-[#E4E4E7] px-8 py-16 text-center @lg:px-12" style="background-color: {{ $loop->even ? 'var(--redo-surface, #F7F7F5)' : 'var(--redo-background, #FFFFFF)' }};">
                <h2 class="text-2xl font-semibold @lg:text-3xl">{{ $section['heading'] }}</h2>
                @if ($section['body'])
                    <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-[#71717A] @lg:text-base">{{ $section['body'] }}</p>
                @endif

                @if (! empty($section['items']))
                    <div class="mx-auto mt-10 grid max-w-4xl gap-x-8 gap-y-10 text-left @sm:grid-cols-2 @5xl:grid-cols-3">
                        @foreach ($section['items'] as $item)
                            @php $itemImageUrl = $sectionImageUrls[$sectionImageIndex] ?? null; $sectionImageIndex++; @endphp
                            <div>
                                @if ($itemImageUrl)
                                    <div class="mb-4 aspect-[16/10] w-full min-w-0 overflow-hidden rounded-lg" style="background-color: var(--redo-surface, #F7F7F5);">
                                        <img src="{{ $itemImageUrl }}" alt="{{ $item['title'] }}" class="h-full w-full object-cover">
                                    </div>
                                @elseif ($item['icon'] ?? null)
                                    <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-full" style="background-color: var(--redo-surface, #F7F7F5); color: var(--redo-text, #18181B);">
                                        <x-icon :name="$item['icon']" class="h-5 w-5"/>
                                    </div>
                                @endif
                                @if ($item['label'])
                                    <p class="text-xs font-semibold uppercase tracking-wide text-[#A1A1AA]">{{ $item['label'] }}</p>
                                @endif
                                <h3 class="mt-2 font-semibold">{{ $item['title'] }}</h3>
                                <p class="mt-1 text-sm leading-relaxed text-[#71717A]">{{ $item['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
        </section>
    @endforeach

    <footer class="border-t border-[#E4E4E7] px-8 py-10 text-center @lg:px-12">
        @if ($spec['footer']['summary'])
            <p class="text-sm text-[#71717A]">{{ $spec['footer']['summary'] }}</p>
        @endif
        @if ($spec['footer']['contact_text'])
            <p class="mt-2 text-sm font-medium">{{ $spec['footer']['contact_text'] }}</p>
        @endif
        @if ($spec['footer']['cta_label'])
            <span class="mt-5 inline-block rounded-full px-6 py-2.5 text-sm font-semibold" style="background-color: var(--redo-accent, #18181B); color: var(--redo-accent-contrast, #FFFFFF);">
                {{ $spec['footer']['cta_label'] }}
            </span>
        @endif
    </footer>
</div>
