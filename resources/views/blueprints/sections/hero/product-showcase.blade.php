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
        @php $photo = ! empty($c['image']['asset_id']) && isset($assetUrls[$c['image']['asset_id']]); @endphp
        @if ($photo)
        @include('blueprints.partials.visual', ['image' => $c['image'], 'class' => 'bp-visual--tall'])
        @else
        <div class="bp-mock" @if (! empty($c['image']['alt'])) role="img" aria-label="{{ $c['image']['alt'] }}" @else aria-hidden="true" @endif>
            <div class="bp-mock__bar"><i></i><i></i><i></i></div>
            <div class="bp-mock__body">
                <div class="bp-mock__side"><i></i><i></i><i></i><i></i></div>
                <div class="bp-mock__main"><i style="width:60%"></i><b></b><i></i><i style="width:80%"></i></div>
            </div>
        </div>
        @endif
    </div>
</section>
