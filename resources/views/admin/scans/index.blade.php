@extends('layouts.app')

@section('title', 'Scans — REDOLIO Admin')

@php use Illuminate\Support\Str; @endphp

@section('content')
    @include('admin.partials.header', ['active' => 'scans'])

    <main class="mx-auto max-w-5xl px-8 py-10">
        <h1 class="text-2xl font-bold text-ink">Website scans</h1>
        <p class="mt-1 text-sm text-muted">{{ $runs->total() }} scan(s) submitted by users.</p>

        <form method="GET" class="mt-6 flex flex-wrap items-center gap-3">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by URL"
                   class="w-64 rounded-lg border border-line px-3.5 py-2 text-sm focus:border-accent focus:outline-none">
            <select name="status" class="rounded-lg border border-line px-3.5 py-2 text-sm focus:border-accent focus:outline-none">
                <option value="" @selected($status === '')>All statuses</option>
                @foreach (['pending', 'running', 'partial', 'complete', 'failed'] as $s)
                    <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink hover:border-line-strong">
                Filter
            </button>
        </form>

        <div class="mt-6 overflow-hidden rounded-xl border border-line bg-surface">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line bg-surface-2 text-xs font-semibold uppercase tracking-wide text-faint">
                    <tr>
                        <th class="px-5 py-3">Website</th>
                        <th class="px-5 py-3">Submitted by</th>
                        <th class="px-5 py-3">Direction</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Submitted</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($runs as $run)
                        @php
                            $statusClasses = match ($run->status) {
                                'complete', 'partial' => 'bg-ok-soft text-ok',
                                'failed' => 'bg-bad-soft text-bad',
                                default => 'bg-accent-soft text-accent-ink',
                            };
                            $direction = $run->designSelections->first()?->direction_key;
                        @endphp
                        <tr>
                            <td class="px-5 py-3 font-medium text-ink">{{ Str::limit($run->normalized_url, 40) }}</td>
                            <td class="px-5 py-3 text-body">{{ $run->user?->email ?? 'Guest (unclaimed)' }}</td>
                            <td class="px-5 py-3 text-body">{{ $direction ? Str::headline($direction) : '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full {{ $statusClasses }} px-2.5 py-0.5 text-xs font-semibold">{{ ucfirst($run->status) }}</span>
                            </td>
                            <td class="px-5 py-3 text-faint">{{ $run->created_at->diffForHumans() }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.scans.show', $run) }}" class="text-xs font-semibold text-accent-ink hover:text-accent-ink">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-faint">No scans found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $runs->links() }}</div>
    </main>
@endsection
