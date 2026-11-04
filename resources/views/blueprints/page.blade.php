<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($sampleBanner || ! empty($seo['noindex']))<meta name="robots" content="noindex">@endif
    <title>{{ $seo['title'] ?? ($title.' — '.$direction['name']) }}</title>
@if (! empty($seo['description']))
    <meta name="description" content="{{ $seo['description'] }}">
    <meta property="og:title" content="{{ $seo['title'] ?? $title }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:type" content="website">
@endif
@if (! empty($seo['json_ld']))
    <script type="application/ld+json">{!! json_encode($seo['json_ld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
    <style>
{!! $fontCss ?? '' !!}
{!! $tokenCss !!}
@include('blueprints.styles')
    </style>
{!! $widgetsHead ?? '' !!}
</head>
<body data-blueprint="{{ $blueprint['id'] }}" data-direction="{{ $direction['id'] }}">
@if ($sampleBanner)
    <div class="bp-sample-banner" role="note">Layout preview · {{ $blueprint['name'] }} × {{ $direction['name'] }} — sample content showing layout and art direction, not your website.</div>
@endif
<main>
{!! implode("\n", $sections) !!}
</main>
{!! $widgetsEnd ?? '' !!}
@if (! empty($widgetsScript))<script src="assets/redo-widgets.js" defer></script>@endif
</body>
</html>
