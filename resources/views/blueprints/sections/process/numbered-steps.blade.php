<section class="bp-section" id="process" data-section="{{ $id }}">
    <div class="bp-wrap">
        <div class="bp-head"><h2>{{ $c['heading'] }}</h2>@if (! empty($c['subheading']))<p class="bp-lead">{{ $c['subheading'] }}</p>@endif</div>
        <ol class="bp-steps">@foreach ($c['items'] as $i)<li><h3>{{ $i['title'] }}</h3><p>{{ $i['description'] }}</p></li>@endforeach</ol>
    </div>
</section>
