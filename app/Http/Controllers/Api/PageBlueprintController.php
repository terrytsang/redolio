<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Blueprint\BlueprintCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageBlueprintController extends Controller
{
    public function __construct(private readonly BlueprintCatalog $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $items = array_values($this->catalog->all());
        foreach (['category' => 'category_ids', 'direction' => 'compatible_direction_ids', 'page_type' => null, 'status' => null] as $param => $field) {
            $value = $request->query($param);
            if (! is_string($value) || $value === '') {
                continue;
            }
            $items = array_values(array_filter($items, fn ($b) => match ($param) {
                'status' => $this->catalog->effectiveStatus($b) === $value,
                'page_type' => $b['page_type'] === $value,
                default => in_array($value, $b[$field], true),
            }));
        }

        return response()->json(['data' => array_map($this->present(...), $items)]);
    }

    public function show(string $id): JsonResponse
    {
        $bp = $this->catalog->find($id);
        abort_if($bp === null, 404);

        return response()->json(['data' => array_merge($this->present($bp), ['content_slot_schema' => $this->catalog->contentSlotSchema($bp)])]);
    }

    private function present(array $bp): array
    {
        $bp['status'] = $this->catalog->effectiveStatus($bp);
        $bp['content_slot_schema'] = 'see GET /api/page-blueprints/{id}';
        $bp['preview_urls'] = array_combine(
            $bp['compatible_direction_ids'],
            array_map(fn ($d) => route('blueprints.preview', ['blueprint' => $bp['id'], 'direction' => $d]), $bp['compatible_direction_ids'])
        );

        return $bp;
    }
}
