@extends('layouts.app')

@section('title', 'Add user — REDOLIO Admin')

@section('content')
    @include('admin.partials.header', ['active' => 'users'])

    <main class="mx-auto max-w-xl px-8 py-10">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('admin.users.index') }}" class="text-muted hover:text-ink">Users</a>
            <span class="text-line-strong">/</span>
            <span class="font-medium text-ink">Add user</span>
        </div>
        <h1 class="mt-2 text-2xl font-bold text-ink">Add user</h1>

        <form method="POST" action="{{ route('admin.users.store') }}" class="mt-6 flex flex-col gap-5 rounded-xl border border-line bg-surface p-6">
            @csrf
            @include('admin.users._form')

            <button type="submit" class="self-start rounded-full bg-ink px-5 py-2.5 text-sm font-semibold text-canvas hover:opacity-85">
                Create user
            </button>
        </form>
    </main>
@endsection
