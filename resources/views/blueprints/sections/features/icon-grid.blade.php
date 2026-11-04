<section class="bp-section" id="features" data-section="{{ $id }}">
    <div class="bp-wrap">
        <div class="bp-head"><h2>{{ $c['heading'] }}</h2>@if (! empty($c['subheading']))<p class="bp-lead">{{ $c['subheading'] }}</p>@endif</div>
        <div class="bp-grid bp-grid--3 bp-ifeat">
            @foreach ($c['items'] as $i)
                <article>
                    @if (! empty($i['icon']))<div class="bp-icon"><x-icon :name="$i['icon']"/></div>@else<div class="bp-icon bp-icon--n">{{ $loop->iteration }}</div>@endif
                    <h3>{{ $i['title'] }}</h3><p>{{ $i['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
