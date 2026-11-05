{{-- Site header (homepage and the information pages) --}}
    <header class="border-b border-line bg-canvas">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-16">
            <a href="{{ route('landing') }}" class="text-xl font-extrabold tracking-tight text-ink"><x-brand /></a>

            <nav class="hidden items-center gap-8 lg:flex">
                <a href="{{ route('landing') }}#features" class="text-sm font-medium text-muted hover:text-ink">Features</a>
                <a href="{{ route('landing') }}#how-it-works" class="text-sm font-medium text-muted hover:text-ink">How it works</a>
                <a href="{{ route('pricing') }}" class="text-sm font-medium text-muted hover:text-ink">Pricing</a>
                <a href="{{ route('directions.index') }}" class="text-sm font-medium text-muted hover:text-ink">Directions</a>
            </nav>

            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-dark">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="hidden text-sm font-medium text-muted hover:text-ink sm:inline">Sign in</a>
                    <a href="{{ route('register') }}" class="btn btn-dark">
                        Start free
                    </a>
                @endauth
            </div>
        </div>
    </header>
