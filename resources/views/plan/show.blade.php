@extends('layouts.app')

@section('title', 'Plan & billing — REDOLIO')
@section('no_float_toggle', '1')

@section('content')
    <x-app-header active="plan" />
    <div class="relative">
        <div class="page-glow"></div>
        <main class="relative mx-auto max-w-6xl px-5 pb-24 pt-10 sm:px-8">
            <a href="{{ route('dashboard') }}" class="text-[13px] font-semibold text-muted hover:text-ink">&larr; My websites</a>
            <h1 class="mt-3 text-[34px] font-black leading-[1.1] tracking-tight text-ink sm:text-[40px]">Plan &amp; <span class="grad-text">billing</span></h1>
            <p class="mt-2 max-w-xl text-[15px] text-muted">See what you have, and move up or down whenever it suits you.</p>

            @if (session('status'))
                <div class="mt-6 rounded-xl bg-accent-soft px-4 py-3 text-sm text-accent-ink" role="status">{{ session('status') }}</div>
            @endif

            @if ($plan)
                <section class="card relative mt-8 overflow-hidden p-7 sm:p-8" aria-labelledby="current-h">
                    <div class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-accent/15 blur-3xl"></div>
                    <div class="relative flex flex-wrap items-start justify-between gap-6">
                        <div>
                            <span class="pill pill-ok">● Current plan</span>
                            <h2 id="current-h" class="mt-3 text-[32px] font-black leading-none tracking-tight text-ink">{{ $plan->name }}</h2>
                            <p class="mt-2 max-w-md text-[14px] text-muted">{{ $plan->tagline }}</p>
                        </div>
                        <p class="text-right"><span class="text-[40px] font-black leading-none tracking-tight text-ink">{{ $plan->price_monthly_cents ? '$'.number_format($plan->priceMonthly(), 0) : 'Free' }}</span>@if ($plan->price_monthly_cents)<span class="text-sm font-semibold text-muted"> /month</span>@endif</p>
                    </div>
                    @php $stripeSub = auth()->user()->subscriptions()->where('status', 'active')->whereNotNull('stripe_subscription_id')->latest('started_at')->first(); @endphp
                    @if ($stripeSub)
                        <div class="relative mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-2 px-4 py-3 text-[13px] text-body">
                            <span>@if ($stripeSub->cancel_at_period_end)Ends {{ $stripeSub->current_period_end?->format('j M Y') ?? 'at the end of the period' }}, then moves to Free.@else Renews {{ $stripeSub->current_period_end?->format('j M Y') ?? 'monthly' }}.@endif</span>
                            <form method="POST" action="{{ route('billing.portal') }}">@csrf<button class="btn btn-ghost !py-1.5 text-[12.5px]">Manage billing &amp; invoices</button></form>
                        </div>
                    @endif
                    <dl class="relative mt-7 grid gap-4 border-t border-line pt-6 sm:grid-cols-3">
                        <div><dt class="text-[11px] font-bold uppercase tracking-[0.16em] text-faint">Watched websites</dt><dd class="mt-1 text-lg font-extrabold text-ink">{{ $watching }} <span class="text-sm font-semibold text-muted">of {{ $plan->site_limit ?? 'unlimited' }}</span></dd></div>
                        <div><dt class="text-[11px] font-bold uppercase tracking-[0.16em] text-faint">Website analyses</dt><dd class="mt-1 text-lg font-extrabold text-ink">{{ auth()->user()->analysesUsed() }} <span class="text-sm font-semibold text-muted">of {{ $plan->analysis_limit ?? 'unlimited' }}</span></dd></div>
                        <div><dt class="text-[11px] font-bold uppercase tracking-[0.16em] text-faint">Re-analysis</dt><dd class="mt-1 text-lg font-extrabold text-ink">{{ $plan->auto_rescan_frequency_days ? 'Every '.$plan->auto_rescan_frequency_days.' days' : 'Manual' }}</dd></div>
                    </dl>
                </section>
            @endif

            <h2 class="mt-12 text-xl font-extrabold tracking-tight text-ink">All plans</h2>
            <div class="mt-5 grid gap-6 lg:grid-cols-3">
                @foreach ($options as $o)
                    @php
                        $p = $o['plan']; $rel = $o['relation']; $featured = $p->plan_key === 'starter';
                    @endphp
                    <article class="card relative flex flex-col p-7 {{ $rel === 'current' ? 'border-accent ring-2 ring-accent/25' : '' }}" aria-label="{{ $p->name }} plan">
                        @if ($rel === 'current')<span class="pill pill-accent absolute -top-3 left-6">Current plan</span>
                        @elseif ($featured)<span class="pill absolute -top-3 left-6 bg-accent text-white">Most popular</span>@endif
                        <h3 class="text-lg font-extrabold text-ink">{{ $p->name }}</h3>
                        <p class="mt-1 min-h-[2.5rem] text-[13px] text-muted">{{ $p->tagline }}</p>
                        <p class="mt-5 text-[38px] font-black leading-none tracking-tight text-ink">{{ $p->price_monthly_cents ? '$'.number_format($p->priceMonthly(), 0) : '$0' }}<span class="text-sm font-semibold text-muted"> /mo</span></p>
                        <ul class="mt-6 flex-1 space-y-2.5 text-[13.5px] text-body">
                            @foreach ($p->features_json ?? [] as $f)
                                <li class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg><span>{{ $f }}</span></li>
                            @endforeach
                        </ul>

                        @if ($rel === 'current')
                            <span class="btn btn-ghost mt-7 cursor-default opacity-70" aria-disabled="true">You're on this plan</span>
                        @elseif ($rel === 'upgrade')
                            <form method="POST" action="{{ route('plans.select', $p) }}" class="mt-7">@csrf
                                <button type="submit" class="btn btn-primary w-full">Upgrade to {{ $p->name }}</button>
                            </form>
                        @else
                            <button type="button" class="btn btn-ghost mt-7 w-full" data-open-dialog="down-{{ $p->id }}">Downgrade to {{ $p->name }}</button>
                            <dialog id="down-{{ $p->id }}" class="card m-auto w-[min(92vw,26rem)] p-7 text-ink backdrop:bg-black/50">
                                <h3 class="text-lg font-extrabold">Downgrade to {{ $p->name }}?</h3>
                                <p class="mt-2 text-sm text-muted">You'll switch to the {{ $p->name }} plan straight away. {{ $p->site_limit !== null ? "It allows {$p->site_limit} watched ".\Illuminate\Support\Str::plural('website', $p->site_limit).'.' : '' }}</p>
                                @if ($o['over_limit'])
                                    <p class="mt-3 rounded-lg bg-warn-soft px-3 py-2 text-[13px] text-warn">You're watching {{ $watching }} websites, {{ $o['over_limit'] }} more than {{ $p->name }} allows. Nothing is deleted, but pause or remove {{ $o['over_limit'] }} on your dashboard to stay within the limit.</p>
                                @endif
                                <div class="mt-6 flex justify-end gap-2">
                                    <button type="button" class="btn btn-ghost" data-close-dialog>Keep my plan</button>
                                    <form method="POST" action="{{ route('plans.select', $p) }}">@csrf<button type="submit" class="btn btn-dark">Yes, downgrade</button></form>
                                </div>
                            </dialog>
                        @endif
                    </article>
                @endforeach
            </div>
            <p class="mt-6 text-center text-[12.5px] text-faint">@if (app(\App\Services\Billing\Billing::class)->enabled())Payments are handled securely by Stripe. Upgrade, downgrade or cancel any time — changes are prorated.@else Online payments aren't switched on yet.@endif</p>
        </main>
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-open-dialog]').forEach((b) => b.addEventListener('click', () => document.getElementById(b.dataset.openDialog)?.showModal()));
    document.querySelectorAll('[data-close-dialog]').forEach((b) => b.addEventListener('click', () => b.closest('dialog').close()));
</script>
@endpush
