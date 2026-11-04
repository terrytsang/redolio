{{-- Allowlisted template: premium_luxury (Premium Luxury direction).
     Every value here has already passed DesignSpecValidator — this
     component never executes or interpolates raw model output as markup. --}}
<div class="@container overflow-hidden rounded-xl border border-[#2A2620] font-serif" style="background-color: var(--redo-background, #15130F); color: var(--redo-text, #EFE9DC);">
    <nav class="flex items-center justify-between border-b border-[#2A2620] px-8 py-6 @lg:px-12">
        <span class="flex items-center gap-2.5 font-sans text-xs font-semibold uppercase tracking-[0.25em]">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $spec['business_name'] }}" class="h-6 w-auto object-contain">
            @endif
            {{ $spec['business_name'] }}
        </span>
        @if ($spec['hero']['secondary_cta_label'])
            <span class="font-sans text-xs tracking-wide" style="color: var(--redo-accent, #B89A5C);">{{ $spec['hero']['secondary_cta_label'] }}</span>
        @endif
    </nav>

    <section class="flex flex-col items-center px-8 py-20 text-center @lg:px-12 @lg:py-28">
        @if ($spec['hero']['eyebrow'])
            <p class="font-sans text-xs font-semibold uppercase tracking-[0.3em]" style="color: var(--redo-accent, #B89A5C);">{{ $spec['hero']['eyebrow'] }}</p>
        @endif
        <h1 class="mx-auto mt-6 max-w-2xl text-4xl font-medium leading-[1.12] @lg:text-6xl">
            {{ $spec['hero']['headline'] }}
        </h1>
        <p class="mx-auto mt-6 max-w-lg font-sans text-base leading-relaxed" style="color: var(--redo-text-muted, #B6AE9C);">
            {{ $spec['hero']['subheadline'] }}
        </p>
        <span class="mt-9 inline-block border px-8 py-3 font-sans text-xs font-semibold uppercase tracking-[0.2em]" style="border-color: var(--redo-accent, #B89A5C); color: var(--redo-text, #EFE9DC);">
            {{ $spec['hero']['primary_cta_label'] }}
        </span>

        @if ($heroPortraitUrl)
            <div class="relative mx-auto mt-16 aspect-[16/9] w-full max-w-3xl min-w-0 overflow-hidden" style="background-color: var(--redo-surface, #1E1B15);">
                <img src="{{ $heroPortraitUrl }}" alt="{{ $spec['business_name'] }}" class="h-full w-full object-cover object-top">
            </div>
        @endif
    </section>

    @php $sectionImageIndex = 0; @endphp
    @foreach ($spec['sections'] as $section)
        <section class="border-t px-8 py-20 text-center @lg:px-12" style="border-color: #2A2620; background-color: {{ $loop->even ? 'var(--redo-surface, #1E1B15)' : 'var(--redo-background, #15130F)' }};">
                <h2 class="text-2xl font-medium @lg:text-3xl">{{ $section['heading'] }}</h2>
                @if ($section['body'])
                    <p class="mx-auto mt-3 max-w-xl font-sans text-sm leading-relaxed @lg:text-base" style="color: var(--redo-text-muted, #B6AE9C);">{{ $section['body'] }}</p>
                @endif

                @if (! empty($section['items']))
                    <div class="mx-auto mt-10 grid max-w-4xl gap-x-8 gap-y-10 text-left @sm:grid-cols-2 @5xl:grid-cols-3">
                        @foreach ($section['items'] as $item)
                            @php $itemImageUrl = $sectionImageUrls[$sectionImageIndex] ?? null; $sectionImageIndex++; @endphp
                            <div>
                                @if ($itemImageUrl)
                                    <div class="mb-4 aspect-[4/5] w-full min-w-0 overflow-hidden" style="background-color: var(--redo-surface, #1E1B15);">
                                        <img src="{{ $itemImageUrl }}" alt="{{ $item['title'] }}" class="h-full w-full object-cover">
                                    </div>
                                @elseif ($item['icon'] ?? null)
                                    <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-full border" style="border-color: var(--redo-accent, #B89A5C); color: var(--redo-accent, #B89A5C);">
                                        <x-icon :name="$item['icon']" class="h-5 w-5"/>
                                    </div>
                                @endif
                                @if ($item['label'])
                                    <p class="font-sans text-xs font-semibold uppercase tracking-wide" style="color: var(--redo-accent, #B89A5C);">{{ $item['label'] }}</p>
                                @endif
                                <h3 class="mt-2 text-lg font-medium">{{ $item['title'] }}</h3>
                                <p class="mt-1 font-sans text-sm leading-relaxed" style="color: var(--redo-text-muted, #B6AE9C);">{{ $item['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
        </section>
    @endforeach

    <footer class="border-t px-8 py-10 @lg:px-12" style="border-color: #2A2620;">
        <div class="flex flex-col items-center gap-4 text-center">
            @if ($spec['footer']['summary'])
                <p class="font-sans text-sm" style="color: var(--redo-text-muted, #B6AE9C);">{{ $spec['footer']['summary'] }}</p>
            @endif
            @if ($spec['footer']['contact_text'])
                <p class="font-sans text-sm font-medium">{{ $spec['footer']['contact_text'] }}</p>
            @endif
            @if ($spec['footer']['cta_label'])
                <span class="mt-2 inline-block border px-6 py-2.5 font-sans text-xs font-semibold uppercase tracking-[0.2em]" style="border-color: var(--redo-accent, #B89A5C); color: var(--redo-text, #EFE9DC);">
                    {{ $spec['footer']['cta_label'] }}
                </span>
            @endif
        </div>
    </footer>
</div>
