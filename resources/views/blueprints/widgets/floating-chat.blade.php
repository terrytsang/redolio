@php $pos = ($c['position'] ?? 'right') === 'left' ? 'left' : 'right'; @endphp
<div class="bpw-fab bpw-fab--{{ $pos }}">
    @if (count($items) === 1)
        @php $it = $items[0]; @endphp
        <a class="bpw-fab__btn" href="{{ $it['href'] }}" @if (str_starts_with($it['href'], 'https://')) target="_blank" rel="noopener" @endif aria-label="{{ ! empty($c['label']) ? $c['label'] : \App\Services\Widgets\SocialLinks::label($it['platform']) }}" style="--bp-ic-bg: {{ \App\Services\Widgets\SocialLinks::color($it['platform']) }}">{!! \App\Services\Widgets\SocialLinks::icon($it['platform']) !!}</a>
    @else
        <input type="checkbox" id="fab-{{ $id }}" class="bpw-fab__toggle" aria-label="{{ $c['label'] ?: 'Chat with us' }}">
        <ul class="bpw-fab__list">
            @foreach ($items as $it)
                <li><a href="{{ $it['href'] }}" @if (str_starts_with($it['href'], 'https://')) target="_blank" rel="noopener" @endif aria-label="{{ \App\Services\Widgets\SocialLinks::label($it['platform']) }}" style="--bp-ic-bg: {{ \App\Services\Widgets\SocialLinks::color($it['platform']) }}"><span class="bpw-fab__tip">{{ preg_replace('/ \(.*\)$/', '', \App\Services\Widgets\SocialLinks::label($it['platform'])) }}</span><span class="bpw-fab__ic">{!! \App\Services\Widgets\SocialLinks::icon($it['platform']) !!}</span></a></li>
            @endforeach
        </ul>
        <label for="fab-{{ $id }}" class="bpw-fab__btn bpw-fab__main" title="{{ $c['label'] ?: 'Chat with us' }}"><svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg></label>
    @endif
</div>
