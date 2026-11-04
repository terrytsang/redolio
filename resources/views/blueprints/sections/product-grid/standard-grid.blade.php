<section class="bp-section" id="products" data-section="{{ $id }}">
    <div class="bp-wrap">
        <div class="bp-head"><h2>{{ $c['heading'] }}</h2>@if (! empty($c['subheading']))<p class="bp-lead">{{ $c['subheading'] }}</p>@endif</div>
        <div class="bp-grid bp-grid--4 bp-products">
            @foreach ($c['items'] as $p)
                <article class="bp-product">
                    <div class="bp-product__media">
                        @include('blueprints.partials.visual', ['image' => $p['image'] ?? null, 'class' => 'bp-visual--square', 'seed' => $p['title']])
                        @if (! empty($p['badge']))<span class="bp-badge">{{ $p['badge'] }}</span>@endif
                    </div>
                    <h3>{{ $p['title'] }}</h3>
                    @if (! empty($p['description']))<p class="bp-product__d">{{ $p['description'] }}</p>@endif
                    @if (! empty($p['price']))<p class="bp-product__p">{{ $p['price'] }}</p>@endif
                    @include('blueprints.partials.btn', ['cta' => $p['cta'] ?? null, 'class' => 'bp-btn--ghost'])
                </article>
            @endforeach
        </div>
    </div>
</section>
