<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Transport;

use Givanov95\TypedHttp\Contracts\Transport;
use Givanov95\TypedHttp\Exceptions\NetworkException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Default transport. Error statuses come back as normal responses; the connector decides what to throw.
 */
final class GuzzleTransport implements Transport
{
    private readonly Client $client;

    /**
     * @param array<string,mixed> $options
     */
    public function __construct(?Client $client = null, private array $options = [])
    {
        $this->client = $client ?? new Client();
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->options = [...$this->options, ...$options];

        return $clone;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->send($request, [...$this->options, 'http_errors' => false]);
        } catch (GuzzleException $e) {
            if ($e instanceof ClientExceptionInterface) {
                throw $e;
            }

            throw new NetworkException($e->getMessage(), $request, $e);
        }
    }
}
