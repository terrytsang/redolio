<section class="bp-section bp-section--alt" id="schedule" data-section="{{ $id }}">
    <div class="bp-wrap">
        <div class="bp-head"><h2>{{ $c['heading'] }}</h2>@if (! empty($c['subheading']))<p class="bp-lead">{{ $c['subheading'] }}</p>@endif</div>
        <div class="bp-sched">
            @foreach ($c['days'] as $d)
                <div class="bp-card"><h3>{{ $d['day'] }}</h3><ul>@foreach ($d['slots'] as $s)<li><time>{{ $s['time'] }}</time>{{ $s['label'] }}</li>@endforeach</ul></div>
            @endforeach
        </div>
    </div>
</section>
