<?php

namespace App\Services\MetaApi\Exceptions;

class MetaApiNotConfiguredException extends MetaApiException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(
            $message ?? 'Meta Model API is not configured (missing base URL, API key or model ID). '.
            'See docs/meta-api-verified.md. Falling back to fixture mode.',
            'meta_api_not_configured'
        );
    }
}
