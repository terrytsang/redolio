<section class="bp-hero bp-hero--banner bp-hero--banner-left" id="top" data-section="{{ $id }}">
    @include('blueprints.partials.hero-art', ['class' => 'bp-hero__art', 'seed' => 'banner'])
    <div class="bp-wrap bp-banner__wrap">
        <div class="bp-banner__content">
            @if (! empty($c['eyebrow']))<p class="bp-eyebrow">{{ $c['eyebrow'] }}</p>@endif
            <h1>{{ $c['headline'] }}</h1>
            @if (! empty($c['subheadline']))<p class="bp-lead">{{ $c['subheadline'] }}</p>@endif
            <div class="bp-actions">
                @include('blueprints.partials.btn', ['cta' => $c['primary_cta'], 'class' => 'bp-btn--light'])
                @include('blueprints.partials.btn', ['cta' => $c['secondary_cta'] ?? null, 'class' => 'bp-btn--light-ghost'])
            </div>
        </div>
    </div>
</section>
