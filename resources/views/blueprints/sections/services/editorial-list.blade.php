<section class="bp-section" id="services" data-section="{{ $id }}">
    <div class="bp-wrap">
        <div class="bp-head"><h2>{{ $c['heading'] }}</h2>@if (! empty($c['subheading']))<p class="bp-lead">{{ $c['subheading'] }}</p>@endif</div>
        <ol class="bp-rows">
            @foreach ($c['items'] as $i)
                <li class="bp-row">
                    <span class="bp-row__n">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="bp-row__t">{{ $i['title'] }}</h3>
                    <p class="bp-row__d">{{ $i['description'] }}@if (! empty($i['meta']))<span class="bp-row__m">{{ $i['meta'] }}</span>@endif</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
