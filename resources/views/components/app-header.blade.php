@props(['active' => 'dashboard'])
@php
    $u = auth()->user();
    $plan = $u?->currentPlan();
    $initial = mb_strtoupper(mb_substr(trim($u?->name ?: 'U'), 0, 1));
    $link = fn ($key) => $active === $key ? 'text-ink bg-surface-2' : 'text-muted hover:text-ink hover:bg-surface-2';
@endphp
<header class="sticky top-0 z-40 border-b border-line bg-canvas/80 backdrop-blur-xl">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-5 sm:px-8">
        <div class="flex items-center gap-6">
            <a href="{{ route('landing') }}" class="flex items-center gap-2 text-[17px] font-black tracking-tight text-ink">
                <x-brand />
            </a>
            <nav class="hidden items-center gap-1 sm:flex" aria-label="Main">
                <a href="{{ route('dashboard') }}" class="rounded-full px-3.5 py-1.5 text-[13px] font-semibold {{ $link('dashboard') }}">My websites</a>
                <a href="{{ route('plan.show') }}" class="rounded-full px-3.5 py-1.5 text-[13px] font-semibold {{ $link('plan') }}">Plan &amp; billing</a>
                <a href="{{ route('profile.edit') }}" class="rounded-full px-3.5 py-1.5 text-[13px] font-semibold {{ $link('profile') }}">Profile</a>
            </nav>
        </div>
        <div class="flex items-center gap-2.5">
            @if ($plan)
                <a href="{{ route('plan.show') }}" class="pill pill-accent hidden transition hover:brightness-95 sm:inline-flex" title="See your plan and the other plans">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.9 6.6 7.1.6-5.4 4.7 1.6 7L12 17.3 5.8 21l1.6-7L2 9.2l7.1-.6z"/></svg>{{ $plan->name }} plan
                </a>
            @endif
            <x-theme-toggle />
            <details class="group relative">
                <summary class="grid h-9 w-9 cursor-pointer list-none place-items-center rounded-full bg-gradient-to-br from-violet-600 to-fuchsia-500 text-[13px] font-bold text-white shadow-sm [&::-webkit-details-marker]:hidden" aria-label="Account menu">{{ $initial }}</summary>
                <div class="card absolute right-0 mt-2 w-60 p-2 text-[13px]">
                    <div class="border-b border-line px-3 pb-2.5 pt-1.5">
                        <p class="truncate font-semibold text-ink">{{ $u->name }}</p>
                        <p class="truncate text-xs text-faint">{{ $u->email }}</p>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="mt-1 block rounded-lg px-3 py-2 text-body hover:bg-surface-2">Profile &amp; password</a>
                    <a href="{{ route('plan.show') }}" class="block rounded-lg px-3 py-2 text-body hover:bg-surface-2">Plan &amp; billing</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-body hover:bg-surface-2">Log out</button>
                    </form>
                </div>
            </details>
        </div>
    </div>
    <nav class="flex gap-1 overflow-x-auto border-t border-line px-4 py-1.5 sm:hidden" aria-label="Main">
        <a href="{{ route('dashboard') }}" class="rounded-full px-3 py-1.5 text-[12px] font-semibold {{ $link('dashboard') }}">My websites</a>
        <a href="{{ route('plan.show') }}" class="rounded-full px-3 py-1.5 text-[12px] font-semibold {{ $link('plan') }}">Plan</a>
        <a href="{{ route('profile.edit') }}" class="rounded-full px-3 py-1.5 text-[12px] font-semibold {{ $link('profile') }}">Profile</a>
    </nav>
</header>
