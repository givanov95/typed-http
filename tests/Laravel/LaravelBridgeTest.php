<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Laravel;

use Givanov95\TypedHttp\Auth\BasicAuth;
use Givanov95\TypedHttp\Connector;
use Givanov95\TypedHttp\Contracts\Transport;
use Givanov95\TypedHttp\Enums\BodyFormat;
use Givanov95\TypedHttp\Enums\HttpMethod;
use Givanov95\TypedHttp\Exceptions\ConnectionException;
use Givanov95\TypedHttp\Exceptions\RequestException;
use Givanov95\TypedHttp\Laravel\LaravelTransport;
use Givanov95\TypedHttp\Laravel\TypedHttpServiceProvider;
use Givanov95\TypedHttp\Request;
use Givanov95\TypedHttp\Tests\Fixtures\ApiConnector;
use Givanov95\TypedHttp\Tests\Fixtures\FindSiteRequest;
use Givanov95\TypedHttp\Tests\Fixtures\ListCitiesRequest;
use Givanov95\TypedHttp\Transport\RetryClient;
use Illuminate\Http\Client\ConnectionException as LaravelConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use SplFileInfo;

final class LaravelBridgeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [TypedHttpServiceProvider::class];
    }

    protected function tearDown(): void
    {
        // The resolver is static: leave nothing behind for tests that run without the bridge.
        Connector::useDefaultClient(null);

        parent::tearDown();
    }

    public function test_connectors_use_laravels_http_client_by_default(): void
    {
        $this->assertInstanceOf(LaravelTransport::class, app(Transport::class));

        Http::fake(['api.test/*' => Http::response(['sites' => [1]])]);

        $response = (new ApiConnector(new BasicAuth('u', 'p')))->send(new FindSiteRequest(name: 'Varna'));

        $this->assertSame([1], $response->json('sites'));
        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'https://api.test/v1/location/site/'
                && $request->method() === 'POST'
                && $request->data()['name'] === 'Varna'
                && $request->data()['userName'] === 'user'
                && $request->header('Authorization') === ['Basic ' . base64_encode('u:p')]
                && $request->header('Accept') === ['application/json']
                && $request->header('Content-Type') === ['application/json'];
        });
    }

    public function test_get_requests_carry_the_query_and_no_body_headers(): void
    {
        Http::fake();

        (new ApiConnector())->send(new ListCitiesRequest('BG', page: 2));

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.test/v1/cities?countryCode=BG&page=2'
            && $request->method() === 'GET'
            && ! $request->hasHeader('Content-Type'));
    }

    public function test_timeouts_reach_the_laravel_client(): void
    {
        $seen = [];
        Http::fake(function (HttpRequest $request, array $options) use (&$seen) {
            $seen = $options;

            return Http::response([]);
        });

        (new ApiConnector())->send(new FindSiteRequest());

        $this->assertSame(30.0, $seen['timeout']);
        $this->assertSame(10.0, $seen['connect_timeout']);
    }

    public function test_error_status_keeps_response_and_body(): void
    {
        Http::fake(['*' => Http::response(['error' => 'bad name'], 422)]);

        try {
            (new ApiConnector())->send(new FindSiteRequest());
            $this->fail('RequestException expected');
        } catch (RequestException $e) {
            $this->assertSame(422, $e->status());
            $this->assertSame('bad name', $e->response()->json('error'));
        }
    }

    public function test_connection_failures_become_connection_exceptions(): void
    {
        Http::fake(fn () => throw new LaravelConnectionException('cURL error 28: timed out'));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('timed out');

        (new ApiConnector())->send(new FindSiteRequest());
    }

    public function test_prevent_stray_requests_applies_to_typed_requests(): void
    {
        Http::preventStrayRequests();
        Http::fake(['other.test/*' => Http::response()]);

        // A ConnectionException is a RuntimeException too, so only this type proves the request went through Http::.
        $this->expectException(StrayRequestException::class);

        (new ApiConnector())->send(new FindSiteRequest());
    }

    public function test_a_multipart_upload_is_passed_to_laravel_as_a_stream(): void
    {
        Http::fake();
        $path = tempnam(sys_get_temp_dir(), 'typed-http');
        file_put_contents($path, 'FILEDATA');

        $request = new class ($path) extends Request {
            public SplFileInfo $file;

            public function __construct(string $path)
            {
                $this->file = new SplFileInfo($path);
            }

            public function method(): HttpMethod
            {
                return HttpMethod::POST;
            }

            public function endpoint(): string
            {
                return 'upload';
            }

            public function bodyFormat(): BodyFormat
            {
                return BodyFormat::Multipart;
            }
        };

        try {
            (new ApiConnector())->send($request);
        } finally {
            unlink($path);
        }

        Http::assertSent(fn (HttpRequest $sent) => str_starts_with($sent->header('Content-Type')[0], 'multipart/form-data; boundary=')
            && str_contains($sent->body(), 'filename="' . basename($path) . '"')
            && str_contains($sent->body(), 'FILEDATA'));
    }

    public function test_retry_is_off_by_default(): void
    {
        Http::fake(['*' => Http::sequence()->push('', 503)->push(['ok' => true])]);

        try {
            (new ApiConnector())->send(new ListCitiesRequest('BG'));
            $this->fail('RequestException expected');
        } catch (RequestException $e) {
            $this->assertSame(503, $e->status());
        }

        Http::assertSentCount(1);
    }

    public function test_retry_can_be_enabled_in_config(): void
    {
        config(['typed-http.retry' => ['times' => 2, 'delay' => 0]]);
        $this->assertInstanceOf(RetryClient::class, app(Transport::class));

        Http::fake(['*' => Http::sequence()->push('', 503)->push(['ok' => true])]);

        $response = (new ApiConnector())->send(new ListCitiesRequest('BG'));

        $this->assertSame(200, $response->status());
        Http::assertSentCount(2);
    }
}
