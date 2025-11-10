<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Requests\Authorization\BearerToken;

interface BearerTokenInterface
{
    /**
     * Get the OAuth authenticator instance for this request
     */
    public function getAuthenticator(): BearerTokenAuthenticator;
}
