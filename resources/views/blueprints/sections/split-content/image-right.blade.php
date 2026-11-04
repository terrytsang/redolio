<section class="bp-section" id="equipment" data-section="{{ $id }}">
    <div class="bp-wrap bp-split">
        <div>
            @if (! empty($c['eyebrow']))<p class="bp-eyebrow">{{ $c['eyebrow'] }}</p>@endif
            <h2>{{ $c['heading'] }}</h2><p class="bp-lead">{{ $c['body'] }}</p>
            @if (! empty($c['bullets']))<ul class="bp-ticks" style="margin-top:1.5rem">@foreach ($c['bullets'] as $b)<li>{{ $b }}</li>@endforeach</ul>@endif
            <div class="bp-actions">@include('blueprints.partials.btn', ['cta' => $c['cta'] ?? null])</div>
        </div>
        @include('blueprints.partials.visual', ['image' => $c['image'] ?? null, 'class' => 'bp-visual--tall'])
    </div>
</section>
