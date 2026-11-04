<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDesignDirectionJob;
use App\Jobs\ImportDesignMdDirectionJob;
use App\Services\DesignDirection\GradientDeckSource;
use App\Models\DesignDirection;
use App\Models\DesignDirectionGeneration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DesignIntelligenceController extends Controller
{
    public function index(): View
    {
        return view('admin.design-directions.index', [
            'directions' => DesignDirection::orderByDesc('is_selectable')->orderBy('name')->get(),
            'generations' => DesignDirectionGeneration::with('designDirection')->latest()->limit(10)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $remixFrom = $request->query('remix') ? DesignDirection::where('direction_key', $request->query('remix'))->first() : null;

        return view('admin.design-directions.create', ['remixFrom' => $remixFrom]);
    }

    public function store(Request $request): RedirectResponse
    {
        $allowedTemplates = config('design_allowlist.templates');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'template_key' => ['required', 'string', 'in:'.implode(',', $allowedTemplates)],
            'family' => ['required', 'string', 'max:64'],
            'description' => ['required', 'string', 'max:2000'],
            'best_for' => ['nullable', 'string', 'max:2000'],
            'brand_traits' => ['nullable', 'string', 'max:2000'],
            'color_background' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_surface' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_text' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_text_muted' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_accent_contrast' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'typography_display' => ['required', 'in:font-sans,font-serif,font-mono'],
            'typography_body' => ['required', 'in:font-sans,font-serif,font-mono'],
            'spacing_system' => ['required', 'in:spacious,balanced,compact'],
            'layout_compositions' => ['nullable', 'string', 'max:2000'],
            'image_treatment' => ['nullable', 'string', 'max:2000'],
            'responsive_rules' => ['nullable', 'string', 'max:2000'],
            'accessibility_rules' => ['nullable', 'string', 'max:2000'],
            'is_selectable' => ['nullable', 'boolean'],
        ]);

        $directionKey = $this->uniqueDirectionKey(Str::slug($validated['name'], '_'));

        DesignDirection::create([
            'direction_key' => $directionKey,
            'name' => $validated['name'],
            'version' => 1,
            'is_selectable' => (bool) ($validated['is_selectable'] ?? false),
            'created_at' => now(),
            'recipe_json' => $this->recipeFromRequest($directionKey, $validated),
        ]);

        return redirect()->route('admin.design-directions.index')->with('status', 'Design direction created.');
    }

    public function edit(DesignDirection $designDirection): View
    {
        return view('admin.design-directions.edit', ['direction' => $designDirection]);
    }

    public function update(Request $request, DesignDirection $designDirection): RedirectResponse
    {
        $allowedTemplates = config('design_allowlist.templates');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'template_key' => ['required', 'string', 'in:'.implode(',', $allowedTemplates)],
            'family' => ['required', 'string', 'max:64'],
            'description' => ['required', 'string', 'max:2000'],
            'best_for' => ['nullable', 'string', 'max:2000'],
            'brand_traits' => ['nullable', 'string', 'max:2000'],
            'color_background' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_surface' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_text' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_text_muted' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_accent_contrast' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'typography_display' => ['required', 'in:font-sans,font-serif,font-mono'],
            'typography_body' => ['required', 'in:font-sans,font-serif,font-mono'],
            'spacing_system' => ['required', 'in:spacious,balanced,compact'],
            'layout_compositions' => ['nullable', 'string', 'max:2000'],
            'image_treatment' => ['nullable', 'string', 'max:2000'],
            'responsive_rules' => ['nullable', 'string', 'max:2000'],
            'accessibility_rules' => ['nullable', 'string', 'max:2000'],
            'is_selectable' => ['nullable', 'boolean'],
        ]);

        $designDirection->update([
            'name' => $validated['name'],
            'is_selectable' => (bool) ($validated['is_selectable'] ?? false),
            'recipe_json' => $this->recipeFromRequest($designDirection->direction_key, $validated),
        ]);

        return redirect()->route('admin.design-directions.index')->with('status', 'Design direction updated.');
    }

    public function generateFromUrl(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'source_url' => ['required', 'string', 'max:2048'],
        ]);

        // Name is optional: if left blank, GenerateDesignDirectionJob names
        // it from the captured industry/family once the AI response is in
        // (an empty requested_name is the "please auto-name" signal) —
        // this provisional key is just for uniqueness bookkeeping until
        // then and gets replaced with the real one afterward.
        $providedName = trim($validated['name'] ?? '');
        $keyBase = $providedName !== ''
            ? Str::slug($providedName, '_')
            : Str::slug((string) parse_url($validated['source_url'], PHP_URL_HOST), '_');
        $directionKey = $this->uniqueDirectionKey($keyBase !== '' ? $keyBase : 'direction');

        $generation = DesignDirectionGeneration::create([
            'requested_name' => $providedName,
            'direction_key' => $directionKey,
            'source_url' => $validated['source_url'],
            'status' => 'pending',
            'requested_by' => $request->user()->id,
        ]);

        GenerateDesignDirectionJob::dispatch($generation->id);

        return redirect()->route('admin.design-directions.generating', $generation);
    }

    /** Paste a GradientDeck UI style (e.g. https://ui.gradientdeck.com/styles/kobbe) and turn its DESIGN.md into a Design Direction. */
    public function importDesignMd(Request $request, GradientDeckSource $source): RedirectResponse
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:255'], 'design_md_url' => ['required', 'string', 'max:300']]);
        try {
            $slug = $source->slugFrom($data['design_md_url']);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['design_md_url' => $e->getMessage()]);
        }

        $name = trim($data['name'] ?? '');
        $generation = DesignDirectionGeneration::create([
            'requested_name' => $name,
            'direction_key' => $this->uniqueDirectionKey(Str::slug($name !== '' ? $name : $slug.'_style', '_')),
            'source_url' => 'https://ui.gradientdeck.com/styles/'.$slug,
            'status' => 'pending',
            'requested_by' => $request->user()->id,
        ]);
        ImportDesignMdDirectionJob::dispatch($generation->id);

        return redirect()->route('admin.design-directions.generating', $generation);
    }

    public function generating(DesignDirectionGeneration $generation): View|RedirectResponse
    {
        // A capture that never reported back (worker killed, server restarted) must not leave the popup spinning forever.
        if (in_array($generation->status, ['pending', 'running'], true) && $generation->updated_at->lt(now()->subMinutes(20))) {
            $generation->update(['status' => 'failed', 'error_message' => 'The capture took too long or was interrupted. Please try again.']);
        }

        if ($generation->status === 'complete' && $generation->design_direction_id) {
            return redirect()->route('admin.design-directions.edit', $generation->design_direction_id)
                ->with('status', 'A new Design Direction “'.($generation->designDirection?->name ?? $generation->requested_name).'” has been created from '.$generation->source_url.'. Review it below, then add it to the gallery when ready.');
        }

        if ($generation->status === 'failed') {
            return redirect()->route('admin.design-directions.index')
                ->with('status', $generation->error_message ?? 'Design direction generation failed. Please try again.')
                ->with('status_error', true);
        }

        return view('admin.design-directions.generating', ['generation' => $generation]);
    }

    public function toggleSelectable(Request $request, DesignDirection $designDirection): RedirectResponse
    {
        $enabling = ! $designDirection->is_selectable;

        if ($enabling && ! $designDirection->hasRenderableTemplate()) {
            return redirect()->route('admin.design-directions.edit', $designDirection)
                ->with('status', 'This direction has no template assigned yet, so it would break the picker. Choose a template below, then add it to the gallery.');
        }

        $designDirection->update(['is_selectable' => $enabling]);

        return back()->with('status', $enabling ? 'Added to the gallery.' : 'Removed from the gallery.');
    }

    private function recipeFromRequest(string $directionKey, array $validated): array
    {
        return [
            'direction_id' => $directionKey,
            'name' => $validated['name'],
            'family' => $validated['family'],
            'description' => $validated['description'],
            'best_for' => $this->splitLines($validated['best_for'] ?? ''),
            'brand_traits' => $this->splitLines($validated['brand_traits'] ?? ''),
            'color_system' => [
                'background' => $validated['color_background'],
                'surface' => $validated['color_surface'],
                'text' => $validated['color_text'],
                'text_muted' => $validated['color_text_muted'],
                'accent' => $validated['color_accent'],
                'accent_contrast' => $validated['color_accent_contrast'],
            ],
            'typography_system' => [
                'display' => $validated['typography_display'],
                'body' => $validated['typography_body'],
            ],
            'layout_compositions' => $this->splitLines($validated['layout_compositions'] ?? ''),
            'hero_patterns' => [],
            'section_patterns' => [],
            'image_treatment' => $validated['image_treatment'] ?? '',
            'spacing_system' => $validated['spacing_system'],
            'component_theme' => '',
            'responsive_rules' => $validated['responsive_rules'] ?? '',
            'accessibility_rules' => $validated['accessibility_rules'] ?? '',
            'preview_thumbnail' => null,
            'version' => 1,
            'template_key' => $validated['template_key'],
        ];
    }

    private function splitLines(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function uniqueDirectionKey(string $base): string
    {
        $key = $base ?: 'direction';
        $suffix = 1;

        while (DesignDirection::where('direction_key', $key)->exists()) {
            $suffix++;
            $key = $base.'_'.$suffix;
        }

        return $key;
    }
}
