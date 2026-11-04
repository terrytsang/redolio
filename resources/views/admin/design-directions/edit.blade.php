@extends('layouts.app')

@section('title', 'Edit design direction — REDOLIO Admin')

@section('content')
    @include('admin.partials.header', ['active' => 'design-intelligence'])

    <main class="mx-auto max-w-2xl px-8 py-10">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('admin.design-directions.index') }}" class="text-muted hover:text-ink">Design Intelligence</a>
            <span class="text-line-strong">/</span>
            <span class="font-medium text-ink">{{ $direction->name }}</span>
        </div>
        <h1 class="mt-2 text-2xl font-bold text-ink">Edit "{{ $direction->name }}"</h1>

        @if (session('status'))
            <div class="mt-4 rounded-lg bg-ok-soft px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.design-directions.update', $direction) }}" class="mt-6 flex flex-col gap-5 rounded-xl border border-line bg-surface p-6">
            @csrf
            @method('PUT')
            @include('admin.design-directions._form')

            <button type="submit" class="self-start rounded-full bg-ink px-5 py-2.5 text-sm font-semibold text-canvas hover:opacity-85">
                Save changes
            </button>
        </form>
    </main>
@endsection
