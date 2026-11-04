@extends('layouts.app')

@section('title', 'REDOLIO — Generating your preview')

@section('content')
    <x-app-nav :run="$run" />
    <x-stage-bar active="redo" />

    <div class="flex flex-col items-center justify-center gap-8 px-5 py-14 sm:px-20 sm:py-24">
        <div class="flex w-full max-w-[560px] flex-col items-center gap-6 rounded-[20px] border border-line bg-surface p-7 text-center sm:p-12">
            <span class="flex h-14 w-14 items-center justify-center rounded-full border-[3px] border-accent bg-accent-soft">
                <svg viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 animate-spin">
                    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                </svg>
            </span>

            <div class="flex flex-col gap-1.5">
                @if ($selection->refine_from_design_spec_id)
                    <p class="text-lg font-bold text-ink">Refining your {{ $direction?->name ?? 'current' }} REDO…</p>
                    <p class="text-sm text-body">
                        Meta AI is sharpening the copy and layout of your current design — same direction, better
                        execution. This usually takes under a minute.
                    </p>
                @else
                    <p class="text-lg font-bold text-ink">Building your {{ $direction?->name ?? 'new' }} REDO…</p>
                    <p class="text-sm text-body">
                        Meta AI is writing your redesigned copy, choosing layout, and bringing in your real logo and photos.
                        This usually takes under a minute.
                    </p>
                    <ul class="mx-auto mt-3 flex flex-col gap-1.5 text-left text-[13px] text-muted">
                        <li>✓ Reading what your site really says</li>
                        <li>✓ Choosing a layout that suits your business</li>
                        <li>✓ Writing sharper hero wording — checked against your site, never invented</li>
                    </ul>
                    <div class="mt-2 w-full rounded-2xl bg-accent-soft px-5 py-4 text-left" aria-live="polite">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-accent-ink">While the robots work…</p>
                        <p id="idle-tip" class="mt-1.5 min-h-[4.5rem] text-[15px] font-semibold leading-snug text-ink transition-opacity duration-500"></p>
                        <p class="mt-1 text-[12px] text-muted">No need to watch. This page jumps to your new website the moment it’s ready.</p>
                    </div>
                @endif
            </div>

            <div class="h-1.5 w-full overflow-hidden rounded-full bg-line">
                <div class="h-full w-1/3 animate-pulse rounded-full bg-accent"></div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            // Fun things to do while it builds. One at a time, rotating; the first is random so repeat visitors see something new.
            const tips = [
                '☕ Go and make a cup of coffee. Seriously — by the time the milk is frothed, your new website will be waiting for you.',
                '📬 Pop over to your email, delete three newsletters you never read, and come back in about two minutes. It’ll look better than your inbox.',
                '📞 Call someone you love and tell them what the plan is tonight. (“Pizza” counts as a plan. So does “nothing, gloriously”.)',
                '🧘 Stand up, stretch, and roll your shoulders. Your neck has been in that position since the scan started.',
                '🪴 Water the plant on your desk. It has been judging your tab count for weeks.',
                '🥤 Drink some water. Coffee counts, but it doesn’t count double.',
                '🍪 Get a snack. The best websites are reviewed with crumbs on the keyboard.',
            ];
            const el = document.getElementById('idle-tip');
            let i = Math.floor(Math.random() * tips.length);
            const show = () => { el.style.opacity = 0; setTimeout(() => { el.textContent = tips[i]; el.style.opacity = 1; i = (i + 1) % tips.length; }, matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 450); };
            el.textContent = tips[i]; i = (i + 1) % tips.length;
            setInterval(show, 9000);

            // Poll quietly (no page reload, so the tip above isn't reset): this URL redirects to the reveal when done, or back to the choices if it failed.
            const poll = async () => {
                try {
                    const res = await fetch(location.href, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
                    if (res.redirected) return (location.href = res.url);
                } catch (e) { /* offline blip: try again */ }
                setTimeout(poll, 3000);
            };
            setTimeout(poll, 3000);
        })();
    </script>
@endsection
