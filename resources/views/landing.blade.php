@extends('layouts.marketing')

@section('title', 'REDOLIO | AI Website Transformation Platform to Build Better Digital Experiences')

@section('content')
    {{-- Nav --}}
    <header class="border-b border-line bg-canvas">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-16">
            <a href="{{ route('landing') }}" class="text-xl font-extrabold tracking-tight text-ink"><x-brand /></a>

            <nav class="hidden items-center gap-8 lg:flex">
                <a href="#features" class="text-sm font-medium text-muted hover:text-ink">Features</a>
                <a href="#how-it-works" class="text-sm font-medium text-muted hover:text-ink">How it works</a>
                <a href="{{ route('pricing') }}" class="text-sm font-medium text-muted hover:text-ink">Pricing</a>
                <a href="{{ route('directions.index') }}" class="text-sm font-medium text-muted hover:text-ink">Directions</a>
            </nav>

            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-dark">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="hidden text-sm font-medium text-muted hover:text-ink sm:inline">Sign in</a>
                    <a href="{{ route('register') }}" class="btn btn-dark">
                        Start free
                    </a>
                @endauth
            </div>
        </div>
    </header>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-canvas" id="analyze">
        <div class="page-glow" style="height:640px"></div>
        <div class="mx-auto grid max-w-7xl gap-12 px-6 py-16 lg:grid-cols-2 lg:gap-10 lg:px-16 lg:py-24">
            <div class="flex flex-col gap-7">
                <div class="inline-flex w-fit items-center gap-1.5 rounded-full border border-line-strong bg-surface-2 px-3.5 py-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                    <span class="text-xs font-medium tracking-wide text-accent-ink">The intelligent website transformation platform · Powered by Meta AI</span>
                </div>

                <h1 class="max-w-xl text-5xl font-extrabold leading-[1.08] tracking-tight text-ink sm:text-6xl">
                    Your website can do better.<br><span class="grad-text">Let's REDO it.</span>
                </h1>

                <p class="max-w-md text-base leading-relaxed text-faint">
                    REDOLIO analyses your website, understands your business, finds what's holding it back, and keeps
                    redesigning it — automatically, on a schedule. Built for agencies, marketing consultants, and
                    business owners managing one site or fifty.
                </p>

                <form method="POST" action="{{ route('analyses.store') }}" class="flex max-w-xl flex-col items-stretch gap-2.5 sm:flex-row sm:items-center">
                    @csrf
                    <label for="url" class="sr-only">Website URL</label>
                    <input
                        id="url"
                        name="url"
                        type="text"
                        required
                        placeholder="https://yourwebsite.com"
                        value="{{ old('url', request('url')) }}"
                        class="w-full min-w-0 rounded-full border border-line-strong bg-surface-2 px-5 py-3.5 text-sm text-ink placeholder:text-faint focus:border-accent focus:outline-none sm:w-72"
                    >
                    <button type="submit" class="shrink-0 rounded-full bg-accent px-7 py-3.5 text-sm font-bold text-white hover:bg-accent">
                        Analyse →
                    </button>
                </form>
                @error('url')
                    <p class="text-sm text-bad">{{ $message }}</p>
                @enderror
                <ul class="flex flex-wrap gap-x-5 gap-y-1 text-[13px] text-muted" aria-label="How it works">
                    <li>① Paste your address</li><li>② Create a free account in seconds</li><li>③ Get your redesigned website</li>
                </ul>
            </div>

            {{-- Hero Right: before/after preview collage --}}
            <div class="relative hidden min-h-[480px] lg:block">
                <div class="absolute left-0 top-4 w-[340px] overflow-hidden rounded-2xl border border-line-strong bg-surface-2">
                    <div class="flex items-center justify-between bg-surface px-4 py-3">
                        <span class="text-[11px] text-faint">brightsmile-dental.com</span>
                    </div>
                    <div class="bg-[#1A1A1A] px-3 py-1">
                        <span class="text-[10px] font-bold tracking-wider text-muted">BEFORE</span>
                    </div>
                    <div class="flex flex-col gap-2 bg-surface-2 px-4 py-5">
                        <p class="text-base font-bold text-ink">Bright Smile Dental</p>
                        <p class="text-xs text-[#666666]">Dental care for all ages.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 bg-[#F5F5F5] px-4 py-3">
                        <span class="rounded bg-line px-2.5 py-1 text-[10px] text-body">Cleaning</span>
                        <span class="rounded bg-line px-2.5 py-1 text-[10px] text-body">Whitening</span>
                        <span class="rounded bg-line px-2.5 py-1 text-[10px] text-body">Implants</span>
                    </div>
                </div>

                <div class="absolute left-[220px] top-[220px] w-[260px] overflow-hidden rounded-2xl border border-accent/40 bg-canvas">
                    <div class="flex items-center gap-2 bg-accent px-3.5 py-2">
                        <span class="text-[10px] font-bold tracking-wider text-ink">✦ REDO PREVIEW</span>
                    </div>
                    <div class="flex flex-col gap-2.5 bg-surface px-4 py-4">
                        <p class="max-w-[200px] text-sm font-extrabold leading-snug text-ink">The Dentist You'll Actually Look Forward To.</p>
                        <span class="w-fit rounded-full bg-accent px-4 py-2 text-[11px] font-semibold text-white">Book a free consultation</span>
                    </div>
                </div>

                <div class="absolute left-0 top-[400px] w-[200px] rounded-2xl border border-line-strong bg-surface px-4 py-3.5">
                    <p class="text-[9px] font-bold tracking-widest text-accent-ink">WEBSITE DNA</p>
                    <p class="mt-2 text-sm font-semibold text-ink">🧬 Local Dental · Lead gen</p>
                    <p class="mt-1 text-[11px] text-faint">52 evidence items extracted</p>
                </div>

                <div class="absolute left-[300px] top-[450px] flex items-center gap-2.5 rounded-xl border border-bad/40 bg-bad-soft px-4 py-3">
                    <span class="h-2 w-2 rounded-full bg-bad"></span>
                    <div>
                        <p class="text-[13px] font-bold text-ink">5 issues found</p>
                        <p class="text-[11px] text-faint">High severity · Evidence-backed</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Reveal: outdated site vs REDO-designed site, side by side --}}
    <section id="reveal" class="border-y border-line bg-surface px-6 py-16 lg:px-16 lg:py-20">
        <div class="mx-auto max-w-6xl">
            <div class="mx-auto mb-10 max-w-2xl text-center">
                <p class="text-xs font-bold tracking-[0.2em] text-accent-ink">THE REVEAL</p>
                <h2 class="mt-3 text-3xl font-extrabold leading-tight tracking-tight text-ink sm:text-4xl">See what your website could become.</h2>
                <p class="mt-3 text-base leading-relaxed text-faint">Same dental practice, same content — before and after REDOLIO.</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                {{-- Before --}}
                <figure class="flex flex-col overflow-hidden rounded-2xl border border-line-strong bg-canvas">
                    <figcaption class="flex items-center justify-between border-b border-line px-4 py-2.5">
                        <span class="rounded-full bg-bad-soft px-3 py-1 text-[10px] font-bold tracking-widest text-bad">BEFORE · OUTDATED</span>
                        <span class="text-[11px] text-faint">brightsmile-dental.com</span>
                    </figcaption>
                    <div class="flex flex-1 flex-col bg-[#EDEDED] text-[#222]" style="font-family: 'Times New Roman', Georgia, serif;" aria-label="An outdated dental website">
                        <div class="bg-[#2D4A8A] px-4 py-2 text-center text-xs font-bold text-white">Welcome to Bright Smile Dental!!!</div>
                        <div class="flex flex-wrap justify-center gap-x-3 gap-y-1 border-b-2 border-[#999] bg-[#D9D9D9] px-3 py-1.5 text-[11px] text-[#0000EE] underline">
                            <span>Home</span><span>About Us</span><span>Services</span><span>Contact</span><span>Links</span>
                        </div>
                        <div class="space-y-3 px-5 py-5">
                            <p class="text-center text-lg font-bold" style="color:#B00">Bright Smile Dental</p>
                            <p class="text-center text-[12px]">Dental care for all ages. Call us today! We are open Mon–Fri.</p>
                            <div class="mx-auto h-24 w-4/5 border-2 border-[#888] bg-[#CFCFCF] text-center text-[10px] leading-[6rem] text-[#666]">[ small photo ]</div>
                            <ul class="list-disc pl-6 text-[12px]"><li>Cleaning</li><li>Whitening</li><li>Implants</li><li>Fillings</li></ul>
                            <p class="text-center text-[11px] text-[#0000EE] underline">Click here to contact us</p>
                            <p class="text-center text-[10px] text-[#777]">Best viewed in Internet Explorer · Visitors: 004127</p>
                        </div>
                    </div>
                </figure>

                {{-- After --}}
                <figure class="flex flex-col overflow-hidden rounded-2xl border border-accent/50 bg-canvas shadow-[0_0_40px_-12px] shadow-accent/40">
                    <figcaption class="flex items-center justify-between border-b border-line px-4 py-2.5">
                        <span class="rounded-full bg-accent px-3 py-1 text-[10px] font-bold tracking-widest text-white">✦ AFTER · REDO PREVIEW</span>
                        <span class="text-[11px] text-faint">brightsmile-dental.com</span>
                    </figcaption>
                    <div class="flex flex-1 flex-col bg-surface-2" aria-label="The same dental website redesigned by REDOLIO">
                        <div class="flex items-center justify-between px-5 py-3">
                            <span class="text-sm font-extrabold text-ink">Bright Smile</span>
                            <span class="hidden gap-4 text-[11px] text-muted xl:flex"><span>Services</span><span>Team</span><span>Reviews</span></span>
                            <span class="rounded-full bg-accent px-3 py-1 text-[10px] font-semibold text-white">Book now</span>
                        </div>
                        <div class="space-y-3 px-5 pb-4 pt-3">
                            <p class="text-[1.35rem] font-extrabold leading-tight text-ink [text-wrap:balance] sm:text-2xl">The Dentist You’ll Actually Look Forward To.</p>
                            <p class="text-[12px] leading-relaxed text-muted">Gentle, modern care for the whole family — with same-week appointments.</p>
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-accent px-4 py-2 text-[11px] font-semibold text-white">Book a free consultation</span>
                                <span class="rounded-full border border-line-strong px-4 py-2 text-[11px] font-semibold text-ink">See our services</span>
                            </div>
                        </div>
                        <div class="relative mx-5 flex min-h-28 flex-1 items-center justify-between gap-3 overflow-hidden rounded-xl bg-gradient-to-br from-accent/30 via-accent/10 to-transparent px-5 py-3" aria-hidden="true">
                            <div class="relative z-10 max-w-[55%]">
                                <p class="text-[10px] font-bold tracking-widest text-accent-ink">GENTLE FAMILY DENTISTRY</p>
                                <p class="mt-1 text-sm font-extrabold leading-snug text-ink">Healthy, confident smiles start here.</p>
                            </div>
                            <svg viewBox="0 0 120 110" class="h-24 w-auto shrink-0" fill="none">
                                <path d="M60 14c-11-8-31-6-37 10-5 14 2 26 6 38 4 13 5 33 14 33 8 0 7-16 12-26 5 10 4 26 12 26 9 0 10-20 14-33 4-12 11-24 6-38-6-16-26-18-37-10Z" fill="#fff" stroke="currentColor" stroke-width="3" stroke-linejoin="round" class="text-accent"/>
                                <path d="M42 34c3-5 9-7 14-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" class="text-accent/40"/>
                                <path d="M96 12l2.6 7.4L106 22l-7.4 2.6L96 32l-2.6-7.4L86 22l7.4-2.6Z" fill="currentColor" class="text-accent"/>
                                <path d="M14 60l1.8 5.2L21 67l-5.2 1.8L14 74l-1.8-5.2L7 67l5.2-1.8Z" fill="currentColor" class="text-accent/70"/>
                                <path d="M104 70l1.4 4 4 1.4-4 1.4-1.4 4-1.4-4-4-1.4 4-1.4Z" fill="currentColor" class="text-accent/50"/>
                            </svg>
                        </div>
                        <div class="grid grid-cols-3 gap-2 px-5 py-4">
                            @foreach (['Cleaning', 'Whitening', 'Implants'] as $svc)
                                <div class="rounded-lg border border-line bg-surface px-2 py-2.5 text-center text-[11px] font-semibold text-ink">{{ $svc }}</div>
                            @endforeach
                        </div>
                        <p class="px-5 pb-4 text-[11px] text-faint">★★★★★ Rated 4.9 by 1,200+ patients</p>
                    </div>
                </figure>
            </div>

            <p class="mt-6 text-center text-sm text-faint">An illustrative example. Paste your own address above to see yours.</p>
        </div>
    </section>

    {{-- Feature 1: Website DNA --}}
    <section id="features" class="bg-canvas px-6 py-20 lg:px-16 lg:py-28">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-16 lg:flex-row">
            <div class="flex max-w-lg flex-col gap-7">
                <p class="text-xs font-bold tracking-[0.2em] text-accent-ink">WEBSITE DNA</p>
                <h2 class="text-4xl font-extrabold leading-[1.1] tracking-tight text-ink sm:text-5xl">
                    Every website has a story. We help you understand it.
                </h2>
                <p class="text-base leading-relaxed text-faint">
                    REDOLIO looks beyond colours and layouts. It analyses your website to understand what your business does,
                    who you serve, what makes you different, and what your website needs to achieve. We turn your website
                    into a clear business profile — built from what your website actually tells us.
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach (['Business Identity', 'Target Audience', 'Value Proposition', 'Brand Voice', 'Website Goals'] as $tag)
                        <span class="rounded-full border border-line-strong bg-surface-2 px-3.5 py-1.5 text-xs text-accent-ink">{{ $tag }}</span>
                    @endforeach
                </div>
                <a href="#" class="inline-flex items-center gap-1.5 text-sm font-semibold text-accent-ink hover:text-accent-ink">
                    See how Website DNA works →
                </a>
            </div>

            <div class="w-full max-w-xl overflow-hidden rounded-[20px] border border-line bg-surface">
                <div class="flex items-center justify-between border-b border-line bg-surface-2 px-5 py-4">
                    <span class="text-[13px] font-bold text-ink">Website DNA</span>
                    <span class="rounded-full bg-ok-soft px-2.5 py-1 text-[11px] font-semibold text-ok">✓ 52 evidence items</span>
                </div>
                @foreach ([
                    ['Business', 'Bright Smile Dental — Local dental practice'],
                    ['Industry', 'Dental / Healthcare'],
                    ['Goal', 'Lead generation · Appointment booking'],
                    ['Audience', 'Adult patients · Families · Elderly'],
                    ['Tone', 'Professional · Warm · Trustworthy'],
                    ['Value prop', "Family dentist you'll actually look forward to"],
                ] as [$label, $value])
                    <div class="flex gap-4 border-b border-line px-5 py-3.5 last:border-b-0">
                        <span class="w-20 shrink-0 text-xs font-semibold text-faint">{{ $label }}</span>
                        <span class="text-[13px] leading-relaxed text-body">{{ $value }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Feature 2: Diagnosis --}}
    <section id="how-it-works" class="bg-surface-2 px-6 py-20 lg:px-16 lg:py-28">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-16 lg:flex-row">
            <div class="order-2 flex w-full flex-col gap-2.5 lg:order-1">
                <div class="flex items-center justify-between rounded-2xl border border-line bg-surface px-5 py-3.5">
                    <span class="text-[13px] font-bold text-ink">Website Diagnosis</span>
                    <span class="rounded-full bg-bad-soft px-3 py-1 text-[11px] font-semibold text-bad">5 issues · All evidence-backed</span>
                </div>
                @foreach ([
                    ['HIGH', 'text-bad bg-bad-soft', 'No clear value proposition above the fold', 'EV-001'],
                    ['HIGH', 'text-bad bg-bad-soft', 'Primary CTA buried below the fold', 'EV-002'],
                    ['HIGH', 'text-warn bg-bad-soft', 'No trust signals visible', 'EV-003'],
                    ['MED', 'text-warn bg-warn-soft', 'Service descriptions too brief', 'EV-004'],
                    ['LOW', 'text-accent-ink bg-accent-soft', 'Navigation labels are too generic', 'EV-005'],
                ] as [$severity, $badgeClass, $issue, $evId])
                    <div class="flex items-center gap-3.5 rounded-xl border border-line bg-surface px-[18px] py-3.5">
                        <span class="shrink-0 rounded-md px-2 py-1 text-[9px] font-extrabold tracking-wide {{ $badgeClass }}">{{ $severity }}</span>
                        <span class="flex-1 text-[13px] font-semibold text-ink">{{ $issue }}</span>
                        <span class="shrink-0 rounded-full bg-surface-2 px-2.5 py-1 text-[10px] text-accent-ink">{{ $evId }}</span>
                    </div>
                @endforeach
            </div>

            <div class="order-1 flex max-w-md flex-col gap-7 lg:order-2">
                <p class="text-xs font-bold tracking-[0.2em] text-accent-ink">WEBSITE DIAGNOSIS</p>
                <h2 class="text-4xl font-extrabold leading-[1.1] tracking-tight text-ink sm:text-5xl">
                    Know what's wrong. Know why it matters.
                </h2>
                <p class="text-base leading-relaxed text-faint">
                    REDOLIO identifies the issues that may be stopping your website from doing its job — from unclear
                    messaging to weak calls to action and missing trust signals. Every finding comes with evidence from
                    your actual website. No random AI suggestions. No guesswork.
                </p>
                <a href="#" class="inline-flex items-center gap-1.5 text-sm font-semibold text-accent-ink hover:text-accent-ink">
                    See a real diagnosis →
                </a>
            </div>
        </div>
    </section>

    {{-- Feature 3: Before/After --}}
    <section class="bg-canvas px-6 py-20 lg:px-16 lg:py-28">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-12">
            <div class="flex max-w-2xl flex-col items-center gap-3.5 text-center">
                <p class="text-xs font-bold tracking-[0.2em] text-accent-ink">REDO PREVIEW</p>
                <h2 class="text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">Before. After. Side by side.</h2>
                <p class="text-base leading-relaxed text-faint">
                    See the difference before making any changes. REDOLIO turns its redesign recommendations into a working
                    website preview using a structured design system. Compare the original with the proposed redesign,
                    review the improvements, and decide what should change.
                </p>
            </div>

            <div class="grid w-full overflow-hidden rounded-[20px] border border-line bg-surface sm:grid-cols-2">
                <div class="flex flex-col bg-[#F8F8F8]">
                    <div class="flex items-center gap-2 bg-[#1A1A1A] px-4 py-2">
                        <span class="text-[10px] font-bold tracking-widest text-ink">ORIGINAL WEBSITE</span>
                        <span class="text-[10px] text-muted">brightsmile-dental.com</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-[#E5E5E5] bg-surface px-5 py-3.5">
                        <span class="text-[13px] font-bold text-ink">Bright Smile Dental</span>
                    </div>
                    <div class="flex flex-col gap-3 bg-surface px-7 py-10">
                        <p class="text-[22px] font-extrabold text-ink">Bright Smile Dental</p>
                        <p class="text-[13px] text-[#666666]">Dental care for families and adults.</p>
                    </div>
                    <div class="flex gap-2 bg-[#F5F5F5] px-7 py-4">
                        <span class="rounded bg-line px-3 py-1.5 text-[11px] text-body">Check-ups</span>
                        <span class="rounded bg-line px-3 py-1.5 text-[11px] text-body">Whitening</span>
                        <span class="rounded bg-line px-3 py-1.5 text-[11px] text-body">Implants</span>
                    </div>
                </div>

                <div class="flex flex-col bg-canvas">
                    <div class="flex items-center gap-2 bg-accent px-4 py-2">
                        <span class="text-[10px] font-bold tracking-widest text-ink">REDO PREVIEW</span>
                        <span class="text-[10px] text-accent-ink">Safe · Evidence-driven · No AI code executed</span>
                    </div>
                    <div class="flex items-center justify-between bg-surface px-5 py-3.5">
                        <span class="text-[13px] font-extrabold text-ink">Bright Smile Dental</span>
                        <span class="rounded-full bg-accent px-4 py-1.5 text-[11px] font-semibold text-white">Book appointment</span>
                    </div>
                    <div class="flex flex-col gap-3 px-7 py-10">
                        <span class="w-fit rounded-full bg-surface-2 px-3 py-1 text-[11px] text-accent-ink">Certified &amp; Insured</span>
                        <p class="max-w-[280px] text-xl font-extrabold leading-tight text-ink">The Dentist You'll Actually Look Forward To.</p>
                        <span class="w-fit rounded-full bg-accent px-5 py-2.5 text-[13px] font-semibold text-white">Book a free consultation</span>
                    </div>
                </div>
            </div>

            <div class="grid w-full gap-4 sm:grid-cols-5">
                @foreach ([
                    ['Addressed', 'text-ok', 'bg-ok-soft', 'Value proposition now visible'],
                    ['Addressed', 'text-ok', 'bg-ok-soft', 'Clear CTA introduced'],
                    ['Partial', 'text-warn', 'bg-warn-soft', 'Trust signals improved'],
                    ['Partial', 'text-warn', 'bg-warn-soft', 'Service clarity improved'],
                    ['Pending', 'text-body', 'bg-surface-2', 'Navigation refinements needed'],
                ] as [$status, $statusClass, $bgClass, $label])
                    <div class="flex flex-col gap-1.5 rounded-xl border border-line {{ $bgClass }} px-4 py-3.5">
                        <span class="text-[10px] font-bold tracking-wide {{ $statusClass }}">{{ $status }}</span>
                        <span class="text-[11px] leading-relaxed text-muted">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Meta AI Section --}}
    <section class="px-6 py-20 lg:px-16 lg:py-28" style="background: linear-gradient(135deg, var(--c-accent-soft), var(--c-canvas));">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-16 lg:flex-row">
            <div class="flex max-w-lg flex-col gap-7">
                <div class="inline-flex w-fit items-center gap-2 rounded-full border border-accent/40 bg-accent-soft px-3.5 py-1.5">
                    <span class="text-accent-ink">✦</span>
                    <span class="text-xs font-semibold text-accent-ink">POWERED BY META AI · MUSE SPARK</span>
                </div>
                <h2 class="text-4xl font-extrabold leading-[1.1] tracking-tight text-ink sm:text-5xl">
                    Not just another AI website builder.
                </h2>
                <p class="text-base leading-relaxed text-faint">
                    Most AI website builders start by generating a design. REDOLIO starts by understanding your business.
                    It analyses your existing website, extracts its business identity, identifies weaknesses with
                    supporting evidence, and turns those findings into a structured redesign plan.
                </p>
                <ul class="flex flex-col gap-3.5">
                    @foreach ([
                        'Observed facts vs. AI interpretations — clearly separated.',
                        'Every issue links to specific evidence from your website.',
                        'You can edit the DNA before the redesign begins.',
                    ] as $point)
                        <li class="flex gap-3 text-[15px] leading-relaxed text-muted">
                            <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-accent"></span>{{ $point }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="w-full max-w-xl overflow-hidden rounded-[20px] border border-accent/40 bg-accent-soft">
                <div class="flex items-center gap-2.5 border-b border-accent/40 bg-accent-soft px-5 py-3.5">
                    <span class="text-accent-ink">✦</span>
                    <span class="text-[13px] font-semibold text-accent-ink">Meta AI · Extracting Website DNA</span>
                </div>
                <div class="flex flex-col gap-3 p-5">
                    @foreach ([
                        ['done', 'Evidence collected', '52 items from 5 pages'],
                        ['done', 'Business identity understood', 'Dental practice · Local services'],
                        ['done', 'Audience identified', 'Adult patients · Families'],
                        ['active', 'Value proposition analysed', 'Extracting confidence level…'],
                        ['queued', 'Tone of voice evaluated', 'Queued'],
                        ['queued', 'Website redesign prepared', 'Queued'],
                    ] as [$state, $step, $detail])
                        <div class="flex items-center gap-3 rounded-[10px] px-3.5 py-2.5 {{ $state === 'active' ? 'border border-accent/40 bg-accent-soft' : 'bg-accent-soft' }}">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full {{ $state === 'done' ? 'bg-ok-soft' : 'bg-accent-soft' }}">
                                @if ($state === 'done')
                                    <span class="text-[11px] text-ok">✓</span>
                                @endif
                            </span>
                            <div class="flex flex-col gap-0.5">
                                <span class="text-[13px] {{ $state === 'queued' ? 'text-faint' : ($state === 'active' ? 'font-semibold text-ink' : 'text-body') }}">{{ $step }}</span>
                                <span class="text-[11px] {{ $state === 'active' ? 'text-accent-ink' : ($state === 'queued' ? 'text-body' : 'text-faint') }}">{{ $detail }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Stats Section --}}
    <section class="bg-canvas px-6 py-20 lg:px-16 lg:py-28">
        <div class="mx-auto flex max-w-7xl flex-col gap-16">
            <div class="flex flex-col items-start justify-between gap-12 lg:flex-row">
                <div class="flex max-w-xl flex-col gap-5">
                    <h2 class="text-4xl font-extrabold leading-[1.1] tracking-tight text-ink sm:text-5xl">
                        Stop guessing what your website needs.
                    </h2>
                    <p class="text-base leading-relaxed text-faint">
                        REDOLIO combines website crawling, AI analysis, and structured redesign to help you understand your
                        website, uncover opportunities, and see what a better version could look like.
                    </p>
                    <a href="#analyze" class="w-fit rounded-full bg-accent px-8 py-3.5 text-[15px] font-bold text-white hover:bg-accent">
                        Start analysing free →
                    </a>
                </div>

                <div class="flex w-full max-w-md flex-col lg:w-[440px]">
                    @foreach ([
                        ['Under 200s', 'Average analysis time'],
                        ['5', 'Evidence-backed issues per analysis'],
                        ['100%', 'Issue claims linked to website evidence'],
                    ] as [$value, $label])
                        <div class="flex items-center gap-6 border-t border-line py-5">
                            <span class="w-28 shrink-0 text-[40px] font-extrabold tracking-tight text-ink">{{ $value }}</span>
                            <span class="text-[15px] leading-snug text-faint">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-col items-center gap-10 rounded-[20px] border border-line bg-surface px-8 py-8 sm:flex-row sm:justify-center sm:gap-16">
                <span class="h-24 w-24 shrink-0 rounded-full" style="background: radial-gradient(circle, var(--c-accent) 0%, var(--c-accent-soft) 72%, var(--c-canvas) 100%);"></span>
                <div class="flex flex-wrap items-center justify-center gap-10 sm:gap-16">
                    @foreach ([
                        ['SMBs', 'Primary target'],
                        ['Agencies', 'Power users'],
                        ['Consultants', 'Also love REDOLIO'],
                        ['Hackathon', 'Built at Meta 2026'],
                    ] as [$value, $label])
                        <div class="flex flex-col gap-1.5">
                            <span class="text-xl font-extrabold tracking-tight text-ink">{{ $value }}</span>
                            <span class="text-[13px] text-faint">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Build Section --}}
    <section id="pricing" class="bg-surface-2 px-6 py-20 lg:px-16 lg:py-28">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-12">
            <div class="flex max-w-2xl flex-col items-center gap-3 text-center">
                <p class="text-xs font-bold tracking-[0.2em] text-accent-ink">BUILD ON REDOLIO</p>
                <h2 class="text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">
                    Better website insights for people who build businesses.
                </h2>
                <p class="max-w-xl text-base leading-relaxed text-faint">
                    Whether you're improving your own website or helping clients grow, REDOLIO gives you the clarity and
                    direction to make better website decisions.
                </p>
            </div>

            <div class="grid w-full gap-5 sm:grid-cols-3">
                @foreach ([
                    ['🔍', 'Freelance Designers', "Run a REDO analysis before your first client call. Show clients what's holding their website back and present a redesign preview backed by evidence.", 'Discover new insights'],
                    ['🏢', 'Small Agencies', 'Standardise your website audit workflow. Every client gets evidence-backed insights, a structured diagnosis, and a redesign preview.', 'Explore agency workflows'],
                    ['📈', 'Marketing Consultants', 'Pinpoint messaging gaps, CTA failures, and trust issues with real evidence. See the fix, not just the problem.', 'Explore smarter recommendations'],
                ] as [$icon, $title, $body, $tag])
                    <div class="flex flex-col gap-5 rounded-[20px] border border-line bg-surface p-8">
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-surface-2 text-xl">{{ $icon }}</span>
                        <h3 class="text-xl font-extrabold text-ink">{{ $title }}</h3>
                        <p class="text-sm leading-relaxed text-faint">{{ $body }}</p>
                        <span class="w-fit rounded-full border border-line-strong bg-surface-2 px-3.5 py-1.5 text-xs font-medium text-accent-ink">{{ $tag }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-line bg-canvas px-6 pb-10 pt-16 lg:px-16">
        <div class="mx-auto flex max-w-7xl flex-col gap-12">
            <div class="flex flex-col gap-10 sm:flex-row sm:justify-between">
                <div class="flex max-w-[280px] flex-col gap-3">
                    <span class="text-xl font-extrabold tracking-tight text-ink"><x-brand /></span>
                    <p class="text-[13px] leading-relaxed text-faint">The self-evolving website platform.<br>Understand. Diagnose. Redesign. Repeat.</p>
                    <a href="#analyze" class="mt-1 w-fit rounded-full bg-accent px-[22px] py-2.5 text-[13px] font-bold text-white hover:bg-accent">
                        Start Free
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-10 sm:flex sm:gap-16">
                    @foreach ([
                        'Product' => ['Features', 'How it works', 'Pricing', 'Changelog', 'Roadmap'],
                        'Resources' => ['Documentation', 'Security', 'API status', 'Evidence policy'],
                        'Company' => ['About', 'Built at Meta Hackathon', 'Privacy policy', 'Terms of service', 'Contact'],
                    ] as $heading => $links)
                        <div class="flex w-40 flex-col gap-3.5">
                            <span class="text-[13px] font-bold tracking-wide text-ink">{{ $heading }}</span>
                            @foreach ($links as $link)
                                <a href="#" class="text-[13px] text-faint hover:text-muted">{{ $link }}</a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="h-px w-full bg-line"></div>

            <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <span class="text-xs text-body">© 2026 REDOLIO. Built for better websites.</span>
                <p class="text-xs text-body">A product of <a href="https://appition.com" target="_blank" rel="noopener noreferrer" class="font-semibold text-ink underline-offset-2 hover:underline">Appition</a>.</p>
            </div>
        </div>
    </footer>
@endsection
