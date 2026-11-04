@php
    $url = ! empty($image['asset_id']) ? ($assetUrls[$image['asset_id']] ?? null) : null;
@endphp
@php $clear = $url && isset(($alphaAssets ?? [])[$image['asset_id']]); @endphp
<div class="bp-visual {{ $class ?? '' }}{{ $clear ? ' bp-visual--clear' : '' }}" @if (! empty($image['alt'])) role="img" aria-label="{{ $image['alt'] }}" @else aria-hidden="true" @endif>
    @if ($url)
        <img class="bp-visual__img" src="{{ $url }}" alt="" loading="lazy">
    @else
        {!! \App\Support\GenerativeArt::svg($artStyle ?? 'stripes', ($id ?? '').'|'.($seed ?? '')) !!}
    @endif
</div>
