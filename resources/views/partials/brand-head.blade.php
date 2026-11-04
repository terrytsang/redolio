{{-- Favicons, app icons and social-sharing tags (the sharing image is the light-background logo card). --}}
@php
    $shareTitle = trim(strip_tags($__env->yieldContent('title'))) ?: 'REDOLIO | AI Website Transformation Platform to Build Better Digital Experiences';
    $shareDesc = trim(strip_tags($__env->yieldContent('description'))) ?: 'REDOLIO reads your website, diagnoses what is holding it back and redesigns it — Let’s REDO it. Powered by Meta AI.';
    $shareImage = url('brand/og-image.png');
@endphp
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<meta name="theme-color" content="#7c3aed">
<meta name="description" content="{{ $shareDesc }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="REDOLIO">
<meta property="og:title" content="{{ $shareTitle }}">
<meta property="og:description" content="{{ $shareDesc }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ $shareImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="REDOLIO — the intelligent website transformation platform">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $shareTitle }}">
<meta name="twitter:description" content="{{ $shareDesc }}">
<meta name="twitter:image" content="{{ $shareImage }}">
