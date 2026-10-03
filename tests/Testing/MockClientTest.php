<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Testing;

use AssertionError;
use Givanov95\TypedHttp\Testing\MockClient;
use Givanov95\TypedHttp\Testing\MockResponse;
use Givanov95\TypedHttp\Tests\Fixtures\ApiConnector;
use Givanov95\TypedHttp\Tests\Fixtures\FindSiteRequest;
use Givanov95\TypedHttp\Tests\Fixtures\ListCitiesRequest;
use Givanov95\TypedHttp\Transport\RetryClient;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class MockClientTest extends TestCase
{
    public function test_routes_are_matched_by_request_class_and_first_match_wins(): void
    {
        $mock = MockClient::make()
            ->on(FindSiteRequest::class, MockResponse::json(['site' => 1]))
            ->push(MockResponse::json(['fallback' => 1]));
        $connector = (new ApiConnector())->withClient($mock);

        $this->assertSame(['site' => 1], $connector->send(new FindSiteRequest())->json());
        $this->assertSame(['fallback' => 1], $connector->send(new ListCitiesRequest('BG'))->json());
    }

    public function test_responses_are_returned_in_order_and_the_last_repeats(): void
    {
        $mock = MockClient::make()->push(MockResponse::json(['n' => 1]), MockResponse::json(['n' => 2]));
        $connector = (new ApiConnector())->withClient($mock);

        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            $numbers[] = $connector->send(new FindSiteRequest())->json('n');
        }

        $this->assertSame([1, 2, 2], $numbers);
    }

    public function test_closure_matcher_and_closure_response(): void
    {
        $mock = MockClient::make()->on(
            fn (RequestInterface $r) => str_contains((string) $r->getUri(), 'cities'),
            fn (RequestInterface $r) => MockResponse::json(['uri' => (string) $r->getUri()]),
        );

        $json = (new ApiConnector())->withClient($mock)->send(new ListCitiesRequest('BG'))->json();

        $this->assertSame('https://api.test/v1/cities?countryCode=BG', $json['uri']);
    }

    public function test_an_unmatched_request_fails_loudly(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('POST https://api.test/v1/location/site/');

        (new ApiConnector())->withClient(MockClient::make())->send(new FindSiteRequest());
    }

    // RetryClient announces the request once and sends the same PSR request again: routes by class must still match.
    public function test_the_typed_request_survives_repeated_sends_behind_retry_client(): void
    {
        $mock = MockClient::make()->on(FindSiteRequest::class, MockResponse::empty(503), MockResponse::json(['ok' => 1]));
        $retry = new RetryClient($mock, times: 2, retryUnsafe: true, sleep: static function (int $ms): void {
        });

        $response = (new ApiConnector())->withClient($retry)->send(new FindSiteRequest());

        $this->assertSame(['ok' => 1], $response->json());
        $mock->assertSentCount(2);
        $typed = array_filter($mock->sent(), fn (array $sent) => $sent['request'] instanceof FindSiteRequest);
        $this->assertCount(2, $typed);
    }

    public function test_assertions(): void
    {
        $mock = MockClient::make()->push(MockResponse::json([]));
        $connector = (new ApiConnector())->withClient($mock);

        $mock->assertNothingSent();
        $connector->send(new FindSiteRequest(name: 'Varna'));

        $mock->assertSent(FindSiteRequest::class);
        $mock->assertSent(FindSiteRequest::class, fn (RequestInterface $r, $typed) => $typed->name === 'Varna');
        $mock->assertNotSent(ListCitiesRequest::class);
        $mock->assertNotSent(FindSiteRequest::class, fn (RequestInterface $r, $typed) => $typed->name === 'Sofia');
        $mock->assertSentCount(1);

        foreach ([
            fn () => $mock->assertSent(ListCitiesRequest::class),
            fn () => $mock->assertNotSent(FindSiteRequest::class),
            fn () => $mock->assertNothingSent(),
            fn () => $mock->assertSentCount(2),
        ] as $failing) {
            try {
                $failing();
                $this->fail('AssertionError expected');
            } catch (AssertionError $e) {
                $this->assertStringContainsString('POST https://api.test/v1/location/site/', $e->getMessage());
            }
        }
    }
}
