<section class="bp-section" id="features" data-section="{{ $id }}">
    <div class="bp-wrap">
        <div class="bp-head"><h2>{{ $c['heading'] }}</h2>@if (! empty($c['subheading']))<p class="bp-lead">{{ $c['subheading'] }}</p>@endif</div>
        <div class="bp-bento">
            @foreach ($c['items'] as $i)
                <article class="bp-card">
                    @if (! empty($i['icon']))<div class="bp-icon"><x-icon :name="$i['icon']"/></div>@endif
                    <h3>{{ $i['title'] }}</h3><p>{{ $i['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
