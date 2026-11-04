<section class="bp-section" id="testimonials" data-section="{{ $id }}">
    <div class="bp-wrap">
        <h2 class="bp-center bp-head" style="margin-left:auto;margin-right:auto">{{ $c['heading'] }}</h2>
        @foreach ($c['items'] as $t)<figure class="bp-bigquote" style="margin-left:auto;margin-right:auto"><blockquote>“{{ $t['quote'] }}”</blockquote><cite>{{ $t['name'] }}@if (! empty($t['role'])) · {{ $t['role'] }}@endif</cite></figure>@endforeach
    </div>
</section>
