@php $bg = ! empty($c['image']['asset_id']) ? ($assetUrls[$c['image']['asset_id']] ?? null) : null; @endphp
<div class="{{ $class }}" @if ($bg && ! empty($c['image']['alt'])) role="img" aria-label="{{ $c['image']['alt'] }}" @else aria-hidden="true" @endif>
    @if ($bg)<img class="bp-visual__img" src="{{ $bg }}" alt="" loading="eager">@else{!! \App\Support\GenerativeArt::svg($artStyle ?? 'waves', ($id ?? '').'|'.($seed ?? 'hero')) !!}@endif
</div>
