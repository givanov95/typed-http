<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Transport;

use Givanov95\TypedHttp\Exceptions\ConnectionException;
use Givanov95\TypedHttp\Exceptions\NetworkException;
use Givanov95\TypedHttp\Exceptions\RequestException;
use Givanov95\TypedHttp\Tests\Fixtures\ApiConnector;
use Givanov95\TypedHttp\Tests\Fixtures\FindSiteRequest;
use Givanov95\TypedHttp\Transport\GuzzleTransport;
use Givanov95\TypedHttp\Transport\RetryClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class GuzzleTransportTest extends TestCase
{
    /** @var list<array<string,mixed>> */
    private array $history = [];

    /**
     * @param array<string,mixed> $options
     */
    private function transport(MockHandler $handler, array $options = []): GuzzleTransport
    {
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::history($this->history));

        return new GuzzleTransport(new Client(['handler' => $stack]), $options);
    }

    public function test_error_statuses_are_returned_not_thrown(): void
    {
        $transport = $this->transport(new MockHandler([new Response(422, [], '{"error":"bad"}')]));

        $response = $transport->sendRequest(new Request('POST', 'https://x.test'));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('{"error":"bad"}', (string) $response->getBody());
    }

    public function test_options_are_passed_on_to_guzzle(): void
    {
        $transport = $this->transport(new MockHandler([new Response(200)]))->withOptions(['timeout' => 5.0]);

        $transport->sendRequest(new Request('GET', 'https://x.test'));

        $this->assertSame(5.0, $this->history[0]['options']['timeout']);
    }

    public function test_options_of_the_transport_win_over_the_ones_the_connector_passes(): void
    {
        $transport = $this->transport(new MockHandler([new Response(200)]), ['timeout' => 120.0])->withOptions(['timeout' => 30.0, 'connect_timeout' => 10.0]);

        $transport->sendRequest(new Request('GET', 'https://x.test'));

        $this->assertSame(120.0, $this->history[0]['options']['timeout']);
        $this->assertSame(10.0, $this->history[0]['options']['connect_timeout']);
    }

    // Guzzle raises only a few cURL errors as ConnectException; a reset connection (56) is a plain RequestException.
    public function test_a_failure_without_a_response_is_a_network_error_and_gets_retried(): void
    {
        $request = new Request('GET', 'https://x.test');
        $handler = new MockHandler([
            new GuzzleRequestException('cURL error 56: Recv failure: Connection reset by peer', $request),
            new Response(200, [], 'ok'),
        ]);
        $retry = new RetryClient($this->transport($handler), times: 1, sleep: static function (int $ms): void {
        });

        $this->assertSame('ok', (string) $retry->sendRequest($request)->getBody());
        $this->assertCount(2, $this->history);
    }

    public function test_a_request_exception_without_a_response_is_raised_as_network_exception(): void
    {
        $request = new Request('GET', 'https://x.test');
        $transport = $this->transport(new MockHandler([new GuzzleRequestException('cURL error 55', $request)]));

        $this->expectException(NetworkException::class);

        $transport->sendRequest($request);
    }

    public function test_connector_over_guzzle_maps_error_status_and_connection_failure(): void
    {
        $handler = new MockHandler([
            new Response(500, [], 'boom'),
            new ConnectException('dns failure', new Request('POST', 'https://api.test')),
        ]);
        $connector = (new ApiConnector())->withClient($this->transport($handler));

        try {
            $connector->send(new FindSiteRequest());
            $this->fail('RequestException expected');
        } catch (RequestException $e) {
            $this->assertSame(500, $e->status());
            $this->assertSame('boom', $e->body());
        }

        $this->expectException(ConnectionException::class);
        $connector->send(new FindSiteRequest());
    }
}
