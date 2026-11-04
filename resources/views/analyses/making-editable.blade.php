@extends('layouts.app')

@section('title', 'Getting your editor ready — REDOLIO')
@section('no_float_toggle', '1')

@section('content')
    <x-app-header active="sites" />
    <div class="relative">
        <div class="page-glow"></div>
        <main class="relative mx-auto flex max-w-xl flex-col items-center px-5 pb-24 pt-16 text-center sm:px-8">
            <span class="grid h-14 w-14 place-items-center rounded-full border-[3px] border-accent bg-accent-soft">
                <svg viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 animate-spin" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
            </span>
            <h1 class="mt-6 text-[28px] font-black leading-tight tracking-tight text-ink sm:text-[34px]">Getting your <span class="grad-text">editor</span> ready…</h1>
            <p class="mt-3 text-[15.5px] leading-relaxed text-muted">This design was made with an earlier version of REDOLIO. We’ll rebuild it with the new layout engine — same look, same direction — so you can change the words, pictures and layout right on the page. It takes about two minutes, and your earlier versions stay in History.</p>
            <form id="go" method="POST" action="{{ route('analyses.make-editable', $run->public_id) }}" class="mt-7">@csrf
                <button type="submit" class="btn btn-primary px-7 py-3">Make it editable now →</button>
            </form>
            <p class="mt-4 text-[12.5px] text-faint">Starting automatically…</p>
            <a href="{{ route('analyses.reveal', $run->public_id) }}" class="mt-6 text-[13px] font-semibold text-accent-ink hover:text-ink">← Back to before &amp; after</a>
        </main>
    </div>
    <script>setTimeout(function () { var f = document.getElementById('go'); if (f) f.submit(); }, 1400);</script>
@endsection
