<section class="bp-section bp-section--alt" id="pricing" data-section="{{ $id }}">
    <div class="bp-wrap">
        <div class="bp-head"><h2>{{ $c['heading'] }}</h2>@if (! empty($c['subheading']))<p class="bp-lead">{{ $c['subheading'] }}</p>@endif</div>
        <div class="bp-grid bp-grid--{{ min(count($c['items']), 3) }}">
            @foreach ($c['items'] as $p)
                <article class="bp-card {{ ! empty($p['highlighted']) ? 'bp-card--hl' : '' }}">
                    <h3>{{ $p['name'] }}</h3>
                    <p class="bp-price">{{ $p['price'] }}@if (! empty($p['period']))<small> / {{ $p['period'] }}</small>@endif</p>
                    @if (! empty($p['description']))<p>{{ $p['description'] }}</p>@endif
                    @if (! empty($p['features']))<ul class="bp-ticks">@foreach ($p['features'] as $f)<li>{{ $f }}</li>@endforeach</ul>@endif
                    @include('blueprints.partials.btn', ['cta' => $p['cta'] ?? null, 'class' => ! empty($p['highlighted']) ? '' : 'bp-btn--ghost'])
                </article>
            @endforeach
        </div>
    </div>
</section>
