@extends('layouts.app')

@php
    use Illuminate\Support\Str;
@endphp

@section('title', 'My Websites — REDOLIO')
@section('no_float_toggle', '1')

@section('content')
    <x-app-header active="dashboard" />
    <div class="relative">
        <div class="page-glow"></div>
        <main class="relative mx-auto max-w-7xl px-5 pb-20 pt-10 sm:px-8">
            {{-- Greeting + new redo --}}
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="text-[12px] font-bold uppercase tracking-[0.2em] text-accent-ink">Welcome back{{ $user->name ? ', '.Str::before($user->name, ' ') : '' }}</p>
                    <h1 class="mt-1.5 text-[34px] font-black leading-[1.1] tracking-tight text-ink sm:text-[40px]">My <span class="grad-text">websites</span></h1>
                    <p class="mt-2 max-w-xl text-[15px] text-muted">Every site you've redone, each one a click away from its before &amp; after.</p>
                </div>
                <form method="POST" action="{{ route('analyses.store') }}" class="flex w-full max-w-lg items-stretch gap-2 sm:w-auto">
                    @csrf
                    <label for="dashboard-redo-url" class="sr-only">Website URL</label>
                    <input id="dashboard-redo-url" name="url" type="text" required placeholder="https://yourwebsite.com" value="{{ old('url') }}" class="field min-w-0 flex-1 rounded-full px-5 sm:w-72">
                    <button type="submit" class="btn btn-primary shrink-0 px-5">+ Redo a website</button>
                </form>
            </div>
            @error('url')
                <p class="mt-2 text-right text-xs text-bad">{{ $message }}</p>
            @enderror

            @if (session('status'))
                <div class="mt-6 rounded-xl bg-accent-soft px-4 py-3 text-sm text-accent-ink">{{ session('status') }}</div>
            @endif

            {{-- Current plan: obvious, and a way in to every other plan --}}
            @if ($plan)
                @php
                    $limit = $plan->site_limit;
                    $pct = $limit ? min(100, (int) round($watching / max(1, $limit) * 100)) : 0;
                @endphp
                <a href="{{ route('plan.show') }}" class="card card-lift group relative mt-8 flex flex-wrap items-center justify-between gap-6 overflow-hidden p-6 sm:p-7" aria-label="View your {{ $plan->name }} plan and the other plans">
                    <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-accent/15 blur-3xl"></div>
                    <div class="relative flex items-center gap-4">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-violet-600 to-fuchsia-500 text-white shadow-lg">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.9 6.6 7.1.6-5.4 4.7 1.6 7L12 17.3 5.8 21l1.6-7L2 9.2l7.1-.6z"/></svg>
                        </span>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-faint">Your current plan</p>
                            <p class="mt-0.5 flex items-baseline gap-2 text-[26px] font-black leading-none tracking-tight text-ink">{{ $plan->name }}
                                <span class="text-sm font-semibold text-muted">{{ $plan->price_monthly_cents ? '$'.number_format($plan->priceMonthly(), 0).'/mo' : 'Free' }}</span>
                            </p>
                            <p class="mt-1.5 text-[13px] text-muted">{{ $plan->tagline }}</p>
                        </div>
                    </div>
                    <div class="relative flex min-w-[220px] flex-1 flex-col gap-3 sm:max-w-xs">
                        @if ($analysisLimit)
                            @php $apct = min(100, (int) round($analysesUsed / max(1, $analysisLimit) * 100)); @endphp
                            <div>
                                <div class="flex items-baseline justify-between text-[12px]"><span class="font-semibold text-body">Website analyses</span><span class="{{ $analysesUsed >= $analysisLimit ? 'font-bold text-warn' : 'text-muted' }}">{{ $analysesUsed }} of {{ $analysisLimit }}</span></div>
                                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-2" role="progressbar" aria-label="Website analyses used" aria-valuenow="{{ $analysesUsed }}" aria-valuemin="0" aria-valuemax="{{ $analysisLimit }}"><div class="h-full rounded-full bg-gradient-to-r from-violet-600 to-fuchsia-500" style="width: {{ $apct }}%"></div></div>
                            </div>
                        @endif
                        @if ($limit)
                            <div class="flex items-baseline justify-between text-[12px]"><span class="font-semibold text-body">Watched websites</span><span class="text-muted">{{ $watching }} of {{ $limit }}</span></div>
                            <div class="h-2 overflow-hidden rounded-full bg-surface-2" role="progressbar" aria-valuenow="{{ $watching }}" aria-valuemin="0" aria-valuemax="{{ $limit }}"><div class="h-full rounded-full bg-gradient-to-r from-violet-600 to-fuchsia-500" style="width: {{ $pct }}%"></div></div>
                        @else
                            <p class="text-[12px] font-semibold text-body">Unlimited watched websites</p>
                        @endif
                    </div>
                    <span class="btn btn-ghost relative group-hover:border-accent group-hover:text-accent-ink">See plans, upgrade or downgrade <span aria-hidden="true">&rarr;</span></span>
                </a>
            @endif

            @include('partials.watches')

            {{-- Recent analyses: grid of cards --}}
            <section class="mt-12" aria-labelledby="recent-h">
                <div class="flex items-end justify-between">
                    <h2 id="recent-h" class="text-xl font-extrabold tracking-tight text-ink">Recent analyses</h2>
                    @if (count($cards))<span class="text-[13px] text-faint">{{ count($cards) }} {{ Str::plural('website', count($cards)) }}</span>@endif
                </div>

                @if (! count($cards))
                    <div class="card mt-5 border-dashed px-6 py-16 text-center">
                        <p class="text-lg font-bold text-ink">You haven't redone a website yet</p>
                        <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Paste an address above and REDOLIO will show you a redesigned version next to the original.</p>
                        <button type="button" onclick="document.getElementById('dashboard-redo-url').focus()" class="btn btn-primary mt-5">Start your first redo &rarr;</button>
                    </div>
                @else
                    <div class="mt-5 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($cards as $c)
                            @php
                                $run = $c['run'];
                                $pill = match ($run->status) { 'complete', 'partial' => ['pill-ok', 'Ready'], 'failed' => ['pill-bad', 'Failed'], default => ['pill-accent', 'In progress'] };
                            @endphp
                            @php $pillC = $c['completed'] ? ['pill-ok', '✓ Completed'] : $pill; @endphp
                            <article class="card card-lift group relative flex flex-col overflow-hidden {{ $c['completed'] ? 'ring-1 ring-ok/40' : '' }}">
                            @if ($c['editable'])
                                {{-- On hover (always visible on touch screens) the screenshot offers "Edit the design" --}}
                                <div class="pointer-events-none absolute inset-x-0 top-0 z-10 grid aspect-[16/10] place-items-center bg-ink/35 opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100 [@media(hover:none)]:place-items-end [@media(hover:none)]:justify-items-end [@media(hover:none)]:bg-transparent [@media(hover:none)]:p-3 [@media(hover:none)]:opacity-100">
                                    <a href="{{ route('analyses.edit', $run->public_id) }}" class="btn btn-primary pointer-events-auto !px-5 !py-2.5 text-[13px] shadow-lg">✎ Edit the design</a>
                                </div>
                            @endif
                            <a href="{{ $c['href'] }}" class="block" aria-label="{{ $c['title'] }} ({{ $c['host'] }}) — {{ $pillC[1] }}">
                                {{-- header: the REDO preview --}}
                                <div class="shot-frame aspect-[16/10]">
                                    @if ($c['image'])
                                        <img src="{{ $c['image'] }}" alt="{{ $c['kind'] === 'redo' ? 'REDO preview of '.$c['host'] : 'Original '.$c['host'] }}" loading="lazy" class="h-full w-full object-cover object-top">
                                    @else
                                        <div class="grid h-full place-items-center text-center">
                                            <div>
                                                <span class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-surface text-accent-ink shadow-sm"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M3 9h18"/></svg></span>
                                                <p class="mt-2 text-xs font-semibold text-muted">{{ in_array($run->status, ['failed'], true) ? 'No preview — the scan didn\'t finish' : 'Preview on its way…' }}</p>
                                            </div>
                                        </div>
                                    @endif
                                    <span class="pill {{ $pillC[0] }} absolute right-3 top-3 shadow-sm backdrop-blur">{{ $pillC[1] }}</span>
                                    @if ($c['completed'])<span class="pill absolute left-3 top-3 bg-ink/85 text-canvas shadow-sm backdrop-blur">Completed website REDO</span>
                                    @elseif ($c['kind'] === 'redo')<span class="pill absolute left-3 top-3 bg-ink/85 text-canvas shadow-sm backdrop-blur">REDO</span>
                                    @elseif ($c['kind'] === 'original')<span class="pill absolute left-3 top-3 bg-surface/90 text-body shadow-sm backdrop-blur">Original</span>@endif
                                </div>
                                {{-- footer: title + URL --}}
                                <div class="flex items-center justify-between gap-3 border-t border-line px-5 py-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-[15px] font-bold tracking-tight text-ink group-hover:text-accent-ink">{{ $c['title'] }}</p>
                                        <p class="mt-0.5 truncate text-[12.5px] text-muted">{{ $c['host'] }}</p>
                                    </div>
                                    <p class="shrink-0 text-[11.5px] text-faint">{{ $run->created_at->diffForHumans(null, true, true) }}</p>
                                </div>
                            </a>
                            @if (! empty($c['pass']))
                                <p class="border-t border-line bg-accent-soft px-5 py-1.5 text-[11.5px] font-medium text-accent-ink">Export Pass · {{ $c['pass']['edits'] }} saved {{ \Illuminate\Support\Str::plural('edit', $c['pass']['edits']) }} · {{ $c['pass']['downloads'] }} {{ \Illuminate\Support\Str::plural('download', $c['pass']['downloads']) }} left</p>
                            @endif
                            <div class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line bg-surface-2/60 px-5 py-2.5 text-[12.5px] font-semibold">
                                @if ($c['completed'])
                                    @if ($c['editable'])<a href="{{ route('analyses.edit', $run->public_id) }}" class="text-accent-ink hover:text-ink">✎ Edit layout</a>@endif
                                    <a href="{{ route('analyses.design-directions', $run->public_id) }}" class="text-body hover:text-ink">Change design</a>
                                    <a href="{{ route('analyses.reveal', $run->public_id) }}" class="text-body hover:text-ink">Before &amp; after</a>
                                    <a href="{{ route('analyses.launch', $run->public_id) }}" class="text-body hover:text-ink">Publish / download</a>
                                    @if ($c['unapproved_changes'])<span class="w-full text-[11.5px] font-medium text-warn">You’ve edited it since approving — approve again to publish the changes.</span>@endif
                                @elseif ($c['editable'])
                                    <a href="{{ route('analyses.edit', $run->public_id) }}" class="text-accent-ink hover:text-ink">✎ Edit layout</a>
                                    <a href="{{ route('analyses.reveal', $run->public_id) }}" class="text-body hover:text-ink">Before &amp; after</a>
                                @else
                                    <a href="{{ $c['href'] }}" class="text-accent-ink hover:text-ink">Open →</a>
                                @endif
                                <button type="button" class="ml-auto text-faint hover:text-bad" data-delete="{{ route('analyses.destroy', $run->public_id) }}" data-delete-name="{{ $c['host'] }}" data-delete-live="{{ $run->published_slug ? '1' : '' }}">Delete</button>
                            </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <dialog id="delete-dialog" class="m-auto w-[min(92vw,26rem)] rounded-2xl border border-line bg-surface p-0 text-ink shadow-2xl backdrop:bg-black/50">
                <form method="POST" id="delete-form" class="p-6">@csrf @method('DELETE')
                    <h2 class="text-lg font-extrabold tracking-tight">Delete this website analysis?</h2>
                    <p class="mt-2 text-[14px] leading-relaxed text-muted"><strong id="delete-name" class="text-ink"></strong> and everything built for it — designs, edits, screenshots and downloads — will be removed for good. <span id="delete-live" class="hidden font-semibold text-warn">Its published copy goes offline too.</span> It frees up a place on your plan.</p>
                    <div class="mt-6 flex justify-end gap-3"><button type="button" class="btn btn-ghost" id="delete-cancel">Keep it</button><button type="submit" class="btn bg-bad text-white hover:opacity-90">Delete it</button></div>
                </form>
            </dialog>
            <script>
                (function () {
                    const d = document.getElementById('delete-dialog'); if (!d || !d.showModal) return;
                    document.querySelectorAll('[data-delete]').forEach((b) => b.addEventListener('click', () => {
                        document.getElementById('delete-form').action = b.dataset.delete;
                        document.getElementById('delete-name').textContent = b.dataset.deleteName;
                        document.getElementById('delete-live').classList.toggle('hidden', !b.dataset.deleteLive);
                        d.showModal();
                    }));
                    document.getElementById('delete-cancel').addEventListener('click', () => d.close());
                })();
            </script>

        @if ($leads->isNotEmpty())
            <section class="mt-8">
                <h2 class="text-sm font-semibold text-ink">Leads</h2>
                <p class="mt-1 text-xs text-faint">Enquiries submitted through your redesigned previews.</p>
                <div class="mt-3 divide-y divide-line rounded-xl border border-line bg-surface">
                    @foreach ($leads as $lead)
                        <div class="flex items-center justify-between px-5 py-4">
                            <div>
                                <p class="text-sm font-medium text-ink">{{ $lead->name }} &middot; {{ $lead->email }}</p>
                                <p class="mt-0.5 text-xs text-faint">
                                    {{ ucfirst($lead->request_type) }} for {{ $lead->analysisRun->normalized_url }}
                                    &middot; {{ $lead->created_at->diffForHumans() }}
                                </p>
                                @if ($lead->message)
                                    <p class="mt-1 text-xs text-body">{{ Str::limit($lead->message, 120) }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 rounded-full bg-accent-soft px-3 py-1 text-xs font-semibold text-accent-ink">{{ ucfirst($lead->status) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        </main>
    </div>
@endsection
