@extends('layouts.app')

@section('title', 'Users — REDOLIO Admin')

@section('content')
    @include('admin.partials.header', ['active' => 'users'])

    <main class="mx-auto max-w-6xl px-8 py-10">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-ink">Users</h1>
                <p class="mt-1 text-sm text-muted">{{ $users->total() }} signed-up user(s).</p>
            </div>
            <a href="{{ route('admin.users.create') }}" class="rounded-full bg-ink px-5 py-2.5 text-sm font-semibold text-canvas hover:opacity-85">
                Add user
            </a>
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-lg bg-ok-soft px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
        @endif

        <form method="GET" class="mt-6 flex flex-wrap items-center gap-3">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search name or email"
                   class="w-64 rounded-lg border border-line px-3.5 py-2 text-sm focus:border-accent focus:outline-none">
            <select name="role" class="rounded-lg border border-line px-3.5 py-2 text-sm focus:border-accent focus:outline-none">
                <option value="" @selected($role === '')>All roles</option>
                <option value="user" @selected($role === 'user')>User</option>
                <option value="admin" @selected($role === 'admin')>Admin</option>
            </select>
            <button type="submit" class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink hover:border-line-strong">
                Filter
            </button>
        </form>

        <div class="mt-6 overflow-x-auto rounded-xl border border-line bg-surface">
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead class="border-b border-line bg-surface-2 text-xs font-semibold uppercase tracking-wide text-faint">
                    <tr>
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Email</th>
                        <th class="px-5 py-3">Role</th>
                        <th class="px-5 py-3">Plan</th>
                        <th class="px-5 py-3">Scans</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Joined</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-5 py-3 font-medium text-ink">{{ $user->name }}</td>
                            <td class="px-5 py-3 text-body">{{ $user->email }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full {{ $user->role === 'admin' ? 'bg-accent-soft text-accent-ink' : 'bg-surface-2 text-muted' }} px-2.5 py-0.5 text-xs font-semibold">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-body">{{ $user->subscriptions->first()?->plan?->name ?? 'Free' }}</td>
                            <td class="px-5 py-3 text-body">{{ $user->analysis_runs_count }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full {{ $user->is_active ? 'bg-ok-soft text-ok' : 'bg-bad-soft text-bad' }} px-2.5 py-0.5 text-xs font-semibold">
                                    {{ $user->is_active ? 'Active' : 'Deactivated' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-faint">{{ $user->created_at->format('M j, Y') }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-xs font-semibold text-accent-ink hover:text-accent-ink">Edit</a>
                                    <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold {{ $user->is_active ? 'text-bad hover:text-bad' : 'text-ok hover:text-ok' }}">
                                            {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-sm text-faint">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $users->links() }}</div>
    </main>
@endsection
