@extends('layouts.app')

@section('title', 'Edit user — REDOLIO Admin')

@section('content')
    @include('admin.partials.header', ['active' => 'users'])

    <main class="mx-auto max-w-xl px-8 py-10">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('admin.users.index') }}" class="text-muted hover:text-ink">Users</a>
            <span class="text-line-strong">/</span>
            <span class="font-medium text-ink">{{ $editedUser->name }}</span>
        </div>
        <h1 class="mt-2 text-2xl font-bold text-ink">Edit user</h1>

        <form method="POST" action="{{ route('admin.users.update', $editedUser) }}" class="mt-6 flex flex-col gap-5 rounded-xl border border-line bg-surface p-6">
            @csrf
            @method('PUT')
            @include('admin.users._form')

            <button type="submit" class="self-start rounded-full bg-ink px-5 py-2.5 text-sm font-semibold text-canvas hover:opacity-85">
                Save changes
            </button>
        </form>

        <section class="mt-8">
            <h2 class="text-base font-bold text-ink">Subscriptions</h2>
            <div class="mt-3 overflow-x-auto rounded-xl border border-line bg-surface">
                <table class="w-full min-w-[420px] text-left text-sm">
                    <tbody class="divide-y divide-line">
                        @forelse ($subscriptions as $s)
                            <tr>
                                <td class="px-5 py-3 font-medium text-ink">{{ $s->plan->name }}</td>
                                <td class="px-3 py-3 text-xs"><span class="rounded-full {{ $s->status === 'active' ? 'bg-ok-soft text-ok' : 'bg-surface-2 text-muted' }} px-2.5 py-0.5 font-semibold">{{ ucfirst($s->status) }}</span></td>
                                <td class="px-5 py-3 text-right text-xs text-faint">since {{ $s->started_at?->format('M j, Y') }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-5 py-6 text-center text-sm text-faint">No subscriptions.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h2 class="mt-8 text-base font-bold text-ink">Payments</h2>
            <div class="mt-3 overflow-x-auto rounded-xl border border-line bg-surface">
                <table class="w-full min-w-[420px] text-left text-sm">
                    <tbody class="divide-y divide-line">
                        @forelse ($transactions as $t)
                            <tr>
                                <td class="px-5 py-3 text-ink">{{ $t->description }}</td>
                                <td class="px-3 py-3 font-semibold text-ink">${{ number_format($t->amount_cents / 100, 2) }}</td>
                                <td class="px-5 py-3 text-right text-xs text-faint">{{ $t->paid_at?->format('M j, Y') }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-5 py-6 text-center text-sm text-faint">No payments yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
@endsection
