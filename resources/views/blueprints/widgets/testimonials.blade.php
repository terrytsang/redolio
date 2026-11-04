<section class="bp-section bpw-tcar" data-bp-carousel @if (! empty($c['autoplay'])) data-autoplay="{{ (int) ($c['seconds'] ?? 6) }}" @endif aria-roledescription="carousel" aria-label="{{ $c['heading'] ?: 'Testimonials' }}">
    <div class="bp-wrap">
        @if (! empty($c['heading']))<h2 class="bpw-tcar__h">{{ $c['heading'] }}</h2>@endif
        <div class="bpw-tcar__viewport" tabindex="0">
            <ul class="bpw-tcar__track">
                @foreach ($c['items'] as $i => $t)
                    <li class="bpw-tcar__slide" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ count($c['items']) }}">
                        <figure class="bpw-quote"><blockquote>“{{ $t['quote'] }}”</blockquote>
                            <figcaption><strong>{{ $t['name'] }}</strong>@if (! empty($t['role']))<span>{{ $t['role'] }}</span>@endif</figcaption></figure>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="bpw-tcar__nav" hidden><button type="button" data-dir="-1" aria-label="Previous testimonial">‹</button><span class="bpw-tcar__dots" aria-hidden="true"></span><button type="button" data-dir="1" aria-label="Next testimonial">›</button></div>
    </div>
</section>
