<footer class="bp-footer" data-section="{{ $id }}">
    <div class="bp-wrap">
        <div class="bp-footer__grid">
            <div><a class="bp-brand" href="#top" style="opacity:1">{{ $c['brand'] }}</a>@if (! empty($c['tagline']))<p style="margin-top:.75rem;opacity:.75;max-width:22rem">{{ $c['tagline'] }}</p>@endif</div>
            @foreach ($c['columns'] ?? [] as $col)
                <nav aria-label="{{ $col['title'] }}"><h4>{{ $col['title'] }}</h4><ul>@foreach ($col['links'] as $l)<li><a href="{{ $l['href'] }}">{{ $l['label'] }}</a></li>@endforeach</ul></nav>
            @endforeach
        </div>
        @if (! empty($c['legal']))<p class="bp-footer__legal">{{ $c['legal'] }}</p>@endif
    </div>
</footer>
