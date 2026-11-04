<header class="bp-nav" data-section="{{ $id }}">
    <div class="bp-wrap bp-nav__row">
        @php $logoUrl = ! empty($c['logo']['asset_id']) ? ($assetUrls[$c['logo']['asset_id']] ?? null) : null; @endphp
        <a class="bp-brand" href="#top" @if ($logoUrl && ! empty($c['logo_only'])) aria-label="{{ $c['brand'] }}" @endif>
            @if ($logoUrl)<img class="bp-brand__logo" src="{{ $logoUrl }}" alt="{{ ! empty($c['logo_only']) ? ($c['logo']['alt'] ?? $c['brand']) : '' }}">@endif
            @if (! $logoUrl || empty($c['logo_only']))<span>{{ $c['brand'] }}</span>@endif
        </a>
        <nav class="bp-nav__links" aria-label="Primary">
            @foreach ($c['links'] as $l)<a href="{{ $l['href'] }}">{{ $l['label'] }}</a>@endforeach
            @include('blueprints.partials.btn', ['cta' => $c['cta'] ?? null])
        </nav>
        <details>
            <summary aria-label="Open menu">Menu</summary>
            <nav class="bp-nav__menu" aria-label="Mobile">
                @foreach ($c['links'] as $l)<a href="{{ $l['href'] }}">{{ $l['label'] }}</a>@endforeach
                @include('blueprints.partials.btn', ['cta' => $c['cta'] ?? null])
            </nav>
        </details>
    </div>
</header>
