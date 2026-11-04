@extends('layouts.app')

@section('title', 'Admin — REDOLIO')

@section('content')
    @include('admin.partials.header', ['active' => 'dashboard'])

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-8 sm:py-10">
        <h1 class="text-2xl font-bold text-ink">Dashboard</h1>
        <p class="mt-1 text-sm text-muted">How REDOLIO is doing right now.</p>

        @unless ($metaKeySet)
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-warn/40 bg-warn-soft px-5 py-4 text-sm text-warn" role="status">
                <p><strong>One more step to get started:</strong> add your Meta Model API key so REDOLIO can analyse and redesign websites. Until then it runs in demo mode.</p>
                <a href="{{ route('admin.settings') }}" class="shrink-0 rounded-full bg-ink px-4 py-2 text-[13px] font-semibold text-canvas hover:opacity-85">Open Settings →</a>
            </div>
        @endunless

        <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
            @foreach ([
                ['Total users', number_format($totalUsers), null],
                ['Website analyses', number_format($totalAnalyses), null],
                ['Websites watched', number_format($totalWatched), null],
                ['Subscriptions', number_format($totalSubscriptions), 'paid plans'],
                ['MRR (USD)', '$'.number_format($mrr, 2), 'monthly recurring'],
            ] as [$label, $value, $hint])
                <div class="rounded-xl border border-line bg-surface p-5 {{ $loop->last ? 'col-span-2 lg:col-span-1' : '' }}">
                    <p class="text-xs font-medium text-faint">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-bold text-ink">{{ $value }}</p>
                    @if ($hint)<p class="mt-0.5 text-[11px] text-faint">{{ $hint }}</p>@endif
                </div>
            @endforeach
        </div>

        <div class="mt-8 grid items-start gap-6 lg:grid-cols-2">
            <section class="overflow-hidden rounded-xl border border-line bg-surface">
                <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                    <h2 class="text-sm font-bold text-ink">Latest 10 sign-ups</h2>
                    <a href="{{ route('admin.users.index') }}" class="text-xs font-semibold text-accent-ink">All users →</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-line">
                            @forelse ($latestUsers as $u)
                                <tr>
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-ink">{{ $u->name }}</p>
                                        <p class="text-xs text-faint">{{ $u->email }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-xs text-muted">{{ $u->subscriptions->first()?->plan?->name ?? 'Free' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-xs text-faint">{{ $u->created_at->format('M j, H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td class="px-5 py-8 text-center text-sm text-faint">No sign-ups yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-line bg-surface">
                <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                    <h2 class="text-sm font-bold text-ink">Latest 10 websites entered</h2>
                    <a href="{{ route('admin.scans.index') }}" class="text-xs font-semibold text-accent-ink">All scans →</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-line">
                            @forelse ($latestWebsites as $run)
                                <tr>
                                    <td class="px-5 py-3">
                                        <a href="{{ route('admin.scans.show', $run->public_id) }}" class="font-medium text-ink hover:text-accent-ink">{{ parse_url($run->normalized_url, PHP_URL_HOST) ?: $run->normalized_url }}</a>
                                        <p class="text-xs text-faint">{{ $run->user?->email ?? 'Not signed up' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-xs text-muted">{{ ucfirst($run->status) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-xs text-faint">{{ $run->created_at->format('M j, H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td class="px-5 py-8 text-center text-sm text-faint">No websites analysed yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
@endsection
