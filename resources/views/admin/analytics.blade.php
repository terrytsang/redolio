@extends('layouts.app')

@section('title', 'Analytics — REDOLIO Admin')

@section('content')
    @include('admin.partials.header', ['active' => 'analytics'])

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-8 sm:py-10">
        <h1 class="text-2xl font-bold text-ink">Analytics</h1>
        <p class="mt-1 text-sm text-muted">Every visit to redolio.com and to published sites — when, from where, on what, and how they found you. IP addresses are not stored.</p>

        <form method="GET" class="mt-6 flex flex-wrap items-center gap-3">
            <select name="days" class="rounded-lg border border-line bg-surface px-3.5 py-2 text-sm text-ink focus:border-accent focus:outline-none">
                @foreach ([1 => 'Today', 7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $d => $l)
                    <option value="{{ $d }}" @selected($days === $d)>{{ $l }}</option>
                @endforeach
            </select>
            <select name="device" class="rounded-lg border border-line bg-surface px-3.5 py-2 text-sm text-ink focus:border-accent focus:outline-none">
                <option value="">All devices</option>
                @foreach (['mobile' => 'Mobile', 'tablet' => 'Tablet', 'desktop' => 'Desktop'] as $d => $l)
                    <option value="{{ $d }}" @selected($device === $d)>{{ $l }}</option>
                @endforeach
            </select>
            <input type="text" name="q" value="{{ $q }}" placeholder="Country, city, source or page"
                   class="w-64 rounded-lg border border-line bg-surface px-3.5 py-2 text-sm text-ink focus:border-accent focus:outline-none">
            <button type="submit" class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink hover:border-line-strong">Apply</button>
        </form>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-line bg-surface p-5">
                <p class="text-xs font-medium text-faint">Visits</p>
                <p class="mt-2 text-2xl font-bold text-ink">{{ number_format($visits) }}</p>
                <p class="text-[11px] text-faint">{{ number_format($visitors) }} unique visitors</p>
            </div>
            @foreach ([['Top countries', $topCountries], ['Top sources', $topSources], ['Devices', $devices]] as [$title, $list])
                <div class="rounded-xl border border-line bg-surface p-5">
                    <p class="text-xs font-medium text-faint">{{ $title }}</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        @forelse ($list as $item)
                            <li class="flex justify-between gap-3"><span class="truncate text-body">{{ ucfirst($item->label) }}</span><span class="font-semibold text-ink">{{ number_format($item->visits) }}</span></li>
                        @empty
                            <li class="text-faint">No data yet</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="mt-6 overflow-x-auto rounded-xl border border-line bg-surface">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="border-b border-line bg-surface-2 text-xs font-semibold uppercase tracking-wide text-faint">
                    <tr>
                        <th class="px-5 py-3">Date visited</th>
                        <th class="px-5 py-3">Country</th>
                        <th class="px-5 py-3">City</th>
                        <th class="px-5 py-3">Device</th>
                        <th class="px-5 py-3">Source</th>
                        <th class="px-5 py-3">Page</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $v)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-2.5 text-body">{{ $v->created_at->format('M j, Y H:i') }}</td>
                            <td class="px-5 py-2.5 text-body">{{ $v->country ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-body">{{ $v->city ?? '—' }}</td>
                            <td class="px-5 py-2.5">
                                @if ($v->device)
                                    <span class="rounded-full bg-surface-2 px-2.5 py-0.5 text-xs font-semibold text-muted">{{ ucfirst($v->device) }}</span>
                                @else — @endif
                            </td>
                            <td class="px-5 py-2.5 text-body">{{ $v->source ?? '—' }}</td>
                            <td class="max-w-[220px] truncate px-5 py-2.5 text-faint">/{{ $v->path === '/' ? '' : $v->path }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-faint">No visits recorded for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </main>
@endsection
