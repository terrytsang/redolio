@php
    use App\Support\DesignTokens;

    $brand = $spec['brand'];
    $primary = DesignTokens::colorHex($brand['color_tokens']['primary']);
    $secondary = DesignTokens::colorHex($brand['color_tokens']['secondary']);
    $accent = DesignTokens::colorHex($brand['color_tokens']['accent']);
    $background = DesignTokens::colorHex($brand['color_tokens']['background']);
    $textColor = DesignTokens::colorHex($brand['color_tokens']['text']);
    $typographyClass = DesignTokens::typographyClass($brand['typography']);
    $spacingClass = DesignTokens::spacingClass($brand['spacing_scale']);
    $buttonClass = DesignTokens::buttonClass($brand['button_style']);
@endphp

{{-- Allowlisted template: smb_default. Every value here has already
     passed DesignSpecValidator — this component never executes or
     interpolates raw model output as markup.

     This template is always embedded in a width-constrained pane (the
     Preview page's split view), not the full viewport, so breakpoints
     use Tailwind's @container queries (keyed off this div's own width)
     rather than viewport-width sm:/lg: breakpoints — otherwise a 3-column
     grid triggers at desktop viewport widths even when the actual
     rendered column is only a few hundred pixels wide. --}}
<div class="{{ $typographyClass }} @container overflow-hidden rounded-xl border border-slate-200 shadow-sm" style="background-color: {{ $background }}; color: {{ $textColor }};">
    <section class="px-8 py-16 text-center @lg:py-24" style="background-color: {{ $primary }};">
        <p class="text-sm font-medium uppercase tracking-wide" style="color: {{ $accent }};">
            {{ $spec['hero']['eyebrow'] ?? $spec['business_name'] }}
        </p>
        <h1 class="mx-auto mt-4 max-w-3xl text-3xl font-semibold @lg:text-5xl" style="color: {{ $background }};">
            {{ $spec['hero']['headline'] }}
        </h1>
        <p class="mx-auto mt-4 max-w-2xl text-base @lg:text-lg" style="color: {{ $background }}; opacity: 0.85;">
            {{ $spec['hero']['subheadline'] }}
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <span class="{{ $buttonClass }} inline-block px-6 py-3 text-sm font-semibold" style="background-color: {{ $accent }}; color: {{ $primary }};">
                {{ $spec['hero']['primary_cta_label'] }}
            </span>
            @if ($spec['hero']['secondary_cta_label'])
                <span class="{{ $buttonClass }} inline-block px-6 py-3 text-sm font-semibold" style="border-color: {{ $background }}; color: {{ $background }};">
                    {{ $spec['hero']['secondary_cta_label'] }}
                </span>
            @endif
        </div>
    </section>

    <div class="flex flex-col gap-14 px-8 py-14 {{ $spacingClass }} @lg:px-12">
        @foreach ($spec['sections'] as $section)
            <section>
                <h2 class="text-xl font-semibold @lg:text-2xl">{{ $section['heading'] }}</h2>
                @if ($section['body'])
                    <p class="mt-2 max-w-3xl text-sm text-slate-600 @lg:text-base">{{ $section['body'] }}</p>
                @endif

                @if (! empty($section['items']))
                    <div class="mt-6 grid gap-6 @sm:grid-cols-2 @5xl:grid-cols-3">
                        @foreach ($section['items'] as $item)
                            <div class="rounded-lg border border-slate-200 p-5">
                                @if ($item['icon'] ?? null)
                                    <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-full" style="background-color: {{ $primary }}; color: {{ $accent }};">
                                        <x-icon :name="$item['icon']" class="h-5 w-5"/>
                                    </div>
                                @endif
                                @if ($item['label'])
                                    <p class="text-xs font-semibold uppercase tracking-wide" style="color: {{ $secondary }};">{{ $item['label'] }}</p>
                                @endif
                                <h3 class="mt-1 font-semibold">{{ $item['title'] }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ $item['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <footer class="border-t border-slate-200 px-8 py-10 text-center @lg:px-12">
        @if ($spec['footer']['summary'])
            <p class="text-sm text-slate-600">{{ $spec['footer']['summary'] }}</p>
        @endif
        @if ($spec['footer']['contact_text'])
            <p class="mt-2 text-sm font-medium">{{ $spec['footer']['contact_text'] }}</p>
        @endif
        @if ($spec['footer']['cta_label'])
            <span class="{{ $buttonClass }} mt-4 inline-block px-6 py-3 text-sm font-semibold" style="background-color: {{ $primary }}; color: {{ $background }};">
                {{ $spec['footer']['cta_label'] }}
            </span>
        @endif
    </footer>
</div>
