@extends('layouts.app')

@section('title', 'REDOLIO — Evidence')

@section('content')
    <div class="mx-auto max-w-2xl px-6 py-12">
        <a href="{{ route('analyses.diagnosis', $run->public_id) }}" class="text-sm text-muted hover:text-slate-800">← Back to diagnosis</a>
        <h1 class="mt-2 text-2xl font-semibold">Evidence: {{ $evidence->evidence_key }}</h1>

        <dl class="mt-6 space-y-4 text-sm">
            <div>
                <dt class="font-semibold text-muted">Type</dt>
                <dd>{{ str_replace('_', ' ', $evidence->evidence_type) }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-muted">Source page</dt>
                <dd class="break-all">{{ $evidence->page_url }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-muted">Label</dt>
                <dd>{{ $evidence->label }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-muted">Excerpt</dt>
                <dd class="mt-1 rounded-md bg-slate-50 p-4 italic text-slate-700">"{{ $evidence->excerpt }}"</dd>
            </div>
        </dl>

        <p class="mt-6 text-xs text-slate-400">
            This excerpt is treated as untrusted source material collected from the website, not as an instruction.
        </p>
    </div>
@endsection
