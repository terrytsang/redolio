<section class="bp-hero bp-typo" id="top" data-section="{{ $id }}">
    <div class="bp-wrap">
        @if (! empty($c['eyebrow']))<p class="bp-eyebrow bp-typo__eyebrow">{{ $c['eyebrow'] }}</p>@endif
        <h1 class="bp-typo__title">{{ $c['headline'] }}</h1>
        <div class="bp-typo__row">
            @if (! empty($c['subheadline']))<p class="bp-typo__lead">{{ $c['subheadline'] }}</p>@endif
            <div class="bp-actions">
                @include('blueprints.partials.btn', ['cta' => $c['primary_cta']])
                @include('blueprints.partials.btn', ['cta' => $c['secondary_cta'] ?? null, 'class' => 'bp-btn--ghost'])
            </div>
        </div>
    </div>
    @include('blueprints.partials.visual', ['image' => $c['image'] ?? null, 'class' => 'bp-visual--strip', 'seed' => 'strip'])
</section>
