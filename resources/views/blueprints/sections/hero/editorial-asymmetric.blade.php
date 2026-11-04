<section class="bp-hero bp-ed" id="top" data-section="{{ $id }}">
    <div class="bp-wrap bp-ed__grid">
        <div class="bp-ed__text">
            @if (! empty($c['eyebrow']))<p class="bp-eyebrow">{{ $c['eyebrow'] }}</p>@endif
            <h1 class="bp-ed__title">{{ $c['headline'] }}</h1>
        </div>
        <div class="bp-ed__aside">
            @include('blueprints.partials.visual', ['image' => $c['image'] ?? null, 'class' => 'bp-visual--portrait', 'seed' => 'hero'])
            @if (! empty($c['subheadline']))<p class="bp-ed__lead">{{ $c['subheadline'] }}</p>@endif
            <div class="bp-actions">
                @include('blueprints.partials.btn', ['cta' => $c['primary_cta']])
                @include('blueprints.partials.btn', ['cta' => $c['secondary_cta'] ?? null, 'class' => 'bp-btn--ghost'])
            </div>
        </div>
    </div>
</section>
