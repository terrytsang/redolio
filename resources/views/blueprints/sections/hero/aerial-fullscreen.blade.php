<section class="bp-hero--aerial" id="top" data-section="{{ $id }}">
    @php $bg = ! empty($c['image']['asset_id']) ? ($assetUrls[$c['image']['asset_id']] ?? null) : null; @endphp
    <div class="bp-hero__art" @if ($bg) role="img" aria-label="{{ $c['image']['alt'] ?? '' }}" @else aria-hidden="true" @endif>
        @if ($bg)<img class="bp-visual__img" src="{{ $bg }}" alt="" loading="eager">@else{!! \App\Support\GenerativeArt::svg($artStyle ?? 'waves', ($id ?? '').'|aerial') !!}@endif
    </div>
    <div class="bp-wrap">
        @if (! empty($c['eyebrow']))<p class="bp-eyebrow">{{ $c['eyebrow'] }}</p>@endif
        <h1>{{ $c['headline'] }}</h1>
        @if (! empty($c['subheadline']))<p class="bp-lead">{{ $c['subheadline'] }}</p>@endif
        <div class="bp-actions">
            @include('blueprints.partials.btn', ['cta' => $c['primary_cta'], 'class' => 'bp-btn--light'])
            @include('blueprints.partials.btn', ['cta' => $c['secondary_cta'] ?? null, 'class' => 'bp-btn--light-ghost'])
        </div>
    </div>
</section>
