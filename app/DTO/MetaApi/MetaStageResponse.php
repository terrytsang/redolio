<?php

namespace App\DTO\MetaApi;

class MetaStageResponse
{
    public function __construct(
        public readonly array $data,
        public readonly ?string $modelIdentifier,
        public readonly ?int $inputTokens,
        public readonly ?int $outputTokens,
        public readonly int $latencyMs,
    ) {}
}
