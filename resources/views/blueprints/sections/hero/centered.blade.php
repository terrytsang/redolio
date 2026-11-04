<section class="bp-hero bp-centered" id="top" data-section="{{ $id }}">
    <div class="bp-centered__glow" aria-hidden="true">{!! \App\Support\GenerativeArt::svg($artStyle ?? 'orbs', ($id ?? '').'|glow') !!}</div>
    <div class="bp-wrap bp-center">
        @if (! empty($c['eyebrow']))<p class="bp-pill">{{ $c['eyebrow'] }}</p>@endif
        <h1 class="bp-centered__title">{{ $c['headline'] }}</h1>
        @if (! empty($c['subheadline']))<p class="bp-lead" style="margin-left:auto;margin-right:auto">{{ $c['subheadline'] }}</p>@endif
        <div class="bp-actions" style="justify-content:center">
            @include('blueprints.partials.btn', ['cta' => $c['primary_cta']])
            @include('blueprints.partials.btn', ['cta' => $c['secondary_cta'] ?? null, 'class' => 'bp-btn--ghost'])
        </div>
        @php $photo = ! empty($c['image']['asset_id']) && isset($assetUrls[$c['image']['asset_id']]); @endphp
        @if ($photo)
        @include('blueprints.partials.visual', ['image' => $c['image'], 'class' => 'bp-visual--tall bp-centered__mock'])
        @else
        <div class="bp-mock bp-centered__mock" @if (! empty($c['image']['alt'])) role="img" aria-label="{{ $c['image']['alt'] }}" @else aria-hidden="true" @endif>
            <div class="bp-mock__bar"><i></i><i></i><i></i></div>
            <div class="bp-mock__art">{!! \App\Support\GenerativeArt::svg($artStyle ?? 'chart', ($id ?? '').'|mock') !!}</div>
        </div>
        @endif
    </div>
</section>
