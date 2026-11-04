@if (str_contains($renderedHtml, 'bp-frame-host'))
    {!! $renderedHtml !!}
@else
    <div class="p-4">
        {!! $renderedHtml !!}
    </div>
@endif
