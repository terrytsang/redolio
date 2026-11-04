<section class="bp-section" id="services" data-section="{{ $id }}">
    <div class="bp-wrap">
        <div class="bp-head"><h2>{{ $c['heading'] }}</h2>@if (! empty($c['subheading']))<p class="bp-lead">{{ $c['subheading'] }}</p>@endif</div>
        <div class="bp-grid bp-grid--{{ min(max(count($c['items']), 2), 3) }}">
            @foreach ($c['items'] as $i)
                <article class="bp-card" style="padding:0;overflow:hidden">
                    @include('blueprints.partials.visual', ['image' => $i['image'] ?? null, 'class' => ''])
                    <div style="padding:1.5rem">
                        @if (! empty($i['meta']))<p class="bp-eyebrow" style="margin-bottom:.5rem">{{ $i['meta'] }}</p>@endif
                        <h3>{{ $i['title'] }}</h3><p style="margin-top:.5rem">{{ $i['description'] }}</p>
                        @if (! empty($i['cta']))<div class="bp-actions" style="margin-top:1.1rem">@include('blueprints.partials.btn', ['cta' => $i['cta'], 'class' => 'bp-btn--ghost'])</div>@endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
