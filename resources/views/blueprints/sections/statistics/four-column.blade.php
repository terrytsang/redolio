<section class="bp-section bp-section--alt" data-section="{{ $id }}">
    <div class="bp-wrap">
        @if (! empty($c['heading']))<h2 class="bp-center bp-head" style="margin-left:auto;margin-right:auto">{{ $c['heading'] }}</h2>@endif
        <div class="bp-grid bp-grid--4 bp-center">
            @foreach ($c['items'] as $i)<div><p class="bp-stat">{{ $i['value'] }}</p><p class="bp-stat-label">{{ $i['label'] }}</p></div>@endforeach
        </div>
    </div>
</section>
