{{-- A tiny drawing of each hero arrangement. Decorative; the card text says the same. --}}
@php
    $ink = 'var(--color-ink, #1f2937)'; $img = 'color-mix(in srgb, currentColor 22%, transparent)';
@endphp
<svg viewBox="0 0 160 96" class="h-24 w-full rounded-xl bg-surface-2 text-accent" aria-hidden="true" focusable="false">
    <rect x="0" y="0" width="160" height="8" fill="currentColor" opacity=".08"/>
    @switch($key)
        @case('image-left')
            <rect x="10" y="16" width="62" height="72" rx="6" fill="{{ $img }}"/>
            <rect x="84" y="28" width="62" height="7" rx="3" fill="currentColor"/><rect x="84" y="40" width="48" height="7" rx="3" fill="currentColor"/>
            <rect x="84" y="54" width="58" height="4" rx="2" fill="currentColor" opacity=".35"/><rect x="84" y="62" width="50" height="4" rx="2" fill="currentColor" opacity=".35"/>
            <rect x="84" y="74" width="26" height="9" rx="4.5" fill="currentColor"/><rect x="114" y="74" width="26" height="9" rx="4.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
            @break
        @case('image-right')
            <rect x="88" y="16" width="62" height="72" rx="6" fill="{{ $img }}"/>
            <rect x="14" y="28" width="62" height="7" rx="3" fill="currentColor"/><rect x="14" y="40" width="48" height="7" rx="3" fill="currentColor"/>
            <rect x="14" y="54" width="58" height="4" rx="2" fill="currentColor" opacity=".35"/><rect x="14" y="62" width="50" height="4" rx="2" fill="currentColor" opacity=".35"/>
            <rect x="14" y="74" width="26" height="9" rx="4.5" fill="currentColor"/><rect x="44" y="74" width="26" height="9" rx="4.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
            @break
        @case('banner')
            <rect x="0" y="8" width="160" height="88" fill="{{ $img }}"/><rect x="0" y="8" width="90" height="88" fill="currentColor" opacity=".25"/>
            <rect x="14" y="30" width="62" height="8" rx="3" fill="#fff"/><rect x="14" y="43" width="46" height="8" rx="3" fill="#fff"/>
            <rect x="14" y="57" width="56" height="4" rx="2" fill="#fff" opacity=".7"/>
            <rect x="14" y="69" width="26" height="9" rx="4.5" fill="#fff"/><rect x="44" y="69" width="26" height="9" rx="4.5" fill="none" stroke="#fff" stroke-width="1.5"/>
            @break
        @case('fullscreen')
            <rect x="0" y="8" width="160" height="88" fill="currentColor" opacity=".05"/><rect x="0" y="8" width="88" height="88" fill="{{ $img }}"/>
            <rect x="96" y="30" width="54" height="8" rx="3" fill="currentColor"/><rect x="96" y="43" width="42" height="8" rx="3" fill="currentColor"/>
            <rect x="96" y="57" width="50" height="4" rx="2" fill="currentColor" opacity=".35"/>
            <rect x="96" y="69" width="22" height="9" rx="4.5" fill="currentColor"/><rect x="122" y="69" width="22" height="9" rx="4.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
            @break
        @case('bold')
            <rect x="12" y="20" width="120" height="14" rx="3" fill="currentColor"/><rect x="12" y="38" width="86" height="14" rx="3" fill="currentColor"/>
            <rect x="12" y="64" width="136" height="1.5" fill="currentColor" opacity=".6"/>
            <rect x="12" y="72" width="52" height="4" rx="2" fill="currentColor" opacity=".35"/><rect x="12" y="80" width="42" height="4" rx="2" fill="currentColor" opacity=".35"/>
            <circle cx="120" cy="77" r="7" fill="currentColor"/><circle cx="140" cy="77" r="7" fill="none" stroke="currentColor" stroke-width="1.5"/>
            @break
    @endswitch
</svg>
