<!DOCTYPE html>
<html lang="en" class="h-full" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'REDOLIO | AI Website Transformation Platform to Build Better Digital Experiences')</title>
    @include('partials.brand-head')
    @include('partials.theme-head')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-canvas text-ink antialiased">
    @yield('content')

    <div class="fixed bottom-5 right-5 z-50"><x-theme-toggle /></div>
</body>
</html>
