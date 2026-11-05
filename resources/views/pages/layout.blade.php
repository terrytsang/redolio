@extends('layouts.marketing')

@section('title', $pageTitle.' — REDOLIO')
@section('description', $pageIntro ?? '')

@section('content')
    @include('partials.site-header')

    <main class="bg-canvas px-6 pb-20 pt-12 lg:px-16 lg:pt-16">
        <div class="mx-auto max-w-3xl">
            @isset($pageEyebrow)<p class="text-xs font-bold tracking-[0.2em] text-accent-ink">{{ strtoupper($pageEyebrow) }}</p>@endisset
            <h1 class="mt-2 text-[34px] font-black leading-[1.1] tracking-tight text-ink sm:text-[44px]">{{ $pageTitle }}</h1>
            @isset($pageIntro)<p class="mt-4 text-[18px] leading-relaxed text-muted">{{ $pageIntro }}</p>@endisset
            @isset($pageUpdated)<p class="mt-3 text-[13px] text-faint">Last updated {{ $pageUpdated }}</p>@endisset

            <div class="page-prose mt-8">
                @yield('page')
            </div>

            <div class="mt-14 rounded-2xl border border-line bg-surface p-6 text-center">
                <p class="text-[17px] font-extrabold text-ink">Ready to see your own website, redone?</p>
                <p class="mt-1 text-[14px] text-muted">Paste your address and REDOLIO shows you a new version side by side. Free to try.</p>
                <a href="{{ route('landing') }}#analyze" class="btn btn-primary mt-4 px-7 py-3">Let’s REDO it →</a>
            </div>
        </div>
    </main>

    @include('partials.site-footer')
@endsection
