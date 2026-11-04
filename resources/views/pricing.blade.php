@extends('layouts.marketing')

@section('title', 'Pricing — REDOLIO')

@section('content')
    <div class="mx-auto max-w-5xl px-6 py-20">
        <nav class="mb-16 flex items-center justify-between">
            <a href="{{ route('landing') }}" class="text-xl font-extrabold tracking-tight text-ink"><x-brand /></a>
            <div class="flex items-center gap-4 text-sm">
                @auth
                    <a href="{{ route('dashboard') }}" class="font-medium text-muted hover:text-ink">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="font-medium text-muted hover:text-ink">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-full bg-accent px-4 py-2 font-semibold text-white hover:bg-accent">Start free</a>
                @endauth
            </div>
        </nav>

        <div class="text-center">
            <h1 class="text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">Simple pricing, built to scale with your sites</h1>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-muted">
                Whether you're redoing one site or managing a dozen client websites, REDOLIO has a plan that keeps your sites evolving.
            </p>
        </div>

        <div class="mt-16 grid gap-6 sm:grid-cols-3">
            @foreach ($plans as $plan)
                @php
                    $isCurrent = auth()->check() && auth()->user()->currentPlan()?->is($plan);
                    $isFeatured = $plan->plan_key === 'starter';
                @endphp
                <div class="relative flex flex-col rounded-2xl border {{ $isFeatured ? 'border-accent bg-accent-soft' : 'border-line bg-surface' }} p-8">
                    @if ($isFeatured)
                        <span class="absolute -top-3 left-8 rounded-full bg-accent px-3 py-1 text-xs font-semibold text-white">Most popular</span>
                    @endif

                    <h2 class="text-lg font-bold text-ink">{{ $plan->name }}</h2>
                    <p class="mt-1 text-sm text-faint">{{ $plan->tagline }}</p>

                    <p class="mt-6 text-4xl font-extrabold text-ink">
                        ${{ number_format($plan->priceMonthly(), 0) }}
                        <span class="text-base font-medium text-faint">/mo</span>
                    </p>

                    <ul class="mt-6 flex-1 space-y-3 text-sm text-body">
                        @foreach ($plan->features_json ?? [] as $feature)
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5 text-accent-ink">&check;</span>
                                <span>{{ $feature }}</span>
                            </li>
                        @endforeach
                    </ul>

                    @auth
                        @if ($isCurrent)
                            <span class="mt-8 block rounded-full border border-line-strong py-3 text-center text-sm font-semibold text-faint">Current plan</span>
                        @else
                            <form method="POST" action="{{ route('plans.select', $plan) }}">
                                @csrf
                                <button type="submit" class="mt-8 w-full rounded-full {{ $isFeatured ? 'bg-accent hover:bg-accent' : 'bg-ink/10 hover:bg-ink/15' }} py-3 text-sm font-semibold text-white">
                                    Switch to {{ $plan->name }}
                                </button>
                            </form>
                        @endif
                    @else
                        <a href="{{ route('register') }}" class="mt-8 block rounded-full {{ $isFeatured ? 'bg-accent hover:bg-accent' : 'bg-ink/10 hover:bg-ink/15' }} py-3 text-center text-sm font-semibold text-white">
                            Start with {{ $plan->name }}
                        </a>
                    @endauth
                </div>
            @endforeach
        </div>

        <div class="mx-auto mt-10 flex max-w-xl flex-col items-center gap-3 rounded-2xl border border-line bg-surface p-6 text-center">
            <p class="text-sm font-bold text-ink">Just want one website, no subscription?</p>
            <p class="text-sm text-muted">
                @php $exp = app(\App\Services\Billing\Billing::class)->exportPassPricing(); @endphp
                Export Pass — <x-export-pass-price :pricing="$exp" /> one-time{{ $exp['offer'] ? ' (launch offer)' : '' }}. A production-ready static HTML/CSS/JS download of your approved redesign, no REDOLIO hosting required.
            </p>
            @auth
                <form method="POST" action="{{ route('export-pass.purchase') }}">
                    @csrf
                    <button type="submit" class="rounded-full bg-ink/10 px-5 py-2.5 text-sm font-semibold text-white hover:bg-ink/15">
                        Get Export Pass
                    </button>
                </form>
            @else
                <a href="{{ route('register') }}" class="rounded-full bg-ink/10 px-5 py-2.5 text-sm font-semibold text-white hover:bg-ink/15">
                    Sign up to get Export Pass
                </a>
            @endauth
        </div>

        <p class="mt-10 text-center text-xs text-faint">
            Secure payments by Stripe. Cancel any time. Prefer to self-host? Grab the MIT-licensed edition at redoweb.org.
        </p>
    </div>
@endsection
