<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Transport;

use Closure;
use Givanov95\TypedHttp\Contracts\RequestAware;
use Givanov95\TypedHttp\Contracts\Transport;
use Givanov95\TypedHttp\Enums\HttpMethod;
use Givanov95\TypedHttp\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Repeats failed requests (network errors and 429/502/503/504) with exponential backoff, a single pause is at most 30 s.
 * Only idempotent methods are repeated unless `$retryUnsafe` is set: a repeated POST can create a duplicate.
 * A body that cannot be rewound is never repeated: the second attempt would send it empty or cut short.
 */
final class RetryClient implements RequestAware, Transport
{
    private const MAX_DELAY_MS = 30_000;

    private readonly Closure $sleep;

    /**
     * @param list<int>    $retryStatuses
     * @param null|Closure $sleep         receives milliseconds; defaults to usleep
     */
    public function __construct(
        private ClientInterface $client,
        private readonly int $times = 2,
        private readonly int $delayMs = 200,
        private readonly array $retryStatuses = [429, 502, 503, 504],
        private readonly bool $retryUnsafe = false,
        ?Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static fn (int $ms) => usleep($ms * 1000);
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;

        if ($this->client instanceof Transport) {
            $clone->client = $this->client->withOptions($options);
        }

        return $clone;
    }

    public function forRequest(Request $request): void
    {
        if ($this->client instanceof RequestAware) {
            $this->client->forRequest($request);
        }
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        for ($attempt = 0; ; $attempt++) {
            $canRetry = $attempt < $this->times && $this->mayRepeat($request);

            if ($request->getBody()->isSeekable()) {
                $request->getBody()->rewind();
            }

            try {
                $response = $this->client->sendRequest($request);
            } catch (NetworkExceptionInterface $e) {
                if (! $canRetry) {
                    throw $e;
                }

                ($this->sleep)($this->backoff($attempt));

                continue;
            }

            if (! $canRetry || ! in_array($response->getStatusCode(), $this->retryStatuses, true)) {
                return $response;
            }

            ($this->sleep)($this->retryAfter($response) ?? $this->backoff($attempt));
        }
    }

    private function mayRepeat(RequestInterface $request): bool
    {
        if (! $request->getBody()->isSeekable()) {
            return false;
        }

        if ($this->retryUnsafe) {
            return true;
        }

        return HttpMethod::tryFrom(strtoupper($request->getMethod()))?->isIdempotent() ?? false;
    }

    private function backoff(int $attempt): int
    {
        // The exponent is capped as well: 2 ** 64 is a float and would not fit the int return type.
        return (int) min($this->delayMs * (2 ** min($attempt, 20)), self::MAX_DELAY_MS);
    }

    private function retryAfter(ResponseInterface $response): ?int
    {
        $header = $response->getHeaderLine('Retry-After');

        return ctype_digit($header) ? min((int) $header * 1000, self::MAX_DELAY_MS) : null;
    }
}
