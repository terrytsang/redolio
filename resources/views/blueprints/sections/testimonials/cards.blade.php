<section class="bp-section bp-section--alt" id="testimonials" data-section="{{ $id }}">
    <div class="bp-wrap">
        <div class="bp-head"><h2>{{ $c['heading'] }}</h2></div>
        <div class="bp-grid bp-grid--{{ min(max(count($c['items']), 2), 3) }}">
            @foreach ($c['items'] as $t)<figure class="bp-card bp-quote" style="margin:0"><blockquote style="margin:0">“{{ $t['quote'] }}”</blockquote><footer><strong>{{ $t['name'] }}</strong>{{ $t['role'] ?? '' }}</footer></figure>@endforeach
        </div>
    </div>
</section>
