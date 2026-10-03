# TypedHttp

Typed, object-oriented HTTP requests for PHP 8.2+. One API is a **Connector**, one call is a **Request** class, and the result can be a typed DTO. The core is plain PHP on top of PSR-18; an optional Laravel bridge sends everything through `Http::`, so `Http::fake()` and `Http::preventStrayRequests()` keep working.

```bash
composer require givanov95/typed-http
```

## Quick start

```php
use Givanov95\TypedHttp\Auth\BasicAuth;
use Givanov95\TypedHttp\Connector;
use Givanov95\TypedHttp\Contracts\Authenticator;
use Givanov95\TypedHttp\Enums\HttpMethod;
use Givanov95\TypedHttp\Request;
use Givanov95\TypedHttp\Response;

final class EcontConnector extends Connector
{
    public function __construct(private string $user, private string $pass) {}

    public function baseUrl(): string { return 'https://ee.econt.com/services'; }

    public function authenticator(): ?Authenticator { return new BasicAuth($this->user, $this->pass); }
}

final class GetCitiesRequest extends Request
{
    public function __construct(public string $countryCode) {}   // public properties = the payload

    public function method(): HttpMethod { return HttpMethod::POST; }

    public function endpoint(): string { return '/Nomenclatures/NomenclaturesService.getCities.json'; }

    /** @return list<string> */
    public function createDto(Response $response): array        // optional: a typed result
    {
        return array_column($response->json('cities'), 'name');
    }
}

$cities = (new EcontConnector($user, $pass))->send(new GetCitiesRequest('BGR'))->dto();
```

## The payload

The public properties of the request class **and of its parent classes** are the payload. `null`, uninitialized and static properties are skipped. Enums become their value, `DateTimeInterface` becomes an ATOM string, `Stringable` objects become strings. This applies at every depth, also inside arrays and nested objects (public properties).

```php
use Givanov95\TypedHttp\Attributes\Field;
use Givanov95\TypedHttp\Attributes\Ignore;

final class FindSiteRequest extends Request
{
    #[Field('site_id')] public int $siteId;   // sent as "site_id"
    #[Ignore] public string $cacheKey;        // never sent
    public ?string $name = null;              // skipped while null
}
```

- `GET`, `HEAD` and `OPTIONS` send the payload as a query string, every other method sends it as a body. Extra query parameters: `Request::query()`.
- Override `Request::body()` when you need other names, nested data or an explicit `null`.
- `Connector::defaultBody()` / `defaultQuery()` add fields to every request (for APIs that expect credentials in the payload).
- Body format: `Request::bodyFormat()` returns `BodyFormat::Json` (default), `Form`, `Multipart`, `Xml` or `Text`. Outside multipart, nested `null`s are skipped (JSON keeps them) and a file or stream throws a `TypedHttpException` instead of being sent as its path.
- Multipart files: pass an `SplFileInfo` (Laravel's `UploadedFile` works, its original name is used) or `['contents' => $stream, 'filename' => 'a.pdf']`. An array is a part definition only when it holds nothing but `contents`, `filename` and `headers`; a field called `contents` next to other fields is a normal field.
- `Request::accepts()` is the Accept header: `application/json`, or `application/xml` for `BodyFormat::Xml`.
- `endpoint()` is relative to `Connector::baseUrl()`, or an absolute URL.

## Responses

`send()` returns a `Response`: `status()`, `header()`, `body()`, `json($key = null, $default = null)` (dot notation), `object()` (stdClass), `xml()`, `data()` (by Content-Type), and `dto()` (whatever `createDto()` returns). Empty or invalid JSON/XML throws `ResponseException`. The body is read once and kept, so streamed responses work too. `data()` goes by the response's Content-Type; when it is neither JSON nor XML (some APIs send JSON as `text/plain`) it tries the format `accepts()` names and returns the raw string if the body does not parse.

## Errors

| Exception | When |
| --- | --- |
| `RequestException` | status 4xx/5xx. `->response()`, `->status()`, `->body()` keep what the server said |
| `ConnectionException` | no response at all (DNS, refused, timeout, connection reset). `->getPrevious()` has the cause |
| `ResponseException` | the body cannot be read as JSON/XML |

All extend `TypedHttpException`, including the errors raised while building the request (unencodable body, invalid XML element name, relative endpoint without a base URL). To handle error bodies yourself, override `Connector::shouldThrow(Response $response): bool`.

## Authentication

`BasicAuth`, `BearerToken` and `Certificate` (mutual TLS) implement `Authenticator`. Set one per connector, override it per request with `Request::authenticator()`, or skip it with `Request::skipsAuthentication()`. Implement `Authenticator` for anything else (`apply()` receives the PSR request, so signing is possible).

## Timeouts, client and retry

Timeouts default to 30 s (`timeout()`) and 10 s (`connectTimeout()`); override them on the connector. Return `null` to pass no timeout and leave it to the client (a Guzzle `Client` config or `Http::globalOptions()`). Options given to the transport itself (`new GuzzleTransport($client, ['timeout' => 120])`, `new LaravelTransport($http, [...])`) win over what the connector passes.

The HTTP client is any PSR-18 `ClientInterface`: `$connector->withClient($client)`. Without one, the connector uses `GuzzleTransport`. Clients implementing `Contracts\Transport` also receive the options (timeouts, client certificate); a plain PSR-18 client has to be configured by you.

Retry is opt-in: wrap the client in `RetryClient`. It repeats network errors and 429/502/503/504 with exponential backoff and honours `Retry-After`; one pause is at most 30 s. **Only idempotent methods are repeated** unless `retryUnsafe: true`, because repeating a POST can create a duplicate. A request whose body cannot be rewound (a non-seekable stream) is never repeated.

```php
$connector = (new EcontConnector($user, $pass))->withClient(new RetryClient(new GuzzleTransport(), times: 2));
```

## Testing

`MockClient` answers with prepared responses and never touches the network:

```php
use Givanov95\TypedHttp\Testing\{MockClient, MockResponse};

$mock = MockClient::make()->on(GetCitiesRequest::class, MockResponse::json(['cities' => [['name' => 'Varna']]]));

$cities = $connector->withClient($mock)->send(new GetCitiesRequest('BGR'))->dto();

$mock->assertSent(GetCitiesRequest::class, fn ($psr, $typed) => $typed->countryCode === 'BGR');
```

A matcher is a request class or a `Closure(RequestInterface, ?Request)`; a response is a PSR response, a `Throwable` (thrown) or a closure; several responses are returned in order and the last one repeats. An unmatched request throws `LogicException`.

## Laravel

With `illuminate/http` installed the service provider is auto-discovered and connectors send through `Http::` by default:

```php
Http::fake(['ee.econt.com/*' => Http::response(['cities' => []])]);
Http::preventStrayRequests();
```

Optional retry config (`php artisan vendor:publish --tag=typed-http-config`, key `typed-http.retry`, `times = 0` means off). Keep credentials in `config/services.php`, not `env()` inside request classes: `env()` returns `null` once the config is cached.

Upgrading from 1.x: see [UPGRADE.md](UPGRADE.md).
