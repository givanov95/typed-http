<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Requests\Authorization\BasicAuth;

use Givanov95\TypedHttp\Requests\Authorization\BasicAuth\BasicAuthAuthenticator;

interface BasicAuthInterface
{
    /**
     * Get the OAuth authenticator instance for this request
     */
    public function getAuthenticator(): BasicAuthAuthenticator;
}
