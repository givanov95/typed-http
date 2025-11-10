<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Requests\Authorization;

interface AuthenticatorInterface
{
    /**
     * Generate the Authorization header value (e.g., "Basic xxx", "Bearer yyy").
     *
     * @return array
     */
    public function getAuthHeaders(): array;
}
