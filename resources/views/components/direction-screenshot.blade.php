@props(['direction'])

@php
    // The card image is a REAL screenshot of the direction's showcase website, rendered by the
    // production blueprint renderer in a real browser (php artisan directions:capture). Directions
    // without a capture (e.g. admin-created ones) fall back to the template preview.
    $shot = "direction-previews/{$direction->direction_key}/hero.png";
    $hasShot = \Illuminate\Support\Facades\Storage::disk('public')->exists($shot);
@endphp

@if ($hasShot)
    <figure class="m-0 aspect-[16/10] w-full overflow-hidden rounded-xl border border-line bg-surface">
        <img
            src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($shot) }}?v={{ \Illuminate\Support\Facades\Storage::disk('public')->lastModified($shot) }}"
            alt="Screenshot of the {{ $direction->name }} direction applied to an example website"
            class="h-full w-full object-cover object-top"
            loading="lazy"
            width="1440" height="900"
        >
    </figure>
@else
    <x-direction-hero-preview :direction="$direction" />
@endif
