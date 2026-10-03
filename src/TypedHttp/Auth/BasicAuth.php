<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Auth;

use Givanov95\TypedHttp\Contracts\Authenticator;
use Psr\Http\Message\RequestInterface;

final readonly class BasicAuth implements Authenticator
{
    public function __construct(
        private string $username,
        private string $password,
    ) {
    }

    public function apply(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', 'Basic ' . base64_encode($this->username . ':' . $this->password));
    }

    public function options(): array
    {
        return [];
    }
}
