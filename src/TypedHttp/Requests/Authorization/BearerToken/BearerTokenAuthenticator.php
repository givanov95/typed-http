<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Requests\Authorization\BearerToken;

use Givanov95\TypedHttp\Requests\Authorization\AuthenticatorInterface;

final readonly class BearerTokenAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private string $accessToken,
    ) {
    }

    /**
     * Generate the Bearer Auth header.
     *
     * @return array
     */
    public function getAuthHeaders(): array
    {
        return ['Bearer ' . $this->accessToken];
    }

    /**
     * Get the access token.
     *
     * @return string
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }
}
