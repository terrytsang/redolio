<section class="bp-section"  id="gallery" data-section="{{ $id }}">
    <div class="bp-wrap">
        <div class="bp-head"><h2>{{ $c['heading'] }}</h2>@if (! empty($c['subheading']))<p class="bp-lead">{{ $c['subheading'] }}</p>@endif</div>
    </div>
    <div class="bp-wrap">
        <div class="bp-gal bp-gal--featured">
            @foreach ($c['items'] as $i)
                <figure>@include('blueprints.partials.visual', ['image' => $i['image'] ?? ['alt' => $i['title']]])<figcaption><strong>{{ $i['title'] }}</strong>@if (! empty($i['caption'])) — {{ $i['caption'] }}@endif</figcaption></figure>
            @endforeach
        </div>
    </div>
</section>
