<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Testing;

use AssertionError;
use Closure;
use Givanov95\TypedHttp\Contracts\RequestAware;
use Givanov95\TypedHttp\Contracts\Transport;
use Givanov95\TypedHttp\Request;
use LogicException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use WeakMap;

/**
 * A client for tests: answers with prepared responses and records what was sent. Never touches the network.
 *
 *     $mock = MockClient::make()->on(GetCitiesRequest::class, MockResponse::json(['cities' => []]));
 *     $connector->withClient($mock)->send(new GetCitiesRequest('BGR'));
 *     $mock->assertSent(GetCitiesRequest::class);
 *
 * A matcher is a Request class name or a Closure(RequestInterface, ?Request): bool. A response is a PSR response,
 * a Throwable (thrown, e.g. a NetworkException) or a Closure(RequestInterface): ResponseInterface|Throwable.
 * Several responses are returned in order and the last one repeats.
 */
final class MockClient implements RequestAware, Transport
{
    /** @var list<array{match: null|Closure|string, responses: non-empty-list<Closure|ResponseInterface|Throwable>, used: int}> */
    private array $routes = [];

    /** @var list<array{request: ?Request, psr: RequestInterface, options: array<string,mixed>}> */
    private array $sent = [];

    /** @var array<string,mixed> */
    private array $options = [];

    private ?Request $current = null;

    /** @var null|WeakMap<RequestInterface, Request> the typed request behind a PSR request, kept for repeated sends of the same object */
    private ?WeakMap $typed = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * @param Closure|string $match
     */
    public function on(Closure|string $match, Closure|ResponseInterface|Throwable ...$responses): static
    {
        return $this->addRoute($match, $responses);
    }

    /**
     * Answers any request not matched by an earlier route.
     */
    public function push(Closure|ResponseInterface|Throwable ...$responses): static
    {
        return $this->addRoute(null, $responses);
    }

    public function withOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function forRequest(Request $request): void
    {
        $this->current = $request;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->typed ??= new WeakMap();

        if ($this->current !== null) {
            $this->typed[$request] = $this->current;
            $this->current = null;
        }

        // A decorator such as RetryClient announces the request once and sends the same object several times.
        $typed = $this->typed[$request] ?? null;
        $this->sent[] = ['request' => $typed, 'psr' => $request, 'options' => $this->options];

        foreach ($this->routes as $index => $route) {
            if (! $this->matches($route['match'], $request, $typed)) {
                continue;
            }

            $response = $route['responses'][min($route['used'], count($route['responses']) - 1)];
            $this->routes[$index]['used']++;

            if ($response instanceof Closure) {
                $response = $response($request);
            }

            if ($response instanceof Throwable) {
                throw $response;
            }

            return $response;
        }

        throw new LogicException('MockClient has no response for ' . $request->getMethod() . ' ' . $request->getUri() . '.');
    }

    /**
     * @return list<array{request: ?Request, psr: RequestInterface, options: array<string,mixed>}>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    public function lastRequest(): ?RequestInterface
    {
        return $this->sent === [] ? null : $this->sent[array_key_last($this->sent)]['psr'];
    }

    /**
     * @return array<string,mixed>
     */
    public function lastOptions(): array
    {
        return $this->sent === [] ? [] : $this->sent[array_key_last($this->sent)]['options'];
    }

    /**
     * Asserts a matching request was sent; `$callback(RequestInterface, ?Request): bool` can inspect it further.
     */
    public function assertSent(Closure|string $match, ?Closure $callback = null): void
    {
        if ($this->matching($match, $callback) === []) {
            throw new AssertionError('Expected a request to be sent, but none matched.' . $this->summary());
        }
    }

    public function assertNotSent(Closure|string $match, ?Closure $callback = null): void
    {
        if ($this->matching($match, $callback) !== []) {
            throw new AssertionError('Expected no matching request to be sent, but one was.' . $this->summary());
        }
    }

    public function assertNothingSent(): void
    {
        if ($this->sent !== []) {
            throw new AssertionError('Expected no requests, but ' . count($this->sent) . ' were sent.' . $this->summary());
        }
    }

    public function assertSentCount(int $count): void
    {
        if (count($this->sent) !== $count) {
            throw new AssertionError('Expected ' . $count . ' requests, but ' . count($this->sent) . ' were sent.' . $this->summary());
        }
    }

    /**
     * @param  array<array-key,Closure|ResponseInterface|Throwable> $responses
     * @return $this
     */
    private function addRoute(Closure|string|null $match, array $responses): static
    {
        if ($responses === []) {
            throw new LogicException('Provide at least one response.');
        }

        $this->routes[] = ['match' => $match, 'responses' => array_values($responses), 'used' => 0];

        return $this;
    }

    private function matches(Closure|string|null $match, RequestInterface $psr, ?Request $typed): bool
    {
        return match (true) {
            $match === null     => true,
            is_string($match)   => $typed instanceof $match,
            default             => (bool) $match($psr, $typed),
        };
    }

    /**
     * @return list<array{request: ?Request, psr: RequestInterface, options: array<string,mixed>}>
     */
    private function matching(Closure|string $match, ?Closure $callback): array
    {
        return array_values(array_filter(
            $this->sent,
            fn (array $sent) => $this->matches($match, $sent['psr'], $sent['request'])
                && ($callback === null || $callback($sent['psr'], $sent['request'])),
        ));
    }

    private function summary(): string
    {
        if ($this->sent === []) {
            return ' Nothing was sent.';
        }

        return ' Sent: ' . implode(', ', array_map(
            fn (array $sent) => $sent['psr']->getMethod() . ' ' . $sent['psr']->getUri(),
            $this->sent,
        )) . '.';
    }
}
