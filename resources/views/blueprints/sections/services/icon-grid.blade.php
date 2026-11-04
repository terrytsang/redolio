<section class="bp-section" id="services" data-section="{{ $id }}">
    <div class="bp-wrap">
        <div class="bp-head"><h2>{{ $c['heading'] }}</h2>@if (! empty($c['subheading']))<p class="bp-lead">{{ $c['subheading'] }}</p>@endif</div>
        <div class="bp-grid bp-grid--{{ min(max(count($c['items']), 2), 3) }}">
            @foreach ($c['items'] as $i)
                <article class="bp-card bp-tile">
                    @include('blueprints.partials.visual', ['image' => $i['image'] ?? null, 'class' => 'bp-visual--chip', 'seed' => $i['title']])
                    @if (! empty($i['meta']))<p class="bp-eyebrow" style="margin:1rem 0 .4rem">{{ $i['meta'] }}</p>@endif
                    <h3>{{ $i['title'] }}</h3><p style="margin-top:.5rem">{{ $i['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
