{{-- Site footer (homepage and the information pages). Every link goes to a real section or page. --}}
@php
    $home = route('landing');
    $footerColumns = [
        'Product' => [
            'Features' => $home.'#features',
            'How it works' => $home.'#how-it-works',
            'Pricing' => route('pricing'),
            'Changelog' => route('pages.changelog'),
            'Roadmap' => route('pages.roadmap'),
        ],
        'Resources' => [
            'Documentation' => route('pages.docs'),
            'Security' => route('pages.security'),
            'API status' => route('pages.status'),
            'Evidence policy' => route('pages.evidence'),
        ],
        'Company' => [
            'About' => route('pages.about'),
            'Built at Meta Hackathon' => route('pages.hackathon'),
            'Privacy policy' => route('pages.privacy'),
            'Terms of service' => route('pages.terms'),
            'Contact' => route('pages.contact'),
        ],
    ];
@endphp
<footer class="border-t border-line bg-canvas px-6 pb-10 pt-16 lg:px-16">
    <div class="mx-auto flex max-w-7xl flex-col gap-12">
        <div class="flex flex-col gap-10 sm:flex-row sm:justify-between">
            <div class="flex max-w-[280px] flex-col gap-3">
                <span class="text-xl font-extrabold tracking-tight text-ink"><x-brand /></span>
                <p class="text-[13px] leading-relaxed text-faint">The self-evolving website platform.<br>Understand. Diagnose. Redesign. Repeat.</p>
                <a href="{{ $home }}#analyze" class="mt-1 w-fit rounded-full bg-accent px-[22px] py-2.5 text-[13px] font-bold text-white hover:bg-accent">
                    Start Free
                </a>
            </div>

            <div class="grid grid-cols-2 gap-10 sm:flex sm:gap-16">
                @foreach ($footerColumns as $heading => $links)
                    <div class="flex w-40 flex-col gap-3.5">
                        <span class="text-[13px] font-bold tracking-wide text-ink">{{ $heading }}</span>
                        @foreach ($links as $label => $url)
                            <a href="{{ $url }}" class="text-[13px] text-faint hover:text-muted">{{ $label }}</a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <div class="h-px w-full bg-line"></div>

        <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <span class="text-xs text-body">© 2026 REDOLIO. Built for better websites.</span>
            <p class="text-xs text-body">A product of <a href="https://appition.com" target="_blank" rel="noopener noreferrer" class="font-semibold text-ink underline-offset-2 hover:underline">Appition</a>.</p>
        </div>
    </div>
</footer>
