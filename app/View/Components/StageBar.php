<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class StageBar extends Component
{
    private const STAGES = [
        'validate_url' => 'Validate URL',
        'crawl_pages' => 'Crawl pages',
        'website_dna' => 'Website DNA',
        'diagnose' => 'Diagnose',
        'redo' => 'REDO',
        'next_redo' => 'Next REDO',
    ];

    /** @var array<int, array{key: string, label: string, state: string}> */
    public array $stages;

    public function __construct(public string $active)
    {
        $keys = array_keys(self::STAGES);
        $activeIndex = array_search($active, $keys, true) ?: 0;

        $this->stages = [];
        foreach ($keys as $index => $key) {
            $this->stages[] = [
                'key' => $key,
                'label' => self::STAGES[$key],
                'state' => $index < $activeIndex ? 'complete' : ($index === $activeIndex ? 'current' : 'pending'),
            ];
        }
    }

    public function render(): View|Closure|string
    {
        return view('components.stage-bar');
    }
}
