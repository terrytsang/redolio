@extends('layouts.app')

@section('title', 'REDOLIO — Launch Decision')

@section('content')
    <x-app-nav :run="$run" />
    <x-stage-bar active="redo" />

    <div class="mx-auto flex max-w-5xl flex-col gap-7 px-5 py-10 sm:px-12 sm:py-12">
        @if (session('status'))
            <div class="rounded-lg bg-accent-soft px-4 py-3 text-sm text-accent-ink">{{ session('status') }}</div>
        @endif

        <div class="flex flex-col gap-1.5">
            <h1 class="text-[22px] font-extrabold tracking-tight text-ink">Your redesign is ready. What would you like to do next?</h1>
            <p class="text-[13px] text-faint">
                Template: {{ \Illuminate\Support\Str::headline($spec->template_key) }} · v{{ $spec->version }}
            </p>
        </div>

        @if (! $approved)
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-warn/30 bg-warn-soft p-6">
                <div>
                    <p class="text-sm font-semibold text-warn">Approve this design to continue</p>
                    <p class="mt-1 text-[13px] text-warn">Approving saves this exact version as your draft — publishing and export both start from here.</p>
                </div>
                <form method="POST" action="{{ route('analyses.approve-design', $run->public_id) }}">
                    @csrf
                    <button type="submit" class="shrink-0 rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">
                        Approve design
                    </button>
                </form>
            </div>
        @else
            <div class="rounded-2xl border border-emerald-200 bg-ok-soft p-4 text-[13px] text-ok">
                Approved {{ $approved->approved_at->diffForHumans() }} — v{{ $approved->designSpec->version }} is your current draft.
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <a href="{{ route('analyses.publish', $run->public_id) }}" class="flex flex-col gap-3 rounded-2xl border-2 border-accent bg-[#FAF5FF] p-6">
                    <span class="w-fit rounded-full bg-accent px-2.5 py-0.5 text-[10px] font-bold text-white">RECOMMENDED</span>
                    <p class="text-base font-bold text-ink">Publish my website</p>
                    <p class="text-[13px] leading-relaxed text-body">Launch on a REDOLIO web address. Hosting, versioning and rollback are managed for you.</p>
                    @unless ($user->canPublish())
                        <span class="mt-auto text-xs font-semibold text-accent-ink">Requires Starter or Agency →</span>
                    @endunless
                </a>

                <form method="POST" action="{{ route('analyses.exports.store', $run->public_id) }}" class="flex flex-col gap-3 rounded-2xl border-2 border-line bg-surface p-6">
                    @csrf
                    <p class="text-base font-bold text-ink">Download website files</p>
                    <p class="text-[13px] leading-relaxed text-body">Export a production-ready static HTML/CSS/JS package you can host anywhere.</p>
                    @if ($user->canExportStatic() && $canExportThis)
                        <button type="submit" class="mt-auto w-fit rounded-lg bg-ink px-4 py-2 text-xs font-semibold text-canvas hover:opacity-85">
                            Export now
                        </button>
                        @if ($pass)<p class="text-[12px] text-muted">Export Pass: {{ $pass['downloads'] }} {{ \Illuminate\Support\Str::plural('download', $pass['downloads']) }} and {{ $pass['edits'] }} saved {{ \Illuminate\Support\Str::plural('edit', $pass['edits']) }} left for this website.</p>@endif
                    @elseif ($user->canExportStatic())
                        <p class="text-[12.5px] leading-snug text-warn">You’ve used all the downloads included with this website’s Export Pass.</p>
                        <a href="{{ route('analyses.upgrade', $run->public_id) }}" class="mt-auto text-xs font-semibold text-accent-ink">Choose Starter or Agency for unlimited →</a>
                    @else
                        <span class="mt-auto text-xs font-semibold text-accent-ink">Requires a plan or Export Pass →</span>
                    @endif
                </form>

                <a href="{{ route('analyses.design-directions', $run->public_id) }}" class="flex flex-col gap-3 rounded-2xl border-2 border-line bg-surface p-6">
                    <p class="text-base font-bold text-ink">Continue improving</p>
                    <p class="text-[13px] leading-relaxed text-body">Keep this as a draft and explore another design direction or refinement.</p>
                    <span class="mt-auto text-xs font-semibold text-muted">Free →</span>
                </a>
            </div>

            @unless ($user->canPublish() && $user->canExportStatic())
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-line bg-surface p-5">
                    <p class="text-[13px] text-body">Don't want a subscription? @php $exp = app(\App\Services\Billing\Billing::class)->exportPassPricing(); @endphp Buy a one-time Export Pass (<x-export-pass-price :pricing="$exp" />{{ $exp['offer'] ? ', launch offer' : '' }}) for a single production download.</p>
                    <form method="POST" action="{{ route('export-pass.purchase') }}">
                        @csrf
                        <input type="hidden" name="run" value="{{ $run->public_id }}">
                        <button type="submit" class="shrink-0 rounded-lg border border-line px-4 py-2 text-xs font-semibold text-ink hover:border-line-strong">
                            Get Export Pass
                        </button>
                    </form>
                </div>
            @endunless
        @endif

        {{-- What you're approving: the real page, rendered in a browser at desktop and phone width --}}
        <section class="flex flex-col gap-4" aria-labelledby="shots-h">
            <div>
                <h2 id="shots-h" class="text-lg font-extrabold tracking-tight text-ink">What you’re approving</h2>
                <p class="text-[13px] text-muted">Your current design (v{{ $spec->version }}) as visitors will see it.</p>
            </div>
            @if (! empty($shots['desktop']) || ! empty($shots['mobile']))
                <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                    @if (! empty($shots['desktop']))
                        <figure class="overflow-hidden rounded-2xl border border-line bg-surface shadow-sm">
                            <figcaption class="flex items-center gap-2 border-b border-line bg-surface-2 px-4 py-2 text-[12px] font-bold uppercase tracking-wider text-muted"><span class="flex gap-1" aria-hidden="true"><i class="h-2 w-2 rounded-full bg-line-strong"></i><i class="h-2 w-2 rounded-full bg-line-strong"></i><i class="h-2 w-2 rounded-full bg-line-strong"></i></span>Website</figcaption>
                            <div class="max-h-[560px] overflow-y-auto"><img src="{{ $shots['desktop'] }}" alt="Desktop screenshot of your redesigned website" class="block w-full" loading="lazy"></div>
                        </figure>
                    @endif
                    @if (! empty($shots['mobile']))
                        <figure class="mx-auto w-[280px] max-w-full">
                            <figcaption class="mb-2 text-center text-[12px] font-bold uppercase tracking-wider text-muted">Mobile</figcaption>
                            <div class="overflow-hidden rounded-[2rem] border-[6px] border-ink bg-surface shadow-lg"><div class="max-h-[560px] overflow-y-auto"><img src="{{ $shots['mobile'] }}" alt="Mobile screenshot of your redesigned website" class="block w-full" loading="lazy"></div></div>
                        </figure>
                    @endif
                </div>
            @else
                <div id="shots-preparing" class="flex flex-col items-center gap-3 rounded-2xl border border-line bg-surface px-6 py-12 text-center" data-run-url="{{ route('analyses.preview.visual-qa.run', [$run->public_id, $spec->version]) }}" data-status-base="{{ url('/analyses/'.$run->public_id.'/visual-qa') }}" data-pending="{{ $qaPending?->id }}">
                    <svg class="h-7 w-7 animate-spin text-accent-ink" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <p class="text-sm font-semibold text-ink">Capturing the website and mobile views…</p>
                    <p id="shots-error" class="hidden rounded-lg bg-bad-soft px-4 py-2 text-[13px] text-bad"></p>
                </div>
                <script>
                (function () {
                    const prep = document.getElementById('shots-preparing'); if (!prep) return;
                    const fail = (m) => { const e = document.getElementById('shots-error'); e.textContent = m; e.classList.remove('hidden'); };
                    const poll = async (id) => {
                        for (let i = 0; i < 90; i++) {
                            await new Promise((r) => setTimeout(r, 2500));
                            const d = await (await fetch(prep.dataset.statusBase + '/' + id, { headers: { Accept: 'application/json' } })).json();
                            if (d.status === 'complete') return location.reload();
                            if (d.status === 'failed') return fail(d.error_message || 'Could not capture the screenshots.');
                        }
                        fail('This is taking longer than expected. Please refresh in a minute.');
                    };
                    (async () => {
                        if (prep.dataset.pending) return poll(prep.dataset.pending);
                        const res = await fetch(prep.dataset.runUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': @json(csrf_token()), Accept: 'application/json' } });
                        if (!res.ok) return fail('Could not start the capture.');
                        poll((await res.json()).report_id);
                    })();
                })();
                </script>
            @endif
        </section>
    </div>
@endsection
