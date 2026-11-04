{{-- The REDOLIO logo. Light-background artwork by default; dark mode swaps to the dark-background artwork (see .brand-logo in app.css).
     brand/logo-light.png and logo-dark.png are the supplied redolio-logo-*bg.png files with their empty margins trimmed so the logo fills its height. --}}
<span {{ $attributes->merge(['class' => 'brand-logo']) }}>
    <img class="brand-logo__light" src="{{ asset('brand/logo-light.png') }}" alt="REDOLIO">
    <img class="brand-logo__dark" src="{{ asset('brand/logo-dark.png') }}" alt="" aria-hidden="true">
</span>
