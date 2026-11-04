<?php

namespace App\View\Components;

use App\Models\AnalysisRun;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AppNav extends Component
{
    public string $hostLabel;

    public string $statusLabel;

    public string $statusColor;

    public ?string $runTime;

    public function __construct(
        public AnalysisRun $run,
    ) {
        $this->hostLabel = parse_url($run->submitted_url, PHP_URL_HOST) ?: $run->submitted_url;

        [$this->statusLabel, $this->statusColor] = match ($run->status) {
            'complete' => ['Completed', 'success'],
            'partial' => ['Completed with warnings', 'success'],
            'failed' => ['Failed', 'danger'],
            default => ['Analyzing…', 'brand'],
        };

        $this->runTime = $run->duration_ms ? round($run->duration_ms / 1000).'s' : null;
    }

    public function render(): View|Closure|string
    {
        return view('components.app-nav');
    }
}
