<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Transport;

use Givanov95\TypedHttp\Contracts\Transport;
use Givanov95\TypedHttp\Exceptions\NetworkException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Default transport. Error statuses come back as normal responses; the connector decides what to throw.
 */
final class GuzzleTransport implements Transport
{
    private readonly Client $client;

    /** @var array<string,mixed> */
    private array $requestOptions = [];

    /**
     * @param array<string,mixed> $options Fixed for this transport; they win over the options the connector passes per request
     */
    public function __construct(?Client $client = null, private readonly array $options = [])
    {
        $this->client = $client ?? new Client();
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->requestOptions = [...$this->requestOptions, ...$options];

        return $clone;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->send($request, [...$this->requestOptions, ...$this->options, 'http_errors' => false]);
        } catch (RequestException $e) {
            // Guzzle only turns a few cURL errors into ConnectException; a reset connection or a failed receive
            // arrives here without a response and is just as much a network error.
            if ($e->hasResponse()) {
                throw $e;
            }

            throw new NetworkException($e->getMessage(), $request, $e);
        }
    }
}
