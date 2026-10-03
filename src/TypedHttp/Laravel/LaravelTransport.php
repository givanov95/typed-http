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
    /**
     * @param array<string,mixed> $options
     */
    public function __construct(private readonly Factory $http, private array $options = [])
    {
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->options = [...$this->options, ...$options];

        return $clone;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $pending = $this->http->withOptions($this->options);

        $headers = [];
        foreach (array_keys($request->getHeaders()) as $name) {
            $headers[$name] = $request->getHeaderLine($name);
        }

        $content = (string) $request->getBody();

        if ($content !== '' || $request->hasHeader('Content-Type')) {
            $pending->withBody($content, $request->getHeaderLine('Content-Type'));
            $headers = array_filter($headers, fn (string $name) => strtolower($name) !== 'content-type', \ARRAY_FILTER_USE_KEY);
        }

        try {
            return $pending->replaceHeaders($headers)->send($request->getMethod(), (string) $request->getUri())->toPsrResponse();
        } catch (ConnectionException $e) {
            throw new NetworkException($e->getMessage(), $request, $e);
        }
    }
}
