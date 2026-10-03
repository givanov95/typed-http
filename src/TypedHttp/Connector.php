<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp;

use Closure;
use Givanov95\TypedHttp\Contracts\Authenticator;
use Givanov95\TypedHttp\Contracts\RequestAware;
use Givanov95\TypedHttp\Contracts\Transport;
use Givanov95\TypedHttp\Enums\HttpMethod;
use Givanov95\TypedHttp\Exceptions\ConnectionException;
use Givanov95\TypedHttp\Exceptions\RequestException;
use Givanov95\TypedHttp\Support\BodyEncoder;
use Givanov95\TypedHttp\Transport\GuzzleTransport;
use GuzzleHttp\Psr7\Request as Psr7Request;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

/**
 * One API: base URL, authentication, default headers, timeouts and the client that sends its requests.
 */
abstract class Connector
{
    private static ?Closure $defaultClient = null;

    private ?ClientInterface $client = null;

    /**
     * Sets how connectors without an explicit client get one (the Laravel bridge uses this). Null restores Guzzle.
     */
    public static function useDefaultClient(?Closure $resolver): void
    {
        self::$defaultClient = $resolver;
    }

    public function baseUrl(): string
    {
        return '';
    }

    public function authenticator(): ?Authenticator
    {
        return null;
    }

    /**
     * @return array<string,string>
     */
    public function headers(): array
    {
        return [];
    }

    /**
     * @return array<string,mixed>
     */
    public function defaultQuery(): array
    {
        return [];
    }

    /**
     * Fields added to the body of every request that has one (e.g. API credentials sent in the payload).
     *
     * @return array<string,mixed>
     */
    public function defaultBody(): array
    {
        return [];
    }

    public function timeout(): float
    {
        return 30.0;
    }

    public function connectTimeout(): float
    {
        return 10.0;
    }

    /**
     * Extra transport options (Guzzle option names).
     *
     * @return array<string,mixed>
     */
    public function options(): array
    {
        return [];
    }

    public function withClient(ClientInterface $client): static
    {
        $clone = clone $this;
        $clone->client = $client;

        return $clone;
    }

    public function client(): ClientInterface
    {
        return $this->client ??= (self::$defaultClient !== null ? (self::$defaultClient)() : new GuzzleTransport());
    }

    /**
     * @throws ConnectionException when no response arrives
     * @throws RequestException    when the response is an error, see shouldThrow()
     */
    public function send(Request $request): Response
    {
        $psrRequest = $this->buildRequest($request);
        $authenticator = $this->authenticatorFor($request);

        if ($authenticator !== null) {
            $psrRequest = $authenticator->apply($psrRequest);
        }

        $client = $this->client();

        if ($client instanceof Transport) {
            $client = $client->withOptions([
                'timeout'         => $this->timeout(),
                'connect_timeout' => $this->connectTimeout(),
                ...$this->options(),
                ...($authenticator?->options() ?? []),
            ]);
        }

        if ($client instanceof RequestAware) {
            $client->forRequest($request);
        }

        try {
            $psrResponse = $client->sendRequest($psrRequest);
        } catch (ClientExceptionInterface $e) {
            throw new ConnectionException('Request failed: ' . $e->getMessage(), $e, $psrRequest);
        }

        $response = new Response($psrResponse, $request);

        if ($this->shouldThrow($response)) {
            throw new RequestException($response);
        }

        return $response;
    }

    /**
     * Whether a response should be raised as a RequestException. Override to read error bodies yourself.
     */
    protected function shouldThrow(Response $response): bool
    {
        return $response->failed();
    }

    private function authenticatorFor(Request $request): ?Authenticator
    {
        if ($request->skipsAuthentication()) {
            return null;
        }

        return $request->authenticator() ?? $this->authenticator();
    }

    private function buildRequest(Request $request): RequestInterface
    {
        $method = $request->method();
        $query = [...$this->defaultQuery(), ...$request->query()];
        $payload = $request->body();

        if ($method->hasBody()) {
            $payload = [...$this->defaultBody(), ...$payload];
        } else {
            $query = [...$query, ...$payload];
            $payload = [];
        }

        $headers = [...$this->headers(), ...$request->headers()];
        $body = null;

        if ($method->hasBody() && ($payload !== [] || in_array($method, [HttpMethod::POST, HttpMethod::PUT, HttpMethod::PATCH], true))) {
            $encoded = BodyEncoder::encode($request->bodyFormat(), $payload);
            $body = $encoded->stream;
            $headers = ['Content-Type' => $encoded->contentType, ...$headers];
        }

        $accepts = $request->accepts();

        if ($accepts !== null && ! array_key_exists('accept', array_change_key_case($headers, \CASE_LOWER))) {
            $headers['Accept'] = $accepts;
        }

        return new Psr7Request($method->value, $this->uri($request->endpoint(), $query), $headers, $body);
    }

    /**
     * @param array<string,mixed> $query
     */
    private function uri(string $endpoint, array $query): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $endpoint) === 1) {
            $uri = $endpoint;
        } elseif ($this->baseUrl() === '') {
            throw new InvalidArgumentException('Endpoint "' . $endpoint . '" is relative but the connector has no base URL.');
        } else {
            $uri = rtrim($this->baseUrl(), '/') . '/' . ltrim($endpoint, '/');
        }

        $queryString = http_build_query($query, '', '&', \PHP_QUERY_RFC3986);

        return $queryString === '' ? $uri : $uri . (str_contains($uri, '?') ? '&' : '?') . $queryString;
    }
}
