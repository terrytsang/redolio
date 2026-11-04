@props(['pricing'])
@if ($pricing['offer'])
    <span class="whitespace-nowrap"><s class="text-faint decoration-2" aria-label="Regular price ${{ number_format($pricing['list'], 0) }}">${{ number_format($pricing['list'], 0) }}</s> <strong class="text-ink">${{ number_format($pricing['price'], 0) }}</strong></span>
@else
    <strong class="text-ink">${{ number_format($pricing['price'], 0) }}</strong>
@endif
