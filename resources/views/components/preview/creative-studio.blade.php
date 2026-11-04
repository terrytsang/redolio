{{-- Allowlisted template: creative_studio (Creative Studio direction).
     Every value here has already passed DesignSpecValidator — this
     component never executes or interpolates raw model output as markup. --}}
<div class="@container overflow-hidden rounded-xl border border-[#1A1A1A] font-sans" style="background-color: var(--redo-background, #0E0E10); color: var(--redo-text, #F5F5F0);">
    <nav class="flex items-center justify-between px-8 py-5 @lg:px-12">
        <span class="flex items-center gap-2.5 text-sm font-bold tracking-wide">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $spec['business_name'] }}" class="h-6 w-auto object-contain">
            @endif
            {{ $spec['business_name'] }}
        </span>
        @if ($spec['hero']['secondary_cta_label'])
            <span class="rounded-full border border-[#3A3A3A] px-4 py-1.5 text-xs font-semibold">{{ $spec['hero']['secondary_cta_label'] }}</span>
        @endif
    </nav>

    <section class="px-8 pb-14 pt-10 @lg:px-12 @lg:pb-20 @lg:pt-16">
        @if ($spec['hero']['eyebrow'])
            <p class="text-xs font-bold uppercase tracking-[0.3em]" style="color: var(--redo-accent, #FF5B2E);">{{ $spec['hero']['eyebrow'] }}</p>
        @endif
        <h1 class="mt-5 max-w-3xl text-5xl font-extrabold leading-[0.98] tracking-tight @lg:text-7xl">
            {{ $spec['hero']['headline'] }}
        </h1>
        <p class="mt-6 max-w-lg text-base leading-relaxed" style="color: var(--redo-text-muted, #A3A3A3);">
            {{ $spec['hero']['subheadline'] }}
        </p>
        <div class="mt-8 flex flex-wrap gap-3">
            <span class="inline-block rounded-full px-7 py-3 text-sm font-bold" style="background-color: var(--redo-accent, #FF5B2E); color: var(--redo-accent-contrast, #0E0E10);">
                {{ $spec['hero']['primary_cta_label'] }}
            </span>
        </div>

        @if ($heroPortraitUrl)
            <div class="relative mt-12 aspect-[16/9] w-full min-w-0 overflow-hidden rounded-2xl" style="background-color: var(--redo-surface, #19191C);">
                <img src="{{ $heroPortraitUrl }}" alt="{{ $spec['business_name'] }}" class="h-full w-full object-cover object-top">
            </div>
        @endif
    </section>

    @php $sectionImageIndex = 0; @endphp
    @foreach ($spec['sections'] as $section)
        <section class="border-t px-8 py-16 @lg:px-12" style="border-color: #1A1A1A; background-color: {{ $loop->even ? 'var(--redo-surface, #19191C)' : 'var(--redo-background, #0E0E10)' }};">
                <h2 class="text-2xl font-extrabold tracking-tight @lg:text-4xl">{{ $section['heading'] }}</h2>
                @if ($section['body'])
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed @lg:text-base" style="color: var(--redo-text-muted, #A3A3A3);">{{ $section['body'] }}</p>
                @endif

                @if (! empty($section['items']))
                    <div class="mt-9 grid gap-5 @sm:grid-cols-2 @5xl:grid-cols-3">
                        @foreach ($section['items'] as $item)
                            @php $itemImageUrl = $sectionImageUrls[$sectionImageIndex] ?? null; $sectionImageIndex++; @endphp
                            <div class="overflow-hidden rounded-2xl border border-white/10" style="background-color: var(--redo-surface, #19191C);">
                                @if ($itemImageUrl)
                                    <div class="aspect-[4/3] w-full min-w-0 overflow-hidden" style="background-color: #232326;">
                                        <img src="{{ $itemImageUrl }}" alt="{{ $item['title'] }}" class="h-full w-full object-cover">
                                    </div>
                                @elseif ($item['icon'] ?? null)
                                    <div class="flex aspect-[4/3] w-full items-center justify-center" style="background-color: #232326; color: var(--redo-accent, #FF5B2E);">
                                        <x-icon :name="$item['icon']" class="h-9 w-9"/>
                                    </div>
                                @endif
                                <div class="p-5">
                                    @if ($item['label'])
                                        <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--redo-accent, #FF5B2E);">{{ $item['label'] }}</p>
                                    @endif
                                    <h3 class="mt-2 text-lg font-bold">{{ $item['title'] }}</h3>
                                    <p class="mt-1 text-sm leading-relaxed" style="color: var(--redo-text-muted, #A3A3A3);">{{ $item['description'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
        </section>
    @endforeach

    <footer class="border-t px-8 py-10 @lg:px-12" style="border-color: #1A1A1A;">
        <div class="flex flex-col gap-4 @sm:flex-row @sm:items-center @sm:justify-between">
            <div>
                @if ($spec['footer']['summary'])
                    <p class="text-sm" style="color: var(--redo-text-muted, #A3A3A3);">{{ $spec['footer']['summary'] }}</p>
                @endif
                @if ($spec['footer']['contact_text'])
                    <p class="mt-1 text-sm font-medium">{{ $spec['footer']['contact_text'] }}</p>
                @endif
            </div>
            @if ($spec['footer']['cta_label'])
                <span class="inline-block w-fit rounded-full px-6 py-2.5 text-sm font-bold" style="background-color: var(--redo-accent, #FF5B2E); color: var(--redo-accent-contrast, #0E0E10);">
                    {{ $spec['footer']['cta_label'] }}
                </span>
            @endif
        </div>
    </footer>
</div>
