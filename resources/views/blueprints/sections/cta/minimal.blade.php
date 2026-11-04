<section class="bp-section bp-cta-min" id="get-started" data-section="{{ $id }}">
    <div class="bp-wrap bp-cta-min__row">
        <div><h2>{{ $c['heading'] }}</h2>@if (! empty($c['body']))<p class="bp-lead">{{ $c['body'] }}</p>@endif</div>
        <div class="bp-actions" style="margin-top:0">
            @include('blueprints.partials.btn', ['cta' => $c['primary_cta']])
            @include('blueprints.partials.btn', ['cta' => $c['secondary_cta'] ?? null, 'class' => 'bp-btn--ghost'])
        </div>
    </div>
</section>
