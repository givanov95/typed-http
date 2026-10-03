<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Contracts;

use Psr\Http\Client\ClientInterface;

/**
 * A PSR-18 client that also accepts per-request transport options (timeout, cert, ssl_key, verify...).
 * A plain PSR-18 client works too, it just ignores the options.
 */
interface Transport extends ClientInterface
{
    /**
     * @param array<string,mixed> $options Guzzle option names
     */
    public function withOptions(array $options): static;
}
