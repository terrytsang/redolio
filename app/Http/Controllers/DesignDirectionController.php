<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\DesignDirection;
use App\Models\DesignSelection;
use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Blueprint\HeroLayoutChoices;
use App\Services\Catalogue\DesignCatalogue;
use App\Services\DesignDirection\DesignDirectionSelectorService;
use App\Services\Recommendation\BlueprintRecommender;
use App\Services\Recommendation\PersistDesignSelection;
use App\Services\Recommendation\ProjectSignals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DesignDirectionController extends Controller
{
    public function show(Request $request, AnalysisRun $analysisRun, DesignDirectionSelectorService $selector, DesignCatalogue $catalogue)
    {
        $recommendations = $analysisRun->designDirectionRecommendations()->orderBy('rank')->get();

        if ($recommendations->isEmpty() && $analysisRun->currentWebsiteDna()) {
            $recommendations = $selector->recommend($analysisRun);
        }

        $directions = DesignDirection::selectable()->get()->filter->hasRenderableTemplate()->keyBy('direction_key');

        // A recommendation row can outlive its direction becoming
        // unselectable (e.g. an admin removed it from the gallery after
        // recommendations were generated) — never show a card for one.
        $recommendations = $recommendations->filter(fn ($rec) => $directions->has($rec->direction_key))->values();

        // Recommendations saved before the v1.5 catalogue point at retired directions — re-rank.
        if ($recommendations->isEmpty() && $analysisRun->currentWebsiteDna()) {
            $recommendations = $selector->recommend($analysisRun)->filter(fn ($rec) => $directions->has($rec->direction_key))->values();
        }

        // Category is a discovery filter only: it narrows what is listed, never what may be chosen.
        $categoryId = $request->query('category');
        $categoryId = is_string($categoryId) && collect($catalogue->categories())->contains('id', $categoryId) ? $categoryId : null;
        $suggestedCategory = ProjectSignals::fromRun($analysisRun)->inferCategory($catalogue);
        if ($categoryId) {
            $inCategory = array_column($catalogue->directionsForCategory($categoryId), 'id');
            $recommendations = $recommendations->filter(fn ($rec) => in_array($rec->direction_key, $inCategory, true))->values();
        }

        // One-time nudge from the direction gallery's "Remix this direction"
        // — consumed here so it only pre-selects/banners once.
        $remixDirection = $request->session()->pull('remix_direction');

        return view('analyses.design-directions', [
            'run' => $analysisRun,
            'recommendations' => $recommendations,
            'directions' => $directions,
            'currentSelection' => $analysisRun->currentDesignSelection(),
            'categories' => $catalogue->categories(),
            'categoryId' => $categoryId,
            'suggestedCategory' => $suggestedCategory,
            'remixDirection' => ($remixDirection && $directions->has($remixDirection)) ? $remixDirection : null,
        ]);
    }

    /**
     * The express path: the scan is done, so build the REDO now with the best-matching direction and its recommended
     * layout (the person can still pick another design from the Reveal page). Idempotent: an existing selection is just followed.
     */
    public function express(Request $request, AnalysisRun $analysisRun, DesignDirectionSelectorService $selector, BlueprintRecommender $recommender, PersistDesignSelection $persist): RedirectResponse
    {
        if ($analysisRun->user_id !== null && $analysisRun->user_id !== $request->user()->id) {
            abort(403);
        }
        $analysisRun->user_id === null && $analysisRun->update(['user_id' => $request->user()->id]);

        if ($existing = $analysisRun->currentDesignSelection()) {
            return $existing->status === 'failed'
                ? redirect()->route('analyses.design-directions', $analysisRun->public_id)->with('status', $existing->error_message ?? 'Redesign generation failed. Please try again.')
                : redirect()->route('analyses.design-directions.generating', [$analysisRun->public_id, $existing->id]);
        }
        if (! $analysisRun->currentWebsiteDna() || ! $analysisRun->currentDiagnosisReport()) {
            return redirect()->route('analyses.show', $analysisRun->public_id);
        }

        $eligible = DesignDirection::selectable()->get()->filter->hasRenderableTemplate()->keyBy('direction_key');
        $rec = ($analysisRun->designDirectionRecommendations()->orderBy('rank')->get()->whenEmpty(fn () => $selector->recommend($analysisRun)))->first(fn ($r) => $eligible->has($r->direction_key));
        $key = $rec?->direction_key ?? $eligible->keys()->first();
        if (! $key) {
            return redirect()->route('analyses.design-directions', $analysisRun->public_id);
        }
        $blueprints = $recommender->recommend($key, ProjectSignals::fromRun($analysisRun));
        $blueprintId = $blueprints[0]['blueprint']['id'] ?? ($recommender->eligible($key)[0]['id'] ?? null);

        $selection = $persist->handle($analysisRun, ['direction_key' => $key, 'blueprint_id' => $blueprintId, 'preferences' => ['preserve_logo' => true, 'preserve_images' => true, 'preserve_wording' => false]], dispatch: true);

        return redirect()->route('analyses.design-directions.generating', [$analysisRun->public_id, $selection->id]);
    }

    /**
     * "Edit the design" on a design made by the older generator, which the on-page editor can't open: rebuild it with the layout engine,
     * keeping the direction the person already chose (or the best match), then land in the editor. The older versions stay in History.
     */
    public function makeEditable(Request $request, AnalysisRun $analysisRun, DesignDirectionSelectorService $selector, BlueprintRecommender $recommender, PersistDesignSelection $persist): RedirectResponse
    {
        $spec = $analysisRun->currentDesignSpec();
        if ($spec && isset($spec->design_spec_json['v15'])) {
            return redirect()->route('analyses.edit', $analysisRun->public_id);
        }
        if (! $analysisRun->currentWebsiteDna() || ! $analysisRun->currentDiagnosisReport()) {
            return redirect()->route('analyses.show', $analysisRun->public_id);
        }
        // A rebuild already under way: just wait for it.
        if (($current = $analysisRun->currentDesignSelection()) && $current->status === 'generating' && $current->blueprint_id) {
            return redirect()->route('analyses.design-directions.generating', [$analysisRun->public_id, $current->id, 'edit' => 1]);
        }

        // Keep the direction they chose when it has a ready layout; otherwise the best-matching direction that does. A design is only
        // editable when it is built from a layout, so never fall back to a build without one.
        $eligible = DesignDirection::selectable()->get()->filter->hasRenderableTemplate()->keyBy('direction_key');
        $recommended = $analysisRun->designDirectionRecommendations()->orderBy('rank')->get()->whenEmpty(fn () => $selector->recommend($analysisRun))->pluck('direction_key');
        $key = $blueprintId = null;
        foreach (collect([$current?->direction_key])->merge($recommended)->merge($eligible->keys())->filter()->unique() as $candidate) {
            if (! $eligible->has($candidate)) {
                continue;
            }
            $blueprints = $recommender->recommend($candidate, ProjectSignals::fromRun($analysisRun));
            $id = $blueprints[0]['blueprint']['id'] ?? ($recommender->eligible($candidate)[0]['id'] ?? null);
            if ($id) {
                [$key, $blueprintId] = [$candidate, $id];
                break;
            }
        }
        if (! $key) {
            return redirect()->route('analyses.reveal', $analysisRun->public_id)
                ->with('status', 'We couldn’t prepare this design for editing just now — our layouts aren’t available. Please try again shortly or email '.config('redo.support_email').'.');
        }

        $selection = $persist->handle($analysisRun, ['direction_key' => $key, 'blueprint_id' => $blueprintId, 'preferences' => ['preserve_logo' => true, 'preserve_images' => true, 'preserve_wording' => false]], dispatch: true);

        return redirect()->route('analyses.design-directions.generating', [$analysisRun->public_id, $selection->id, 'edit' => 1]);
    }

    /** Step 2: choose a page layout (blueprint) for the chosen direction. */
    public function blueprints(Request $request, AnalysisRun $analysisRun, BlueprintRecommender $recommender, BlueprintCatalog $catalog, DesignCatalogue $catalogue): View|RedirectResponse
    {
        $key = (string) $request->query('direction_key');
        $direction = DesignDirection::selectable()->where('direction_key', $key)->first();
        if (! $direction || ! $direction->hasRenderableTemplate()) {
            return redirect()->route('analyses.design-directions', $analysisRun->public_id)->with('status', 'Please choose a design direction first.');
        }

        $signals = ProjectSignals::fromRun($analysisRun);
        $recommended = $recommender->recommend($key, $signals);
        $recommendedIds = array_map(fn ($r) => $r['blueprint']['id'], $recommended);
        $others = array_values(array_filter($recommender->eligible($key), fn ($bp) => ! in_array($bp['id'], $recommendedIds, true)));

        return view('analyses.design-blueprints', [
            'run' => $analysisRun,
            'direction' => $direction,
            'recommended' => $recommended,
            'others' => $others,
            'catalog' => $catalog,
            'categoryId' => $request->query('category'),
            'preferences' => $request->only(['preserve_logo', 'preserve_images', 'preserve_wording']),
        ]);
    }

    public function select(Request $request, AnalysisRun $analysisRun, PersistDesignSelection $persist): RedirectResponse
    {
        $validated = $request->validate([
            'direction_key' => ['required', 'string'],
            'blueprint_id' => ['nullable', 'string', 'max:64'],
            'category_id' => ['nullable', 'string', 'max:32'],
            'preserve_logo' => ['nullable', 'boolean'],
            'preserve_images' => ['nullable', 'boolean'],
            'preserve_wording' => ['nullable', 'boolean'],
            'hero_layout' => ['nullable', 'string', 'in:'.implode(',', array_keys(HeroLayoutChoices::CHOICES))],
            'hero_side' => ['nullable', 'string', 'in:left,right'],
        ]);

        $selection = $persist->handle($analysisRun, [
            'direction_key' => $validated['direction_key'],
            'blueprint_id' => $validated['blueprint_id'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'preferences' => [
                'preserve_logo' => (bool) ($validated['preserve_logo'] ?? false),
                'preserve_images' => (bool) ($validated['preserve_images'] ?? false),
                'preserve_wording' => (bool) ($validated['preserve_wording'] ?? false),
            ] + array_filter(['hero_layout' => $validated['hero_layout'] ?? null, 'hero_side' => $validated['hero_side'] ?? null]),
        ], dispatch: true);

        return redirect()->route('analyses.design-directions.generating', [$analysisRun->public_id, $selection->id]);
    }

    public function generating(Request $request, AnalysisRun $analysisRun, DesignSelection $designSelection): View|RedirectResponse
    {
        abort_unless($designSelection->analysis_run_id === $analysisRun->id, 404);

        if ($designSelection->status === 'complete' && $designSelection->designSpec) {
            // Someone who asked to edit a design that had to be rebuilt first goes straight into the editor.
            if ($request->boolean('edit')) {
                return isset($designSelection->designSpec->design_spec_json['v15'])
                    ? redirect()->route('analyses.edit', $analysisRun->public_id)
                    : redirect()->route('analyses.reveal', $analysisRun->public_id)->with('status', 'We couldn’t make this design editable automatically. Please email '.config('redo.support_email').' and we’ll sort it out.');
            }

            return redirect()->route('analyses.reveal', $analysisRun->public_id);
        }

        if ($designSelection->status === 'failed') {
            return redirect()->route('analyses.design-directions', $analysisRun->public_id)
                ->with('status', $designSelection->error_message ?? 'Redesign generation failed. Please try again.');
        }

        $direction = DesignDirection::where('direction_key', $designSelection->direction_key)->first();

        return view('analyses.generating', [
            'run' => $analysisRun,
            'selection' => $designSelection,
            'direction' => $direction,
        ]);
    }
}
