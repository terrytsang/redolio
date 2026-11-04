@extends('layouts.app')

@section('title', 'Scan detail — REDOLIO Admin')

@php use Illuminate\Support\Str; @endphp

@section('content')
    @include('admin.partials.header', ['active' => 'scans'])

    <main class="mx-auto max-w-3xl px-8 py-10">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('admin.scans.index') }}" class="text-muted hover:text-ink">Scans</a>
            <span class="text-line-strong">/</span>
            <span class="font-medium text-ink">{{ $run->normalized_url }}</span>
        </div>
        <h1 class="mt-2 text-2xl font-bold text-ink">{{ $run->normalized_url }}</h1>

        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-medium text-faint">Status</p>
                <p class="mt-1 text-sm font-semibold text-ink">{{ ucfirst($run->status) }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-medium text-faint">Stage</p>
                <p class="mt-1 text-sm font-semibold text-ink">{{ Str::headline($run->current_stage) }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-medium text-faint">Submitted by</p>
                <p class="mt-1 text-sm font-semibold text-ink">{{ $run->user?->email ?? 'Guest (unclaimed)' }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-medium text-faint">Submitted</p>
                <p class="mt-1 text-sm font-semibold text-ink">{{ $run->created_at->format('M j, Y g:ia') }}</p>
            </div>
        </div>

        @if ($run->status === 'failed')
            <div class="mt-6 rounded-lg bg-bad-soft px-4 py-3 text-sm text-bad">
                {{ $run->safe_error_message ?? 'This scan failed.' }}
            </div>
        @endif

        <div class="mt-6 flex flex-col gap-4">
            <div class="rounded-xl border border-line bg-surface p-5">
                <p class="text-sm font-bold text-ink">Chosen design direction</p>
                @if ($designSelection)
                    <p class="mt-1 text-sm text-body">{{ Str::headline($designSelection->direction_key) }} — status: {{ $designSelection->status }}</p>
                @else
                    <p class="mt-1 text-sm text-faint">No design direction selected yet.</p>
                @endif
            </div>

            <div class="rounded-xl border border-line bg-surface p-5">
                <p class="text-sm font-bold text-ink">Website DNA</p>
                @if ($dna)
                    <p class="mt-1 text-sm text-body">{{ $dna->dna_json['business_summary'] ?? 'Captured — see raw data below.' }}</p>
                @else
                    <p class="mt-1 text-sm text-faint">Not yet generated.</p>
                @endif
            </div>

            <div class="rounded-xl border border-line bg-surface p-5">
                <p class="text-sm font-bold text-ink">Diagnosis</p>
                @if ($diagnosis)
                    <ul class="mt-2 flex flex-col gap-1.5 text-sm text-body">
                        @foreach (array_slice($diagnosis->issues_json['issues'] ?? [], 0, 5) as $issue)
                            <li>• {{ $issue['title'] ?? $issue['issue_id'] }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-1 text-sm text-faint">Not yet generated.</p>
                @endif
            </div>

            <div class="rounded-xl border border-line bg-surface p-5">
                <p class="text-sm font-bold text-ink">Latest DesignSpec</p>
                @if ($designSpec)
                    <p class="mt-1 text-sm text-body">v{{ $designSpec->version }} · Template: {{ Str::headline($designSpec->template_key) }}</p>
                @else
                    <p class="mt-1 text-sm text-faint">No redesign generated yet.</p>
                @endif
            </div>
        </div>
    </main>
@endsection
