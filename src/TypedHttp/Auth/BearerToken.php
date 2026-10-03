<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Auth;

use Givanov95\TypedHttp\Contracts\Authenticator;
use Psr\Http\Message\RequestInterface;

final readonly class BearerToken implements Authenticator
{
    public function __construct(private string $token)
    {
    }

    public function apply(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function options(): array
    {
        return [];
    }
}
