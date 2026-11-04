<?php

namespace App\Services\Orchestration;

use App\DTO\MetaApi\MetaStageResponse;
use App\Models\AnalysisRun;
use App\Models\ModelCallLog;

class ModelCallLogger
{
    public function logSuccess(?AnalysisRun $run, string $stage, string $requestHash, MetaStageResponse $response): void
    {
        ModelCallLog::create([
            'analysis_run_id' => $run?->id,
            'stage' => $stage,
            'provider' => 'meta',
            'model_identifier' => $response->modelIdentifier,
            'prompt_version' => config('meta_api.prompt_version'),
            'request_hash' => $requestHash,
            'response_status' => 200,
            'latency_ms' => $response->latencyMs,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'created_at' => now(),
        ]);
    }

    public function logFailure(?AnalysisRun $run, string $stage, string $requestHash, string $errorCode): void
    {
        ModelCallLog::create([
            'analysis_run_id' => $run?->id,
            'stage' => $stage,
            'provider' => 'meta',
            'prompt_version' => config('meta_api.prompt_version'),
            'request_hash' => $requestHash,
            'error_code' => $errorCode,
            'created_at' => now(),
        ]);
    }
}
