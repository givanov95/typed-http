<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Contracts;

use Psr\Http\Message\RequestInterface;

interface Authenticator
{
    /**
     * Adds credentials to the outgoing request (headers, signatures...).
     */
    public function apply(RequestInterface $request): RequestInterface;

    /**
     * Transport options needed for authentication (e.g. client certificate), Guzzle option names.
     *
     * @return array<string,mixed>
     */
    public function options(): array;
}
