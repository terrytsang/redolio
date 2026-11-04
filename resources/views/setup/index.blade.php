@extends('layouts.marketing')

@section('title', 'Set up your REDOLIO installation')

@section('content')
    <div class="mx-auto max-w-xl px-6 py-16">
        <a href="{{ route('landing') }}" class="text-xl font-extrabold tracking-tight text-ink"><x-brand /></a>
        <p class="mt-1 text-xs font-medium tracking-wide text-accent-ink">SELF-HOSTED (MIT) SETUP</p>

        <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-ink">Set up your installation</h1>
        <p class="mt-2 text-sm leading-relaxed text-muted">
            Three steps: add your own Meta Model API key, try a REDO analysis to see it work, then create your admin
            account to save it and unlock the full dashboard.
        </p>

        @if (session('status'))
            <div class="mt-6 rounded-lg bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">{{ session('status') }}</div>
        @endif

        {{-- Step 1 --}}
        <div class="mt-8 rounded-2xl border border-line bg-surface p-6">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $apiKeySet ? 'bg-ok' : 'bg-accent' }} text-xs font-bold text-white">
                    {{ $apiKeySet ? '✓' : '1' }}
                </span>
                <h2 class="text-sm font-bold text-ink">Add your Meta Model API key</h2>
            </div>

            <form method="POST" action="{{ route('setup.api-key') }}" class="mt-4 flex flex-col gap-3">
                @csrf
                <div>
                    <label class="text-xs font-medium text-muted">API base URL</label>
                    <input name="meta_api_base_url" type="text" required value="{{ old('meta_api_base_url', $baseUrl) }}"
                           class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-ink focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label class="text-xs font-medium text-muted">Model ID</label>
                    <input name="meta_api_model_id" type="text" required value="{{ old('meta_api_model_id', $modelId) }}"
                           class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-ink focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label class="text-xs font-medium text-muted">API key</label>
                    <input name="meta_api_key" type="password" {{ $apiKeySet ? '' : 'required' }}
                           placeholder="{{ $apiKeySet ? '•••••••••••••••• (set — leave blank to keep it)' : 'Paste your API key' }}"
                           class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-accent focus:outline-none">
                </div>
                <button type="submit" class="mt-1 self-start rounded-full bg-accent px-5 py-2.5 text-sm font-semibold text-white hover:bg-accent">
                    Save key
                </button>
            </form>
        </div>

        {{-- Step 2 --}}
        <div class="mt-5 rounded-2xl border border-line bg-surface p-6 {{ $apiKeySet ? '' : 'opacity-50' }}">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-accent text-xs font-bold text-white">2</span>
                <h2 class="text-sm font-bold text-ink">Try a REDO analysis</h2>
            </div>
            <p class="mt-2 text-xs text-muted">Run the full journey — crawl, Website DNA, diagnosis, redesign preview — on any website.</p>

            @if ($apiKeySet)
                <form method="POST" action="{{ route('analyses.store') }}" class="mt-4 flex gap-2">
                    @csrf
                    <input type="text" name="url" required placeholder="https://yourwebsite.com"
                           class="flex-1 rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-accent focus:outline-none">
                    <button type="submit" class="shrink-0 rounded-full bg-ink px-5 py-2.5 text-sm font-bold text-canvas hover:opacity-85">
                        Analyse →
                    </button>
                </form>
            @else
                <p class="mt-4 text-xs text-faint">Save your API key above first.</p>
            @endif
        </div>

        {{-- Step 3 --}}
        <div class="mt-5 rounded-2xl border border-line bg-surface p-6">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-accent text-xs font-bold text-white">3</span>
                <h2 class="text-sm font-bold text-ink">Create your admin account</h2>
            </div>
            <p class="mt-2 text-xs text-muted">
                This saves your REDO result and unlocks the full dashboard and admin portal for this installation.
                @if ($claimRun)
                    The analysis you just ran will be saved to this account.
                @endif
            </p>

            <form method="POST" action="{{ route('setup.admin') }}" class="mt-4 flex flex-col gap-3">
                @csrf
                @if ($claimRun)
                    <input type="hidden" name="claim_run" value="{{ $claimRun }}">
                @endif
                <input name="name" type="text" required placeholder="Your name"
                       class="rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-accent focus:outline-none">
                <input name="email" type="email" required placeholder="Email address"
                       class="rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-accent focus:outline-none">
                <input name="password" type="password" required placeholder="Password"
                       class="rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-accent focus:outline-none">
                <input name="password_confirmation" type="password" required placeholder="Confirm password"
                       class="rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-accent focus:outline-none">
                <button type="submit" class="mt-1 self-start rounded-full bg-accent px-5 py-2.5 text-sm font-semibold text-white hover:bg-accent">
                    Create admin account
                </button>
            </form>
        </div>
    </div>
@endsection
