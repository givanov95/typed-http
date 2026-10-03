<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Laravel;

use Givanov95\TypedHttp\Contracts\Transport;
use Givanov95\TypedHttp\Exceptions\NetworkException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Sends requests through Laravel's HTTP client, so Http::fake(), Http::preventStrayRequests(),
 * events and Telescope see them like any other outgoing request.
 */
final class LaravelTransport implements Transport
{
    /** @var array<string,mixed> */
    private array $requestOptions = [];

    /**
     * @param array<string,mixed> $options Fixed for this transport; they win over the options the connector passes per request
     */
    public function __construct(private readonly Factory $http, private readonly array $options = [])
    {
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->requestOptions = [...$this->requestOptions, ...$options];

        return $clone;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $pending = $this->http->withOptions([...$this->requestOptions, ...$this->options]);

        $headers = [];
        foreach (array_keys($request->getHeaders()) as $name) {
            $headers[$name] = $request->getHeaderLine($name);
        }

        $body = $request->getBody();

        // A null size (unknown length) means there is something to send, so only a known 0 counts as no body.
        if ($body->getSize() !== 0 || $request->hasHeader('Content-Type')) {
            // The stream goes through as it is: casting it to a string would hold a whole upload in memory.
            $pending->withBody($body, $request->getHeaderLine('Content-Type'));
            $headers = array_filter($headers, fn (string $name) => strtolower($name) !== 'content-type', \ARRAY_FILTER_USE_KEY);
        }

        try {
            return $pending->replaceHeaders($headers)->send($request->getMethod(), (string) $request->getUri())->toPsrResponse();
        } catch (ConnectionException $e) {
            throw new NetworkException($e->getMessage(), $request, $e);
        }
    }
}
