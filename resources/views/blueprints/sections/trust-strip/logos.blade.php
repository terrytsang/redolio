<section class="bp-section bp-section--alt" style="padding:2.5rem 0" data-section="{{ $id }}">
    <div class="bp-wrap bp-center">
        @if (! empty($c['heading']))<p class="bp-eyebrow" style="color:var(--color-muted)">{{ $c['heading'] }}</p>@endif
        <div class="bp-logos">@foreach ($c['items'] as $i)<span>{{ $i['name'] }}</span>@endforeach</div>
    </div>
</section>
