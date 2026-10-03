<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests;

use Givanov95\TypedHttp\Auth\BasicAuth;
use Givanov95\TypedHttp\Auth\BearerToken;
use Givanov95\TypedHttp\Auth\Certificate;
use Givanov95\TypedHttp\Connector;
use Givanov95\TypedHttp\Enums\BodyFormat;
use Givanov95\TypedHttp\Enums\HttpMethod;
use Givanov95\TypedHttp\Exceptions\ConnectionException;
use Givanov95\TypedHttp\Exceptions\NetworkException;
use Givanov95\TypedHttp\Exceptions\RequestException;
use Givanov95\TypedHttp\Request;
use Givanov95\TypedHttp\Response;
use Givanov95\TypedHttp\Testing\MockClient;
use Givanov95\TypedHttp\Testing\MockResponse;
use Givanov95\TypedHttp\Tests\Fixtures\ApiConnector;
use Givanov95\TypedHttp\Tests\Fixtures\FindSiteRequest;
use Givanov95\TypedHttp\Tests\Fixtures\ListCitiesRequest;
use GuzzleHttp\Psr7\Request as Psr7Request;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class ConnectorTest extends TestCase
{
    private MockClient $mock;

    protected function setUp(): void
    {
        $this->mock = MockClient::make()->push(MockResponse::json(['ok' => true]));
    }

    private function connector(?\Givanov95\TypedHttp\Contracts\Authenticator $auth = null): Connector
    {
        return (new ApiConnector($auth))->withClient($this->mock);
    }

    // Regression for v1 bug #1: properties declared in a parent class were not sent.
    public function test_inherited_properties_are_sent_in_the_body(): void
    {
        $this->connector()->send(new FindSiteRequest(name: 'Varna'));

        $body = json_decode((string) $this->mock->lastRequest()->getBody(), true);
        $this->assertSame('user', $body['userName']);
        $this->assertSame('secret', $body['password']);
        $this->assertSame('EN', $body['language']);
        $this->assertSame('Varna', $body['name']);
    }

    public function test_post_sets_json_content_type_accept_and_resolves_the_url(): void
    {
        $this->connector()->send(new FindSiteRequest(name: 'Varna'));

        $request = $this->mock->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.test/v1/location/site/', (string) $request->getUri());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    // Regression for v1 bug: GET with JSON content type put the JSON into the query string.
    public function test_get_sends_properties_as_query_string_without_body(): void
    {
        $this->connector()->send(new ListCitiesRequest('BG', page: 2, search: 'a b'));

        $request = $this->mock->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://api.test/v1/cities?countryCode=BG&page=2&search=a%20b', (string) $request->getUri());
        $this->assertSame('', (string) $request->getBody());
        $this->assertFalse($request->hasHeader('Content-Type'));
    }

    public function test_default_body_and_query_are_merged_and_the_request_wins(): void
    {
        $connector = new class () extends ApiConnector {
            public function defaultBody(): array
            {
                return ['userName' => 'from-connector', 'extra' => 1];
            }

            public function defaultQuery(): array
            {
                return ['v' => '2'];
            }
        };

        $connector->withClient($this->mock)->send(new FindSiteRequest());

        $body = json_decode((string) $this->mock->lastRequest()->getBody(), true);
        $this->assertSame('user', $body['userName']);
        $this->assertSame(1, $body['extra']);
        $this->assertSame('v=2', $this->mock->lastRequest()->getUri()->getQuery());
    }

    public function test_absolute_endpoint_ignores_base_url_and_relative_without_base_fails(): void
    {
        $request = new class () extends Request {
            public function method(): HttpMethod
            {
                return HttpMethod::DELETE;
            }

            public function endpoint(): string
            {
                return 'https://other.test/items/1?force=1';
            }

            public function query(): array
            {
                return ['x' => 'y'];
            }
        };

        $this->connector()->send($request);
        $sent = $this->mock->lastRequest();
        $this->assertSame('https://other.test/items/1?force=1&x=y', (string) $sent->getUri());
        $this->assertSame('', (string) $sent->getBody());

        $this->expectException(InvalidArgumentException::class);
        (new class () extends Connector {
        })->withClient($this->mock)->send(new ListCitiesRequest('BG'));
    }

    public function test_connector_headers_request_headers_and_custom_accept(): void
    {
        $connector = new class () extends ApiConnector {
            public function headers(): array
            {
                return ['X-Api' => 'c', 'X-Shared' => 'connector'];
            }
        };
        $request = new class () extends Request {
            public function method(): HttpMethod
            {
                return HttpMethod::GET;
            }

            public function endpoint(): string
            {
                return 'x';
            }

            public function headers(): array
            {
                return ['X-Shared' => 'request', 'accept' => 'text/csv'];
            }
        };

        $connector->withClient($this->mock)->send($request);

        $sent = $this->mock->lastRequest();
        $this->assertSame('c', $sent->getHeaderLine('X-Api'));
        $this->assertSame('request', $sent->getHeaderLine('X-Shared'));
        $this->assertSame('text/csv', $sent->getHeaderLine('Accept'));
    }

    public function test_multipart_request(): void
    {
        $request = new class () extends Request {
            public string $title = 'Hi';

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

        $this->connector()->send($request);

        $sent = $this->mock->lastRequest();
        $this->assertStringStartsWith('multipart/form-data; boundary=', $sent->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('name="title"', (string) $sent->getBody());
    }

    public function test_connector_authenticator_is_applied_and_request_can_override_or_skip_it(): void
    {
        $this->connector(new BearerToken('conn'))->send(new FindSiteRequest());
        $this->assertSame('Bearer conn', $this->mock->lastRequest()->getHeaderLine('Authorization'));

        $override = new class () extends Request {
            public function method(): HttpMethod
            {
                return HttpMethod::GET;
            }

            public function endpoint(): string
            {
                return 'x';
            }

            public function authenticator(): BasicAuth
            {
                return new BasicAuth('u', 'p');
            }
        };
        $this->connector(new BearerToken('conn'))->send($override);
        $this->assertSame('Basic ' . base64_encode('u:p'), $this->mock->lastRequest()->getHeaderLine('Authorization'));

        $skip = new class () extends Request {
            public function method(): HttpMethod
            {
                return HttpMethod::GET;
            }

            public function endpoint(): string
            {
                return 'x';
            }

            public function skipsAuthentication(): bool
            {
                return true;
            }
        };
        $this->connector(new BearerToken('conn'))->send($skip);
        $this->assertFalse($this->mock->lastRequest()->hasHeader('Authorization'));
    }

    public function test_timeouts_and_certificate_options_reach_the_transport(): void
    {
        $this->connector(new Certificate('/c.pem', '/k.pem', null, '/ca.pem'))->send(new FindSiteRequest());

        $options = $this->mock->lastOptions();
        $this->assertSame(30.0, $options['timeout']);
        $this->assertSame(10.0, $options['connect_timeout']);
        $this->assertSame('/c.pem', $options['cert']);
        $this->assertSame('/k.pem', $options['ssl_key']);
        $this->assertSame('/ca.pem', $options['verify']);
    }

    // Regression for v1 bug #2: the exception had no response, status or body.
    public function test_error_status_throws_with_the_full_response(): void
    {
        $mock = MockClient::make()->push(MockResponse::json(['error' => 'bad name'], 422));

        try {
            (new ApiConnector())->withClient($mock)->send(new FindSiteRequest());
            $this->fail('RequestException expected');
        } catch (RequestException $e) {
            $this->assertSame(422, $e->status());
            $this->assertSame(422, $e->getCode());
            $this->assertSame('bad name', $e->response()->json('error'));
            $this->assertSame('{"error":"bad name"}', $e->body());
            $this->assertStringContainsString('422', $e->getMessage());
        }
    }

    public function test_should_throw_can_be_overridden(): void
    {
        $connector = new class () extends ApiConnector {
            protected function shouldThrow(Response $response): bool
            {
                return $response->status() >= 500;
            }
        };

        $mock = MockClient::make()->push(MockResponse::json(['error' => 'x'], 404));
        $response = $connector->withClient($mock)->send(new FindSiteRequest());

        $this->assertSame(404, $response->status());
    }

    public function test_network_failure_becomes_connection_exception(): void
    {
        $mock = MockClient::make()->push(new NetworkException('timeout', new Psr7Request('POST', 'https://api.test')));

        try {
            (new ApiConnector())->withClient($mock)->send(new FindSiteRequest());
            $this->fail('ConnectionException expected');
        } catch (ConnectionException $e) {
            $this->assertStringContainsString('timeout', $e->getMessage());
            $this->assertInstanceOf(NetworkException::class, $e->getPrevious());
            $this->assertInstanceOf(RequestInterface::class, $e->getRequest());
        }
    }

    public function test_dto_comes_from_the_request(): void
    {
        $mock = MockClient::make()->on(ListCitiesRequest::class, MockResponse::json(['cities' => [['name' => 'Varna'], ['name' => 'Sofia']]]));

        $dto = (new ApiConnector())->withClient($mock)->send(new ListCitiesRequest('BG'))->dto();

        $this->assertSame(['Varna', 'Sofia'], $dto);
    }

    public function test_default_client_resolver_is_used_when_no_client_is_set(): void
    {
        Connector::useDefaultClient(fn () => $this->mock);

        try {
            (new ApiConnector())->send(new FindSiteRequest());
            $this->assertCount(1, $this->mock->sent());
        } finally {
            Connector::useDefaultClient(null);
        }
    }
}
