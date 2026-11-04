@extends('layouts.marketing')

@section('title', 'Start free — REDOLIO')

@section('content')
    <div class="relative flex min-h-screen flex-col items-center justify-center px-6 py-14">
        <div class="page-glow"></div>
        <a href="{{ route('landing') }}" class="relative mb-7 text-xl font-extrabold tracking-tight text-ink"><x-brand /></a>

        <div class="card relative w-full max-w-md p-7 sm:p-9">
            @if ($pendingHost)
                <div class="mb-5 flex items-center gap-3 rounded-xl bg-accent-soft px-4 py-3 text-[13px] text-accent-ink">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-accent text-white">✓</span>
                    <span>One quick step, then we’ll analyse <strong class="break-all">{{ $pendingHost }}</strong> and build your REDO preview.</span>
                </div>
            @endif
            <h1 class="text-[22px] font-extrabold tracking-tight text-ink">{{ $pendingHost ? 'Create your free account' : 'Start free' }}</h1>
            <p class="mt-1 text-sm text-muted">No credit card. See your redesigned website in a couple of minutes.</p>
            @if (! empty($remixDirection))
                <p class="mt-3 rounded-lg bg-accent-soft px-3 py-2 text-xs text-accent-ink">You'll be able to apply that design direction once you've redone a website.</p>
            @endif

            @if ($errors->any())
                <div class="mt-5 rounded-lg bg-bad-soft px-4 py-3 text-sm text-bad">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
            @if (session('status'))
                <p class="mt-5 text-[13px] text-muted">{{ session('status') }}</p>
            @endif

            @if (\App\Http\Controllers\Auth\GoogleAuthController::configured())
                <div class="mt-6"><x-google-button label="signup_with" :claim="$claimRun" :remix="$remixDirection ?? null" /></div>
                <div class="my-5 flex items-center gap-3 text-[11px] font-semibold uppercase tracking-wider text-faint"><span class="h-px flex-1 bg-line"></span>or with email<span class="h-px flex-1 bg-line"></span></div>
            @else
                <div class="mt-6"></div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-3.5">
                @csrf
                @if ($claimRun)<input type="hidden" name="claim_run" value="{{ $claimRun }}">@endif
                @if (! empty($remixDirection))<input type="hidden" name="remix_direction" value="{{ $remixDirection }}">@endif
                <div>
                    <label for="email" class="text-xs font-medium text-muted">Email</label>
                    <input id="email" name="email" type="email" required autofocus autocomplete="email" value="{{ old('email') }}" class="field mt-1.5" placeholder="you@company.com">
                </div>
                <div>
                    <label for="password" class="text-xs font-medium text-muted">Password <span class="text-faint">(8+ characters)</span></label>
                    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="field mt-1.5">
                </div>
                <button type="submit" class="btn btn-primary mt-1 w-full py-3">{{ $pendingHost ? 'Create account & analyse my website →' : 'Create free account' }}</button>
            </form>
        </div>

        <p class="relative mt-6 text-sm text-muted">Already have an account? <a href="{{ route('login', $claimRun ? ['claim' => $claimRun] : []) }}" class="font-semibold text-accent-ink hover:text-ink">Log in</a></p>
    </div>
@endsection
