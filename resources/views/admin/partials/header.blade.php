@php
    $adminNav = [
        'dashboard' => ['label' => 'Dashboard', 'route' => 'admin.dashboard'],
        'users' => ['label' => 'Users', 'route' => 'admin.users.index'],
        'analytics' => ['label' => 'Analytics', 'route' => 'admin.analytics'],
        'scans' => ['label' => 'Scans', 'route' => 'admin.scans.index'],
        'design-intelligence' => ['label' => 'Design Intelligence', 'route' => 'admin.design-directions.index'],
        'settings' => ['label' => 'Settings', 'route' => 'admin.settings'],
    ];
@endphp

<header class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-b border-line bg-surface px-4 py-3 sm:px-8 lg:h-[60px] lg:py-0">
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
        <a href="{{ route('landing') }}" class="text-base font-extrabold tracking-tight text-ink"><x-brand /></a>
        <span class="hidden h-5 w-px bg-line sm:block"></span>
        <a href="{{ route('admin.dashboard') }}" class="text-[13px] font-medium text-muted hover:text-ink">Admin</a>
        <nav class="flex flex-wrap items-center gap-x-4 gap-y-1">
            @foreach ($adminNav as $key => $item)
                <a href="{{ route($item['route']) }}" class="text-[13px] font-medium {{ ($active ?? null) === $key ? 'text-ink' : 'text-muted hover:text-ink' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="text-[13px] font-medium text-muted hover:text-ink">Log out</button>
    </form>
</header>
