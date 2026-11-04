<section class="bp-section bp-cta" id="get-started" data-section="{{ $id }}">
    <div class="bp-wrap bp-cta__split">
        <div><h2>{{ $c['heading'] }}</h2>@if (! empty($c['body']))<p class="bp-lead">{{ $c['body'] }}</p>@endif</div>
        <div class="bp-actions">
            @include('blueprints.partials.btn', ['cta' => $c['primary_cta'], 'class' => 'bp-btn--light'])
            @include('blueprints.partials.btn', ['cta' => $c['secondary_cta'] ?? null, 'class' => 'bp-btn--light-ghost'])
        </div>
    </div>
</section>
