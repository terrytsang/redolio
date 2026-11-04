@extends('layouts.app')

@section('title', 'Profile — REDOLIO')
@section('no_float_toggle', '1')

@section('content')
    <x-app-header active="profile" />
    <div class="relative">
        <div class="page-glow"></div>
        <main class="relative mx-auto max-w-3xl px-5 pb-24 pt-10 sm:px-8">
            <a href="{{ route('dashboard') }}" class="text-[13px] font-semibold text-muted hover:text-ink">&larr; My websites</a>
            <h1 class="mt-3 text-[34px] font-black leading-[1.1] tracking-tight text-ink sm:text-[40px]">Your <span class="grad-text">profile</span></h1>
            <p class="mt-2 text-[15px] text-muted">The details you signed up with. Change them any time.</p>

            @if (session('status'))
                <div class="mt-6 rounded-xl bg-ok-soft px-4 py-3 text-sm text-ok" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('profile.update') }}" class="card mt-8 p-7 sm:p-8" novalidate>
                @csrf @method('PATCH')
                <h2 class="text-lg font-extrabold text-ink">Account details</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="name" class="text-[13px] font-semibold text-body">Name</label>
                        <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $user->name) }}" autocomplete="name" class="field mt-1.5 @error('name') !border-bad @enderror" @error('name') aria-invalid="true" aria-describedby="name-err" @enderror>
                        @error('name')<p id="name-err" class="mt-1.5 text-xs text-bad">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="company_name" class="text-[13px] font-semibold text-body">Company <span class="font-normal text-faint">(optional)</span></label>
                        <input id="company_name" name="company_name" type="text" maxlength="255" value="{{ old('company_name', $user->company_name) }}" autocomplete="organization" class="field mt-1.5 @error('company_name') !border-bad @enderror">
                        @error('company_name')<p class="mt-1.5 text-xs text-bad">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="email" class="text-[13px] font-semibold text-body">Email</label>
                        <input id="email" name="email" type="email" required maxlength="255" value="{{ old('email', $user->email) }}" autocomplete="email" class="field mt-1.5 @error('email') !border-bad @enderror" @error('email') aria-invalid="true" aria-describedby="email-err" @enderror>
                        @error('email')<p id="email-err" class="mt-1.5 text-xs text-bad">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="mt-6 flex items-center justify-between gap-4">
                    <p class="text-[12.5px] text-faint">Plan: <a href="{{ route('plan.show') }}" class="font-semibold text-accent-ink hover:underline">{{ $plan?->name ?? 'None' }}</a></p>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>

            <form method="POST" action="{{ route('profile.password') }}" class="card mt-6 p-7 sm:p-8" novalidate>
                @csrf @method('PUT')
                <h2 class="text-lg font-extrabold text-ink">Change password</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="current_password" class="text-[13px] font-semibold text-body">Current password</label>
                        <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="field mt-1.5 sm:max-w-sm @error('current_password', 'password') !border-bad @enderror">
                        @error('current_password', 'password')<p class="mt-1.5 text-xs text-bad">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password" class="text-[13px] font-semibold text-body">New password</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="field mt-1.5 @error('password', 'password') !border-bad @enderror">
                        @error('password', 'password')<p class="mt-1.5 text-xs text-bad">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="text-[13px] font-semibold text-body">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="field mt-1.5">
                    </div>
                </div>
                <div class="mt-6 flex justify-end"><button type="submit" class="btn btn-dark">Update password</button></div>
            </form>
        </main>
    </div>
@endsection
