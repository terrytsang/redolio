@php
    $reasons = $rec->score_json['reasons'] ?? [];
    $cautions = $rec->score_json['cautions'] ?? [];
@endphp
@if ($reasons || $cautions)
    <ul class="flex flex-col gap-1 text-[12px] leading-snug">
        @foreach (array_slice($reasons, 0, 3) as $reason)
            <li class="flex gap-1.5 text-body"><span class="text-ok" aria-hidden="true">✓</span><span>{{ $reason }}</span></li>
        @endforeach
        @foreach (array_slice($cautions, 0, 2) as $caution)
            <li class="flex gap-1.5 text-warn"><span aria-hidden="true">!</span><span>{{ $caution }}</span></li>
        @endforeach
    </ul>
@endif
