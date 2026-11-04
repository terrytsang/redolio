@extends('layouts.marketing')

@section('title', 'Log in — REDOLIO')

@section('content')
    <div class="flex min-h-screen flex-col items-center justify-center px-6 py-16">
        <a href="{{ route('landing') }}" class="mb-8 text-xl font-extrabold tracking-tight text-ink"><x-brand /></a>

        <div class="w-full max-w-sm rounded-2xl border border-line bg-surface p-8">
            <h1 class="text-xl font-bold text-ink">Log in</h1>
            <p class="mt-1 text-sm text-faint">{{ ! empty($pendingHost) ? 'Log in and we’ll analyse '.$pendingHost.' right away.' : 'Welcome back. Let\'s see how your sites are doing.' }}</p>
            @if (\App\Http\Controllers\Auth\GoogleAuthController::configured())
                <div class="mt-5"><x-google-button label="signin_with" :claim="$claimRun" /></div>
                <div class="my-4 flex items-center gap-3 text-[11px] font-semibold uppercase tracking-wider text-faint"><span class="h-px flex-1 bg-line"></span>or<span class="h-px flex-1 bg-line"></span></div>
            @endif

            @if ($errors->any())
                <div class="mt-5 rounded-lg bg-red-500/10 px-4 py-3 text-sm text-bad">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
                @csrf
                @if ($claimRun)
                    <input type="hidden" name="claim_run" value="{{ $claimRun }}">
                @endif
                <div>
                    <label for="email" class="text-xs font-medium text-muted">Email</label>
                    <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                           class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label for="password" class="text-xs font-medium text-muted">Password</label>
                    <input id="password" name="password" type="password" required
                           class="mt-1.5 w-full rounded-lg border border-line-strong bg-surface-2 px-3.5 py-2.5 text-sm text-ink focus:border-accent focus:outline-none">
                </div>
                <label class="flex items-center gap-2 text-xs text-muted">
                    <input type="checkbox" name="remember" class="h-3.5 w-3.5 accent-accent"> Remember me
                </label>
                <button type="submit" class="mt-2 w-full rounded-full bg-accent py-3 text-sm font-semibold text-white hover:bg-accent">
                    Log in
                </button>
            </form>
        </div>

        <p class="mt-6 text-sm text-faint">
            Don't have an account? <a href="{{ route('register', $claimRun ? ['claim' => $claimRun] : []) }}" class="font-medium text-accent-ink hover:text-ink">Start free</a>
        </p>
    </div>
@endsection
