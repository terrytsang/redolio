<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidBlueprintException;
use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Blueprint\BlueprintRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Renders a blueprint with representative SAMPLE content (never a user's site). */
class BlueprintPreviewController extends Controller
{
    public function show(Request $request, string $blueprint, BlueprintCatalog $catalog, BlueprintRenderer $renderer): Response
    {
        $bp = $catalog->find($blueprint);
        abort_if($bp === null, 404);

        $direction = $request->query('direction', $bp['compatible_direction_ids'][0]);
        abort_unless(is_string($direction) && $catalog->compatible($bp, $direction), 404);

        try {
            $html = $renderer->render($bp, $catalog->sample($blueprint, $direction), $direction, ['sample_banner' => ! $request->boolean('clean')]);
        } catch (InvalidBlueprintException $e) {
            report($e);
            abort(500, 'Blueprint failed validation.');
        }

        return response($html)->header('X-Robots-Tag', 'noindex');
    }
}
