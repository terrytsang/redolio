{{-- Allowlisted template: commerce_energetic (Commerce Energetic direction).
     Every value here has already passed DesignSpecValidator — this
     component never executes or interpolates raw model output as markup. --}}
<div class="@container overflow-hidden rounded-xl border border-black/5 font-sans" style="background-color: var(--redo-background, #FAFAF7); color: var(--redo-text, #14110F);">
    <nav class="flex items-center justify-between px-8 py-5 @lg:px-12">
        <span class="flex items-center gap-2.5 text-sm font-bold tracking-wide">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $spec['business_name'] }}" class="h-6 w-auto object-contain">
            @endif
            {{ $spec['business_name'] }}
        </span>
        <span class="rounded-full px-5 py-2.5 text-sm font-bold" style="background-color: var(--redo-accent, #FF6B35); color: var(--redo-accent-contrast, #FFFFFF);">
            {{ $spec['hero']['primary_cta_label'] }}
        </span>
    </nav>

    <section class="flex flex-col items-center px-8 py-16 text-center @lg:px-12 @lg:py-24">
        @if ($spec['hero']['eyebrow'])
            <p class="text-xs font-bold uppercase tracking-[0.2em]" style="color: var(--redo-accent, #FF6B35);">{{ $spec['hero']['eyebrow'] }}</p>
        @endif
        <h1 class="mx-auto mt-5 max-w-2xl text-4xl font-extrabold leading-[1.05] tracking-tight @lg:text-6xl">
            {{ $spec['hero']['headline'] }}
        </h1>
        <p class="mx-auto mt-6 max-w-lg text-base leading-relaxed" style="color: var(--redo-text-muted, #6B6358);">
            {{ $spec['hero']['subheadline'] }}
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <span class="inline-block rounded-full px-7 py-3 text-sm font-bold" style="background-color: var(--redo-accent, #FF6B35); color: var(--redo-accent-contrast, #FFFFFF);">
                {{ $spec['hero']['primary_cta_label'] }}
            </span>
            @if ($spec['hero']['secondary_cta_label'])
                <span class="inline-block px-3 py-3 text-sm font-semibold underline">{{ $spec['hero']['secondary_cta_label'] }}</span>
            @endif
        </div>

        @if ($heroPortraitUrl)
            <div class="relative mx-auto mt-14 aspect-[16/9] w-full max-w-3xl min-w-0 overflow-hidden rounded-2xl" style="background-color: var(--redo-surface, #0F4C3A);">
                <img src="{{ $heroPortraitUrl }}" alt="{{ $spec['business_name'] }}" class="h-full w-full object-cover object-top">
            </div>
        @endif
    </section>

    @php $sectionImageIndex = 0; @endphp
    @foreach ($spec['sections'] as $i => $section)
        @php $isBold = $i % 2 === 1; @endphp
        <section class="px-8 py-16 @lg:px-12 @lg:py-20" style="background-color: {{ $isBold ? '#0F4C3A' : '#FAFAF7' }}; color: {{ $isBold ? '#FFFFFF' : '#14110F' }};">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-2xl font-extrabold @lg:text-3xl">{{ $section['heading'] }}</h2>
                @if ($section['body'])
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed @lg:text-base" style="color: {{ $isBold ? '#CFE3DA' : '#6B6358' }};">{{ $section['body'] }}</p>
                @endif

                @if (! empty($section['items']))
                    <div class="mt-9 grid gap-5 @sm:grid-cols-2 @5xl:grid-cols-3">
                        @foreach ($section['items'] as $item)
                            @php $itemImageUrl = $sectionImageUrls[$sectionImageIndex] ?? null; $sectionImageIndex++; @endphp
                            <div class="overflow-hidden rounded-2xl" style="background-color: {{ $isBold ? 'rgba(255,255,255,0.08)' : '#FFFFFF' }}; border: 1px solid {{ $isBold ? 'rgba(255,255,255,0.12)' : 'rgba(20,17,15,0.08)' }};">
                                @if ($itemImageUrl)
                                    <div class="aspect-[16/10] w-full min-w-0 overflow-hidden">
                                        <img src="{{ $itemImageUrl }}" alt="{{ $item['title'] }}" class="h-full w-full object-cover">
                                    </div>
                                @elseif ($item['icon'] ?? null)
                                    <div class="pt-5 pl-5">
                                        <div class="flex h-11 w-11 items-center justify-center rounded-full" style="background-color: var(--redo-accent, #FF6B35); color: var(--redo-accent-contrast, #FFFFFF);">
                                            <x-icon :name="$item['icon']" class="h-5 w-5"/>
                                        </div>
                                    </div>
                                @endif
                                <div class="p-5">
                                    @if ($item['label'])
                                        <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--redo-accent, #FF6B35);">{{ $item['label'] }}</p>
                                    @endif
                                    <h3 class="mt-2 text-lg font-bold">{{ $item['title'] }}</h3>
                                    <p class="mt-1 text-sm leading-relaxed" style="color: {{ $isBold ? '#CFE3DA' : '#6B6358' }};">{{ $item['description'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endforeach

    <footer class="px-8 py-10 @lg:px-12" style="background-color: #14110F; color: #FFFFFF;">
        <div class="mx-auto flex max-w-5xl flex-col gap-4 @sm:flex-row @sm:items-center @sm:justify-between">
            <div>
                @if ($spec['footer']['summary'])
                    <p class="text-sm" style="color: #B6AE9C;">{{ $spec['footer']['summary'] }}</p>
                @endif
                @if ($spec['footer']['contact_text'])
                    <p class="mt-1 text-sm font-medium">{{ $spec['footer']['contact_text'] }}</p>
                @endif
            </div>
            @if ($spec['footer']['cta_label'])
                <span class="inline-block w-fit rounded-full px-6 py-2.5 text-sm font-bold" style="background-color: var(--redo-accent, #FF6B35); color: var(--redo-accent-contrast, #FFFFFF);">
                    {{ $spec['footer']['cta_label'] }}
                </span>
            @endif
        </div>
    </footer>
</div>
