@php
    // Renders the real Blade template (same one real users get) with
    // generic sample content, scaled down to card size — so this shows
    // the direction's actual hero section, real buttons and real layout,
    // not an abstract mockup. 1cqw/1px is a unitless ratio of the card's
    // own rendered width (container query units), so the scale tracks
    // the card's actual size at every breakpoint with no JS.
    $sampleSpec = \App\Support\SampleDesignSpec::forDirection($direction->recipe_json['template_key'] ?? $direction->direction_key);
    $renderedHtml = app(\App\Services\Rendering\PreviewRenderer::class)->render($sampleSpec, null);
    $designWidth = 900;

    // Two directions can share the same underlying template (e.g. an
    // admin-captured direction reusing "Modern Corporate"'s markup) — the
    // template's own hardcoded colors are expressed as var(--redo-*, hex)
    // with that hex as fallback, so setting these custom properties here
    // (and only here, for this thumbnail) re-tints the shared template
    // with this direction's own captured palette instead of looking like
    // a pixel-identical duplicate. Real redesigns never set these
    // variables, so production rendering is unaffected.
    $colorSystem = $direction->recipe_json['color_system'] ?? [];
    $colorVars = collect($colorSystem)
        ->mapWithKeys(fn ($hex, $role) => ['--redo-'.str_replace('_', '-', $role) => $hex])
        ->map(fn ($hex, $var) => "{$var}: {$hex};")
        ->implode(' ');
@endphp

<div class="@container aspect-[4/3] w-full overflow-hidden rounded-xl border border-black/10 bg-white" style="{{ $colorVars }}">
    <div style="width: {{ $designWidth }}px; transform-origin: top left; transform: scale(calc(1cqw / 1px * 100 / {{ $designWidth }}));">
        {!! $renderedHtml !!}
    </div>
</div>
