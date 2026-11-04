<section class="bp-section bpw-social-sec">
    <div class="bp-wrap bpw-social bpw-social--{{ $c['style'] ?? 'round' }} bpw-align--{{ $c['align'] ?? 'center' }}">
        @if (! empty($c['heading']))<h3 class="bpw-social__h">{{ $c['heading'] }}</h3>@endif
        <ul class="bpw-social__list">
            @foreach ($items as $it)
                @php $label = \App\Services\Widgets\SocialLinks::label($it['platform']); @endphp
                <li><a href="{{ $it['href'] }}" @if (str_starts_with($it['href'], 'https://')) target="_blank" rel="noopener" @endif aria-label="{{ $label }}" style="--bp-ic-bg: {{ \App\Services\Widgets\SocialLinks::color($it['platform']) }}"><span class="bpw-ic">{!! \App\Services\Widgets\SocialLinks::icon($it['platform']) !!}</span>@if (($c['style'] ?? 'round') === 'buttons')<span class="bpw-lbl">{{ preg_replace('/ \(.*\)$/', '', $label) }}</span>@endif</a></li>
            @endforeach
        </ul>
    </div>
</section>
