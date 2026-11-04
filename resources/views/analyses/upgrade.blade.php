@extends('layouts.app')

@section('title', 'Choose a plan to publish — REDOLIO')
@section('no_float_toggle', '1')

@section('content')
    <x-app-header active="sites" />
    <div class="relative">
        <div class="page-glow"></div>
        <main class="relative mx-auto max-w-5xl px-5 pb-24 pt-10 sm:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <span class="pill pill-ok">✓ Your layout is saved</span>
                <h1 class="mt-4 text-[32px] font-black leading-[1.1] tracking-tight text-ink sm:text-[42px]">Make <span class="grad-text">{{ $host }}</span> live</h1>
                <p class="mt-3 text-[16px] leading-relaxed text-muted">Your redesign is ready. Pick a plan to publish it on a REDOLIO web address or download the website files — your edits come with you.</p>
            </div>

            @if (session('status'))<div class="mx-auto mt-6 max-w-2xl rounded-xl bg-accent-soft px-4 py-3 text-sm text-accent-ink" role="status">{{ session('status') }}</div>@endif
            @if ($cancelled)<div class="mx-auto mt-6 max-w-2xl rounded-xl bg-warn-soft px-4 py-3 text-sm text-warn" role="status">No payment was taken. Your layout is still saved — choose a plan whenever you’re ready.</div>@endif

            <div class="mt-10 grid gap-6 md:grid-cols-2">
                @foreach ($plans as $p)
                    @php $featured = $p->plan_key === 'starter'; @endphp
                    <article class="card relative flex flex-col p-7 {{ $featured ? 'border-accent ring-2 ring-accent/25' : '' }}">
                        @if ($featured)<span class="pill absolute -top-3 left-6 bg-accent text-white">Most popular</span>@endif
                        <h2 class="text-xl font-extrabold text-ink">{{ $p->name }}</h2>
                        <p class="mt-1 min-h-[2.5rem] text-[13.5px] text-muted">{{ $p->tagline }}</p>
                        <p class="mt-5 text-[44px] font-black leading-none tracking-tight text-ink">${{ number_format($p->priceMonthly(), 0) }}<span class="text-sm font-semibold text-muted"> /month</span></p>
                        <ul class="mt-6 flex-1 space-y-2.5 text-[14px] text-body">
                            @foreach ($p->features_json ?? [] as $f)
                                <li class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg><span>{{ $f }}</span></li>
                            @endforeach
                        </ul>
                        <form method="POST" action="{{ route('plans.select', $p) }}" class="mt-7">@csrf
                            <input type="hidden" name="run" value="{{ $run->public_id }}"><input type="hidden" name="intent" value="{{ $intent }}">
                            <button type="submit" class="btn {{ $featured ? 'btn-primary' : 'btn-dark' }} w-full py-3">Choose {{ $p->name }} &amp; continue</button>
                        </form>
                    </article>
                @endforeach
            </div>

            {{-- Export Pass: pay once, get the website files --}}
            <section class="card relative mt-6 flex flex-col gap-5 p-7 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="exp-h">
                @if ($exportPass['offer'])<span class="pill absolute -top-3 left-6 bg-ok text-white">Launch offer</span>@endif
                <div class="max-w-xl">
                    <h2 id="exp-h" class="text-xl font-extrabold text-ink">Export Pass <span class="text-sm font-semibold text-muted">· pay once</span></h2>
                    <p class="mt-1 text-[14px] leading-relaxed text-body">Just want the website? Get your approved redesign as a ready-to-host <strong>HTML, CSS &amp; JS</strong> download — no subscription, and it isn’t published on REDOLIO.</p>
                    <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-[13px] text-muted">
                        <li>✓ One download of {{ $host }}</li><li>✓ Host it anywhere</li><li>✓ Your edits included</li>
                    </ul>
                </div>
                <form method="POST" action="{{ route('export-pass.purchase') }}" class="shrink-0 sm:text-right">@csrf
                    <input type="hidden" name="run" value="{{ $run->public_id }}">
                    <p class="text-[34px] font-black leading-none tracking-tight"><x-export-pass-price :pricing="$exportPass" /></p>
                    <p class="mt-1 text-[12px] text-muted">{{ $exportPass['offer'] ? 'launch offer · one-time payment' : 'one-time payment' }}</p>
                    <button type="submit" class="btn btn-dark mt-4 w-full px-6 py-3 sm:w-auto">Get Export Pass &amp; download</button>
                </form>
            </section>

            <div class="mx-auto mt-10 max-w-2xl rounded-2xl border border-line bg-surface p-5 text-center text-[13px] text-muted">
                {{ $billingLive ? 'Secure payment by Stripe. Cancel any time.' : 'Online payments will be available very soon.' }}
                <div class="mt-3 flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
                    <a href="{{ route('analyses.edit', $run->public_id) }}" class="font-semibold text-accent-ink hover:text-ink">← Keep editing</a>
                    <a href="{{ route('analyses.reveal', $run->public_id) }}" class="font-semibold text-accent-ink hover:text-ink">See before &amp; after</a>
                    <a href="{{ route('dashboard') }}" class="font-semibold text-muted hover:text-ink">Maybe later</a>
                </div>
            </div>
        </main>
    </div>
@endsection
