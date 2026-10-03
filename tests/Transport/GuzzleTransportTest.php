<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Transport;

use Givanov95\TypedHttp\Exceptions\ConnectionException;
use Givanov95\TypedHttp\Exceptions\RequestException;
use Givanov95\TypedHttp\Tests\Fixtures\ApiConnector;
use Givanov95\TypedHttp\Tests\Fixtures\FindSiteRequest;
use Givanov95\TypedHttp\Transport\GuzzleTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
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

    private function transport(MockHandler $handler): GuzzleTransport
    {
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::history($this->history));

        return new GuzzleTransport(new Client(['handler' => $stack]));
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
