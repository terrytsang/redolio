<section class="bp-hero" id="top" data-section="{{ $id }}">
    <div class="bp-wrap bp-hero__grid">
        <div>
            @if (! empty($c['eyebrow']))<p class="bp-eyebrow">{{ $c['eyebrow'] }}</p>@endif
            <h1>{{ $c['headline'] }}</h1>
            @if (! empty($c['subheadline']))<p class="bp-lead">{{ $c['subheadline'] }}</p>@endif
            <div class="bp-actions">
                @include('blueprints.partials.btn', ['cta' => $c['primary_cta']])
                @include('blueprints.partials.btn', ['cta' => $c['secondary_cta'] ?? null, 'class' => 'bp-btn--ghost'])
            </div>
        </div>
        @include('blueprints.partials.visual', ['image' => $c['image'] ?? null, 'class' => 'bp-visual--tall'])
    </div>
</section>
