<section class="bp-section bp-section--alt" id="contact" data-section="{{ $id }}">
    {{-- No form here by design: a contact form at the foot of a page is added deliberately from the editor's Add-ons (Form), with its own destination. --}}
    <div class="bp-wrap bp-contact bp-contact--details">
        <div>
            <h2>{{ $c['heading'] }}</h2>
            @if (! empty($c['body']))<p class="bp-lead">{{ $c['body'] }}</p>@endif
        </div>
        @if (! empty($c['contact_details']))
            <dl class="bp-details bp-details--card">@foreach ($c['contact_details'] as $d)<dt>{{ $d['label'] }}</dt><dd>{{ $d['value'] }}</dd>@endforeach</dl>
        @endif
    </div>
</section>
