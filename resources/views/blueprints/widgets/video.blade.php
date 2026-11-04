<section class="bp-section"><div class="bp-wrap">@if (! empty($c['heading']))<h2 class="bpw-h">{{ $c['heading'] }}</h2>@endif
@if ($publish)<div class="bpw-ratio"><iframe src="https://www.youtube-nocookie.com/embed/{{ $yt }}" title="{{ $c['heading'] ?: 'Video' }}" loading="lazy" allowfullscreen sandbox="allow-scripts allow-same-origin allow-presentation allow-popups" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
@else<div class="bpw-ph bpw-ph--video" role="note"><strong>▶ Video</strong><span>Plays on your published site.</span></div>@endif</div></section>
