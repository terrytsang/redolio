<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Catalogue\DesignCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Public, read-only catalogue endpoints (PRD v1.5 api_contracts). No recipe internals are exposed. */
class DesignCatalogueController extends Controller
{
    public function __construct(private readonly DesignCatalogue $catalogue) {}

    public function categories(): JsonResponse
    {
        $categories = array_map(function ($c) {
            $c['direction_ids'] = array_column($this->catalogue->directionsForCategory($c['id']), 'id');
            $c['primary_direction_ids'] = array_column(array_filter(
                $this->catalogue->directions(),
                fn ($d) => in_array($c['id'], $d['primary_category_ids'], true)
            ), 'id');

            return $c;
        }, $this->catalogue->categories());

        return response()->json(['catalogue_version' => $this->catalogue->version(), 'data' => $categories]);
    }

    public function directions(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $directions = $category
            ? $this->catalogue->directionsForCategory((string) $category)
            : $this->catalogue->directions();

        return response()->json([
            'catalogue_version' => $this->catalogue->version(),
            'data' => array_map($this->present(...), $directions),
        ]);
    }

    public function direction(string $id): JsonResponse
    {
        $direction = $this->catalogue->direction($id);
        abort_if($direction === null, 404);

        return response()->json(['catalogue_version' => $this->catalogue->version(), 'data' => $this->present($direction)]);
    }

    private function present(array $d): array
    {
        unset($d['template_key']);
        $d['token_profile'] = collect($this->catalogue->tokenProfiles())->firstWhere('id', $d['token_profile_base']);

        return $d;
    }
}
