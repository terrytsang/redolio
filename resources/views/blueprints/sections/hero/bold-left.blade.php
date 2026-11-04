<section class="bp-hero bp-hero--bold bp-hero--bold-left" id="top" data-section="{{ $id }}">
    <div class="bp-wrap bp-bold__wrap">
        <div class="bp-bold__top">
            @if (! empty($c['eyebrow']))<p class="bp-eyebrow">{{ $c['eyebrow'] }}</p>@endif
            <h1 class="bp-bold__title">{{ $c['headline'] }}</h1>
        </div>
        <div class="bp-bold__bottom">
            @if (! empty($c['subheadline']))<p class="bp-bold__lead bp-lead">{{ $c['subheadline'] }}</p>@endif
            <div class="bp-actions bp-bold__actions">
                @include('blueprints.partials.btn', ['cta' => $c['primary_cta'], 'icon' => true])
                @include('blueprints.partials.btn', ['cta' => $c['secondary_cta'] ?? null, 'class' => 'bp-btn--ghost', 'icon' => true])
            </div>
        </div>
        @php $photo = ! empty($c['image']['asset_id']) && isset($assetUrls[$c['image']['asset_id']]); @endphp
        @if ($photo)@include('blueprints.partials.visual', ['image' => $c['image'], 'class' => 'bp-visual--strip', 'seed' => 'bold'])@endif
    </div>
</section>
