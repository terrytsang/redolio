@extends('layouts.app')

@section('title', 'REDOLIO — Publish')

@section('content')
    <x-app-nav :run="$run" />
    <x-stage-bar active="redo" />

    <div class="mx-auto flex max-w-2xl flex-col gap-7 px-12 py-12">
        @if (session('status'))
            <div class="rounded-lg bg-accent-soft px-4 py-3 text-sm text-accent-ink">{{ session('status') }}</div>
        @endif

        <div class="flex flex-col gap-1.5">
            <h1 class="text-[22px] font-extrabold tracking-tight text-ink">Publish your website</h1>
            <p class="text-[13px] text-faint">Launch the approved design on a REDOLIO web address.</p>
        </div>

        @if ($run->published_slug && $run->activeDeployment?->isLive())
            <div class="flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-ok-soft p-6">
                <p class="text-sm font-semibold text-emerald-900">Your website is live</p>
                <a href="{{ route('sites.show', $run->published_slug) }}" target="_blank" class="w-fit text-sm font-semibold text-accent-ink underline">
                    {{ route('sites.show', $run->published_slug) }} ↗
                </a>
            </div>

            <form method="POST" action="{{ route('analyses.publish.store', $run->public_id) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-ink px-5 py-2.5 text-sm font-semibold text-canvas hover:opacity-85">
                    Republish latest design
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('analyses.publish.store', $run->public_id) }}" class="flex flex-col gap-3 rounded-2xl border border-line bg-surface p-6">
                @csrf
                <label for="slug" class="text-xs font-medium text-muted">Choose your web address</label>
                <div class="flex items-center gap-2">
                    <span class="shrink-0 text-sm text-faint">{{ parse_url(config('app.url'), PHP_URL_HOST) }}/sites/</span>
                    <input id="slug" name="slug" type="text" required value="{{ old('slug', $suggestedSlug) }}"
                           class="flex-1 rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
                </div>
                @error('slug')
                    <p class="text-xs text-bad">{{ $message }}</p>
                @enderror
                <button type="submit" class="mt-2 w-fit rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-white hover:bg-accent-strong">
                    Publish
                </button>
            </form>
        @endif

        @if ($deployments->isNotEmpty())
            <div>
                <h2 class="text-xs font-bold uppercase tracking-wide text-faint">Deployment history</h2>
                <div class="mt-3 divide-y divide-line rounded-xl border border-line bg-surface">
                    @foreach ($deployments as $deployment)
                        <div class="flex items-center justify-between px-5 py-4">
                            <div>
                                <p class="text-sm font-medium text-ink">
                                    Deployment #{{ $deployment->deployment_number }}
                                    <span class="ml-2 rounded-full px-2.5 py-0.5 text-[11px] font-semibold
                                        {{ match($deployment->status) {
                                            'live' => $run->active_deployment_id === $deployment->id ? 'bg-ok-soft text-ok' : 'bg-surface-2 text-muted',
                                            'failed' => 'bg-bad-soft text-bad',
                                            default => 'bg-surface-2 text-muted',
                                        } }}">
                                        {{ $run->active_deployment_id === $deployment->id ? 'Active' : ucfirst($deployment->status) }}
                                    </span>
                                </p>
                                <p class="mt-0.5 text-xs text-faint">
                                    v{{ $deployment->designSpec->version }} · {{ $deployment->created_at->diffForHumans() }}
                                </p>
                            </div>
                            @if ($deployment->status === 'live' && $run->active_deployment_id !== $deployment->id)
                                <form method="POST" action="{{ route('analyses.deployments.rollback', [$run->public_id, $deployment]) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-muted hover:text-ink">Roll back to this</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
