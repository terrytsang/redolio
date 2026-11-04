@extends('layouts.app')

@section('title', 'New design direction — REDOLIO Admin')

@section('content')
    @include('admin.partials.header', ['active' => 'design-intelligence'])

    <main class="mx-auto max-w-2xl px-8 py-10">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('admin.design-directions.index') }}" class="text-muted hover:text-ink">Design Intelligence</a>
            <span class="text-line-strong">/</span>
            <span class="font-medium text-ink">New direction</span>
        </div>
        <h1 class="mt-2 text-2xl font-bold text-ink">{{ $remixFrom ? 'Remix "'.$remixFrom->name.'"' : 'New design direction' }}</h1>
        @if ($remixFrom)
            <p class="mt-1 text-sm text-muted">Starting from {{ $remixFrom->name }}'s recipe — edit anything below, then save as a new direction.</p>
        @endif

        <form method="POST" action="{{ route('admin.design-directions.store') }}" class="mt-6 flex flex-col gap-5 rounded-xl border border-line bg-surface p-6">
            @csrf
            @php($direction = $remixFrom ?? new \App\Models\DesignDirection(['is_selectable' => false]))
            @include('admin.design-directions._form')

            <button type="submit" class="self-start rounded-full bg-ink px-5 py-2.5 text-sm font-semibold text-canvas hover:opacity-85">
                Create direction
            </button>
        </form>
    </main>
@endsection
