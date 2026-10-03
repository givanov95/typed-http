<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Transport;

use Givanov95\TypedHttp\Exceptions\NetworkException;
use Givanov95\TypedHttp\Testing\MockClient;
use Givanov95\TypedHttp\Testing\MockResponse;
use Givanov95\TypedHttp\Transport\RetryClient;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

final class RetryClientTest extends TestCase
{
    /** @var list<int> */
    private array $sleeps = [];

    private function retry(MockClient $mock, int $times = 2, bool $unsafe = false): RetryClient
    {
        return new RetryClient($mock, times: $times, delayMs: 100, retryUnsafe: $unsafe, sleep: function (int $ms): void {
            $this->sleeps[] = $ms;
        });
    }

    public function test_retries_a_get_on_503_with_exponential_backoff(): void
    {
        $mock = MockClient::make()->push(MockResponse::empty(503), MockResponse::empty(503), MockResponse::json(['ok' => 1]));

        $response = $this->retry($mock)->sendRequest(new Request('GET', 'https://x.test'));

        $this->assertSame(200, $response->getStatusCode());
        $mock->assertSentCount(3);
        $this->assertSame([100, 200], $this->sleeps);
    }

    public function test_gives_up_after_the_configured_attempts(): void
    {
        $mock = MockClient::make()->push(MockResponse::empty(503));

        $response = $this->retry($mock, times: 1)->sendRequest(new Request('GET', 'https://x.test'));

        $this->assertSame(503, $response->getStatusCode());
        $mock->assertSentCount(2);
    }

    public function test_a_post_is_not_repeated_by_default(): void
    {
        $mock = MockClient::make()->push(MockResponse::empty(503));

        $response = $this->retry($mock)->sendRequest(new Request('POST', 'https://x.test', [], '{}'));

        $this->assertSame(503, $response->getStatusCode());
        $mock->assertSentCount(1);
    }

    public function test_a_post_is_repeated_when_unsafe_retries_are_enabled(): void
    {
        $mock = MockClient::make()->push(MockResponse::empty(503), MockResponse::empty(200));

        $this->retry($mock, unsafe: true)->sendRequest(new Request('POST', 'https://x.test', [], '{}'));

        $this->assertCount(2, $mock->sent());
    }

    public function test_network_errors_are_retried_for_idempotent_requests_then_rethrown(): void
    {
        $request = new Request('GET', 'https://x.test');
        $mock = MockClient::make()->push(new NetworkException('down', $request));

        $this->expectException(NetworkException::class);

        try {
            $this->retry($mock)->sendRequest($request);
        } finally {
            $mock->assertSentCount(3);
        }
    }

    public function test_retry_after_header_is_honoured_and_capped(): void
    {
        $mock = MockClient::make()->push(
            MockResponse::make('', 429, ['Retry-After' => '2']),
            MockResponse::make('', 429, ['Retry-After' => '999']),
            MockResponse::empty(200),
        );

        $this->retry($mock)->sendRequest(new Request('GET', 'https://x.test'));

        $this->assertSame([2000, 30000], $this->sleeps);
    }

    public function test_a_non_retryable_status_is_returned_immediately(): void
    {
        $mock = MockClient::make()->push(MockResponse::empty(404));

        $this->assertSame(404, $this->retry($mock)->sendRequest(new Request('GET', 'https://x.test'))->getStatusCode());
        $mock->assertSentCount(1);
    }
}
