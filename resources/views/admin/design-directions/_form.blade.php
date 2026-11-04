@php
    $recipe = $direction->recipe_json ?? [];
    $colors = $recipe['color_system'] ?? [];
    $typography = $recipe['typography_system'] ?? [];
    $old = fn ($key, $default = '') => old($key, $default);
@endphp

@if (! empty($recipe['preview_thumbnail']))
    <div class="overflow-hidden rounded-lg border border-line">
        <img src="{{ $recipe['preview_thumbnail'] }}" alt="Homepage screenshot of {{ $recipe['source_url'] ?? 'the reference site' }}" class="aspect-video w-full object-cover object-top">
        <p class="truncate bg-surface-2 px-2.5 py-1 text-[11px] text-faint">Captured from {{ $recipe['source_url'] ?? 'reference site' }} — this is a reference screenshot, not the rendered template below.</p>
    </div>
@endif

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="name" class="text-xs font-medium text-muted">Direction name</label>
        <input id="name" name="name" type="text" value="{{ $old('name', $recipe['name'] ?? ($direction->name ?? '')) }}" required
               class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
        @error('name') <p class="mt-1 text-xs text-bad">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="template_key" class="text-xs font-medium text-muted">Renders using template</label>
        <select id="template_key" name="template_key" class="mt-1.5 w-full rounded-lg border border-line bg-surface px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
            @foreach (config('design_allowlist.templates') as $templateKey)
                <option value="{{ $templateKey }}" @selected($old('template_key', $recipe['template_key'] ?? 'smb_default') === $templateKey)>{{ \Illuminate\Support\Str::headline($templateKey) }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-faint">Directions reuse one of REDOLIO's existing preview templates; the fields below are its metadata and color/typography guideline.</p>
    </div>
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="family" class="text-xs font-medium text-muted">Family</label>
        <input id="family" name="family" type="text" value="{{ $old('family', $recipe['family'] ?? '') }}" required placeholder="e.g. commerce"
               class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
        @error('family') <p class="mt-1 text-xs text-bad">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="spacing_system" class="text-xs font-medium text-muted">Spacing</label>
        <select id="spacing_system" name="spacing_system" class="mt-1.5 w-full rounded-lg border border-line bg-surface px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
            @foreach (['spacious', 'balanced', 'compact'] as $s)
                <option value="{{ $s }}" @selected($old('spacing_system', $recipe['spacing_system'] ?? 'balanced') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
</div>

<div>
    <label for="description" class="text-xs font-medium text-muted">Description</label>
    <textarea id="description" name="description" rows="2" required
              class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">{{ $old('description', $recipe['description'] ?? '') }}</textarea>
    @error('description') <p class="mt-1 text-xs text-bad">{{ $message }}</p> @enderror
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="best_for" class="text-xs font-medium text-muted">Best for (comma separated)</label>
        <input id="best_for" name="best_for" type="text" value="{{ $old('best_for', implode(', ', $recipe['best_for'] ?? [])) }}"
               class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
    </div>
    <div>
        <label for="brand_traits" class="text-xs font-medium text-muted">Brand traits (comma separated)</label>
        <input id="brand_traits" name="brand_traits" type="text" value="{{ $old('brand_traits', implode(', ', $recipe['brand_traits'] ?? [])) }}"
               class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
    </div>
</div>

<div>
    <p class="text-xs font-medium text-muted">Color system</p>
    <div class="mt-1.5 grid grid-cols-3 gap-3 sm:grid-cols-6">
        @foreach (['background', 'surface', 'text', 'text_muted', 'accent', 'accent_contrast'] as $role)
            <div>
                <label for="color_{{ $role }}" class="block text-[10px] uppercase tracking-wide text-faint">{{ \Illuminate\Support\Str::headline($role) }}</label>
                <input id="color_{{ $role }}" name="color_{{ $role }}" type="color" value="{{ $old('color_'.$role, $colors[$role] ?? '#0A0B0D') }}"
                       class="mt-1 h-9 w-full cursor-pointer rounded-lg border border-line">
            </div>
        @endforeach
    </div>
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="typography_display" class="text-xs font-medium text-muted">Display typeface</label>
        <select id="typography_display" name="typography_display" class="mt-1.5 w-full rounded-lg border border-line bg-surface px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
            @foreach (['font-sans', 'font-serif', 'font-mono'] as $t)
                <option value="{{ $t }}" @selected($old('typography_display', $typography['display'] ?? 'font-sans') === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="typography_body" class="text-xs font-medium text-muted">Body typeface</label>
        <select id="typography_body" name="typography_body" class="mt-1.5 w-full rounded-lg border border-line bg-surface px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
            @foreach (['font-sans', 'font-serif', 'font-mono'] as $t)
                <option value="{{ $t }}" @selected($old('typography_body', $typography['body'] ?? 'font-sans') === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
</div>

<div>
    <label for="layout_compositions" class="text-xs font-medium text-muted">Layout compositions (comma separated)</label>
    <input id="layout_compositions" name="layout_compositions" type="text" value="{{ $old('layout_compositions', implode(', ', $recipe['layout_compositions'] ?? [])) }}"
           class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
</div>

<div>
    <label for="image_treatment" class="text-xs font-medium text-muted">Image treatment</label>
    <textarea id="image_treatment" name="image_treatment" rows="2"
              class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">{{ $old('image_treatment', $recipe['image_treatment'] ?? '') }}</textarea>
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="responsive_rules" class="text-xs font-medium text-muted">Responsive rules</label>
        <textarea id="responsive_rules" name="responsive_rules" rows="2"
                  class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">{{ $old('responsive_rules', $recipe['responsive_rules'] ?? '') }}</textarea>
    </div>
    <div>
        <label for="accessibility_rules" class="text-xs font-medium text-muted">Accessibility rules</label>
        <textarea id="accessibility_rules" name="accessibility_rules" rows="2"
                  class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">{{ $old('accessibility_rules', $recipe['accessibility_rules'] ?? '') }}</textarea>
    </div>
</div>

<label class="flex items-center gap-2.5">
    <input type="checkbox" name="is_selectable" value="1" @checked($old('is_selectable', $direction->is_selectable ?? false)) class="h-4 w-4 accent-accent">
    <span class="text-sm text-ink">List in the gallery for signed-up users to choose</span>
</label>
