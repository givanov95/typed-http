<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Requests\Authorization\BasicAuth;

use Givanov95\TypedHttp\Requests\Authorization\AuthenticatorInterface;

final readonly class BasicAuthAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private string $username,
        private string $password,
        private ?string $clientId = null
    ) {
    }

    /**
     * Generate the Basic Auth header.
     *
     * @return string
     */
    public function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
        ];
    }

    /**
     * Get the username.
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Get the client ID.
     *
     * @return string
     */
    public function getClientId(): ?string
    {
        return $this->clientId;
    }
}
