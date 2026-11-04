<section class="bp-hero bp-hero--full bp-hero--full-left" id="top" data-section="{{ $id }}">
    @include('blueprints.partials.hero-art', ['class' => 'bp-full__art', 'seed' => 'full'])
    <div class="bp-wrap bp-full__wrap">
        <div class="bp-full__content">
            @if (! empty($c['eyebrow']))<p class="bp-eyebrow">{{ $c['eyebrow'] }}</p>@endif
            <h1>{{ $c['headline'] }}</h1>
            @if (! empty($c['subheadline']))<p class="bp-lead">{{ $c['subheadline'] }}</p>@endif
            <div class="bp-actions">
                @include('blueprints.partials.btn', ['cta' => $c['primary_cta']])
                @include('blueprints.partials.btn', ['cta' => $c['secondary_cta'] ?? null, 'class' => 'bp-btn--ghost'])
            </div>
        </div>
    </div>
</section>
