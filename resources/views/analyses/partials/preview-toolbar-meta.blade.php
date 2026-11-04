@php
    use Illuminate\Support\Str;
@endphp

<span id="preview-version-badge" class="rounded-full bg-accent-soft px-2.5 py-1 text-[11px] font-semibold text-accent-ink">
    @if ($v15 = $spec->design_spec_json['v15'] ?? null)
        v{{ $spec->version }} · {{ Str::headline($v15['design']['direction_id']) }} · {{ Str::headline($v15['design']['blueprint_id']) }}
    @else
        v{{ $spec->version }} · Template: {{ Str::headline($spec->template_key) }}
    @endif
</span>

@if (($spec->design_spec_json['v15']['content_mapping']['status'] ?? null) === 'partial')
    <span class="rounded-full bg-warn-soft px-2.5 py-1 text-[11px] font-semibold text-warn" title="Some sections were left out because your site has no verified content for them.">
        {{ count($spec->design_spec_json['v15']['content_mapping']['omitted_sections']) }} sections left out
    </span>
@endif

@if ($otherVersions->count() > 1)
    <select id="preview-version-select" onchange="window.location = this.value" class="rounded-lg border border-line px-2.5 py-1.5 text-xs">
        @foreach ($otherVersions as $v)
            <option value="{{ route('analyses.preview.version', [$run->public_id, $v->version]) }}" @selected($v->version === $spec->version)>
                v{{ $v->version }}
            </option>
        @endforeach
    </select>
@endif
