@php($pageEyebrow = 'Resources')
@php($pageTitle = 'API status')
@php($pageIntro = 'Whether everything REDOLIO depends on is working right now.')
@extends('pages.layout')

@section('page')
@php($colors = ['operational' => 'bg-emerald-500', 'degraded' => 'bg-amber-500', 'down' => 'bg-red-500', 'not_in_use' => 'bg-slate-400'])
@php($words = ['operational' => 'Operational', 'degraded' => 'Slower', 'down' => 'Down', 'not_in_use' => 'Not in use'])
<div class="not-prose rounded-2xl border border-line bg-surface p-5">
    <p class="flex items-center gap-3 text-[18px] font-extrabold text-ink"><span class="h-3 w-3 rounded-full {{ $colors[$report['status']] }}"></span>{{ $report['label'] }}</p>
    <ul class="mt-4 divide-y divide-line">
        @foreach ($report['checks'] as $check)
            <li class="flex items-start justify-between gap-4 py-3">
                <div><p class="text-[15px] font-bold text-ink">{{ $check['name'] }}</p><p class="text-[13px] text-muted">{{ $check['note'] }}</p></div>
                <span class="flex shrink-0 items-center gap-2 text-[13px] font-semibold text-ink"><span class="h-2.5 w-2.5 rounded-full {{ $colors[$check['status']] }}"></span>{{ $words[$check['status']] }}</span>
            </li>
        @endforeach
    </ul>
    <p class="mt-3 text-[12px] text-faint">Checked {{ \Illuminate\Support\Carbon::parse($report['checked_at'])->diffForHumans() }}. Machine-readable: <a href="{{ route('pages.status.json') }}">status.json</a></p>
</div>
@endsection
