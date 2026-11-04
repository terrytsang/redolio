@if ($publish)
<section class="bp-section"><div class="bp-wrap"><iframe class="bpw-embed" src="embeds/{{ $id }}.html" title="{{ $c['title'] ?: 'Embedded content' }}" sandbox="allow-scripts allow-popups allow-forms allow-popups-to-escape-sandbox" loading="lazy" style="height:{{ (int) ($c['height'] ?? 400) }}px"></iframe></div></section>
@else
<section class="bp-section"><div class="bp-wrap"><div class="bpw-ph bpw-ph--embed" style="min-height:{{ min(300, (int) ($c['height'] ?? 400)) }}px" role="note"><strong>{{ $c['title'] ?: 'Embedded content' }}</strong><span>Your embed code runs live on the published site, in its own isolated frame.</span></div></div></section>
@endif
