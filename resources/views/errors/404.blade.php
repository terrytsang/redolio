@extends('layouts.marketing')

@section('title', 'Page not found — REDOLIO')

@section('content')
    <style>
        @keyframes nf-float { 0%, 100% { transform: translateY(0) rotate(-1.5deg); } 50% { transform: translateY(-12px) rotate(1.5deg); } }
        @keyframes nf-search { 0%, 100% { transform: translate(0, 0); } 25% { transform: translate(14px, -6px); } 50% { transform: translate(-6px, 8px); } 75% { transform: translate(10px, 6px); } }
        @keyframes nf-blink { 0%, 92%, 100% { transform: scaleY(1); } 96% { transform: scaleY(.1); } }
        .nf-float { animation: nf-float 6s ease-in-out infinite; }
        .nf-search { animation: nf-search 7s ease-in-out infinite; transform-origin: center; }
        .nf-blink { animation: nf-blink 5s infinite; transform-origin: center; transform-box: fill-box; }
        @media (prefers-reduced-motion: reduce) { .nf-float, .nf-search, .nf-blink { animation: none; } }
    </style>

    <div class="relative min-h-screen overflow-hidden bg-canvas">
        <div class="page-glow"></div>

        <header class="relative mx-auto flex max-w-6xl items-center justify-between px-6 py-5 lg:px-10">
            <a href="{{ route('landing') }}" class="text-xl font-extrabold tracking-tight text-ink" aria-label="REDOLIO home"><x-brand /></a>
            <nav class="flex items-center gap-5 text-[13px] font-semibold">
                <a href="{{ route('pricing') }}" class="text-muted hover:text-ink">Pricing</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-full border border-line-strong px-4 py-2 text-ink hover:border-accent">My websites</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-full border border-line-strong px-4 py-2 text-ink hover:border-accent">Log in</a>
                @endauth
            </nav>
        </header>

        <main class="relative mx-auto flex max-w-6xl flex-col items-center gap-10 px-6 pb-20 pt-6 text-center lg:flex-row lg:gap-16 lg:px-10 lg:pt-12 lg:text-left">
            {{-- The lost web page --}}
            <div class="relative w-full max-w-[420px] shrink-0" aria-hidden="true">
                <svg viewBox="0 0 420 340" class="nf-float h-auto w-full drop-shadow-[0_24px_40px_rgba(109,40,217,.18)]" fill="none">
                    <rect x="20" y="30" width="380" height="270" rx="22" fill="var(--c-surface, #fff)" stroke="var(--c-line-strong, #d8d4cf)" stroke-width="3"/>
                    <path d="M20 52a22 22 0 0 1 22-22h336a22 22 0 0 1 22 22v28H20Z" fill="var(--c-surface-2, #f3f1ee)"/>
                    <circle cx="52" cy="55" r="7" fill="#ff6b6b"/><circle cx="76" cy="55" r="7" fill="#ffc145"/><circle cx="100" cy="55" r="7" fill="#3ccf91"/>
                    <rect x="128" y="45" width="230" height="20" rx="10" fill="var(--c-canvas, #faf9f8)"/>
                    <text x="142" y="60" font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="var(--c-muted, #8a8782)">redolio.com/…where-did-it-go?</text>
                    <text x="210" y="195" text-anchor="middle" font-family="Inter, sans-serif" font-weight="900" font-size="104" letter-spacing="-4" fill="url(#nf-grad)">404</text>
                    <g class="nf-blink"><ellipse cx="170" cy="236" rx="7" ry="9" fill="var(--c-ink, #17151a)"/><ellipse cx="250" cy="236" rx="7" ry="9" fill="var(--c-ink, #17151a)"/></g>
                    <path d="M180 266c10-10 50-10 60 0" stroke="var(--c-ink, #17151a)" stroke-width="5" stroke-linecap="round"/>
                    <g class="nf-search"><circle cx="330" cy="190" r="38" fill="rgba(124,58,237,.10)" stroke="#7c3aed" stroke-width="7"/><path d="M357 218l30 34" stroke="#7c3aed" stroke-width="10" stroke-linecap="round"/><path d="M314 176a22 22 0 0 1 20-10" stroke="#fff" stroke-width="5" stroke-linecap="round" opacity=".8"/></g>
                    <defs><linearGradient id="nf-grad" x1="90" y1="110" x2="330" y2="200" gradientUnits="userSpaceOnUse"><stop stop-color="#7c3aed"/><stop offset=".55" stop-color="#d946ef"/><stop offset="1" stop-color="#fb923c"/></linearGradient></defs>
                </svg>
                <span class="absolute -right-2 -top-3 rotate-6 rounded-full bg-accent px-3 py-1 text-[11px] font-extrabold tracking-wide text-white shadow-lg">NOT FOUND</span>
            </div>

            <div class="max-w-xl">
                <p class="text-xs font-bold tracking-[0.2em] text-accent-ink">ERROR 404 · PAGE NOT FOUND</p>
                <h1 class="mt-3 text-[38px] font-black leading-[1.05] tracking-tight text-ink sm:text-[54px]">Well, this is <span class="grad-text">awkward.</span></h1>
                <p class="mt-4 text-[17px] leading-relaxed text-muted">We looked everywhere — under the couch, behind the CSS, even in the footer. This page isn’t here. It may have moved, never existed, or it’s someone’s private website analysis (we keep those locked up, and we’ll do the same for yours).</p>
                <p id="nf-quip" class="mt-4 min-h-[3rem] text-[15px] font-semibold italic leading-snug text-body">Our AI has analysed thousands of websites. This page is not one of them.</p>

                <div class="mt-8 rounded-2xl border border-line bg-surface p-5 text-left shadow-sm sm:p-6">
                    <p class="text-[15px] font-extrabold text-ink">While you’re here — let’s REDO <em class="not-italic text-accent-ink">your</em> website instead.</p>
                    <p class="mt-1 text-[13.5px] text-muted">Paste your address. In about three minutes REDOLIO reads it, finds what’s holding it back and shows you a brand-new version, side by side.</p>
                    <form method="GET" action="{{ route('landing') }}" class="mt-4 flex flex-col gap-2.5 sm:flex-row">
                        <input type="text" name="url" inputmode="url" autocomplete="url" placeholder="https://yourwebsite.com" aria-label="Your website address"
                               class="min-w-0 flex-1 rounded-full border border-line-strong bg-surface-2 px-5 py-3.5 text-sm text-ink placeholder:text-faint focus:border-accent focus:outline-none">
                        <button type="submit" class="shrink-0 rounded-full bg-accent px-7 py-3.5 text-sm font-bold text-white hover:opacity-90">Let’s REDO it →</button>
                    </form>
                    <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-[12.5px] text-faint"><li>✓ Free to try</li><li>✓ No credit card</li><li>✓ Your site stays untouched</li></ul>
                </div>

                <p class="mt-6 text-[13.5px] text-muted">
                    <a href="{{ route('landing') }}" class="font-semibold text-accent-ink hover:text-ink">← Back to the homepage</a>
                    @auth<span class="mx-2 text-faint">·</span><a href="{{ route('dashboard') }}" class="font-semibold text-accent-ink hover:text-ink">My websites</a>@endauth
                    <span class="mx-2 text-faint">·</span><a href="mailto:{{ config('redo.support_email') }}" class="font-semibold text-muted hover:text-ink">Think this is our mistake? Tell us</a>
                </p>
            </div>
        </main>
    </div>

    <script>
        (function () {
            var quips = [
                'Our AI has analysed thousands of websites. This page is not one of them.',
                'Error 404: page not found. Error 200: your website is about to look amazing.',
                'This page went out for coffee and didn’t say when it’s back.',
                'We checked the URL twice. Then we blamed the intern. We don’t have an intern.',
                'Plot twist: the page you wanted is private — and so is everyone else’s analysis.',
                'Good news: at least your own website exists. Let’s make it shine.',
                'It’s not you, it’s the link. (Okay, maybe it’s a little you.)'
            ];
            var el = document.getElementById('nf-quip');
            if (el) el.textContent = quips[Math.floor(Math.random() * quips.length)];
        })();
    </script>
@endsection
