<footer class="bp-footer bp-footer--min" data-section="{{ $id }}">
    <div class="bp-wrap bp-footer__row">
        <a class="bp-brand" href="#top" style="opacity:1">{{ $c['brand'] }}</a>
        <nav aria-label="Footer"><ul>@foreach (collect($c['columns'] ?? [])->flatMap(fn ($col) => $col['links'])->take(6) as $l)<li><a href="{{ $l['href'] }}">{{ $l['label'] }}</a></li>@endforeach</ul></nav>
        @if (! empty($c['legal']))<p class="bp-footer__legal" style="margin:0;padding:0;border:0">{{ $c['legal'] }}</p>@endif
    </div>
</footer>
