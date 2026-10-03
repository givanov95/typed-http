# Upgrading from 1.x to 2.0

2.0 is a rewrite of the public API. The idea (one class per request, public properties as payload) is the same.

## Summary

| 1.x | 2.0 |
| --- | --- |
| `class X extends Request` with `getUrl()`, `getAuthenticator()`, `getHttpMethodType()`, `getContentType()`, `getExpectedResponseFormat()` | `Request` with `method()`, `endpoint()`, `bodyFormat()`, plus a `Connector` for base URL and auth |
| `new Url(env('X').'/path')` | `endpoint()` returns `'/path'`; the base URL lives in `Connector::baseUrl()` (absolute endpoints still work) |
| `$request->executeRequest()`, `$request->getParsedBody()` | `$connector->send($request)->json()` / `->object()` / `->dto()` |
| `getParsedBody()` returned `stdClass` by default | `json()` returns arrays; `object()` returns `stdClass` |
| `BasicAuthInterface` + `BasicAuthAuthenticator` | `Auth\BasicAuth` (same for `BearerToken`, `Certificate`) |
| `getHeaders()`, `getClientOptions()` | `headers()`; options belong to the connector (`options()`, `timeout()`) |
| `ContentType`, `ExpectedResponseFormat` enums | `BodyFormat` (`Json`, `Form`, `Multipart`, `Xml`, `Text`); `accepts()` sets the Accept header |
| `Options`, `Url`, `ResponseParser`, `ResponseException` | removed (`ResponseException` is now thrown for unreadable bodies) |
| `HttpMethod` had GET and POST | all methods |

## Behaviour changes

- **Inherited public properties are now sent.** In 1.x only the properties of the most derived class were collected, so credentials declared in an abstract base class were silently dropped. Check requests with a base class: their payload now contains the parent's properties.
- **Error responses keep their data.** `RequestException` has `response()`, `status()` and `body()`. Network failures are a separate `ConnectionException`.
- **GET sends the payload as a query string** (1.x encoded it with the body format, so a JSON GET put JSON into the URL). `GET`/`HEAD`/`OPTIONS` have no body.
- An empty JSON payload is sent as `{}` (was `[]`).
- Multipart works. XML is nested and escaped.
- Default timeouts (30 s / 10 s). 1.x waited forever.
- `Certificate` only sets `verify` when a CA bundle is given.

## Typical migration

```php
// 1.x
class GetCitiesRequest extends Request implements BasicAuthInterface
{
    public string $countryCode;
    public function __construct(string $countryCode) { $this->countryCode = $countryCode; parent::__construct(); }
    public function getAuthenticator(): BasicAuthAuthenticator { return new BasicAuthAuthenticator(env('U'), env('P')); }
    public function getUrl(): Url { return new Url(env('BASE').'/cities.json'); }
    protected function getHttpMethodType(): HttpMethod { return HttpMethod::POST; }
    protected function getContentType(): ContentType { return ContentType::JSON; }
    protected function getExpectedResponseFormat(): ExpectedResponseFormat { return ExpectedResponseFormat::JSON; }
}
$cities = (new GetCitiesRequest('BGR'))->getParsedBody();

// 2.0
final class EcontConnector extends Connector
{
    public function baseUrl(): string { return config('services.econt.url'); }
    public function authenticator(): ?Authenticator { return new BasicAuth(config('services.econt.user'), config('services.econt.pass')); }
}
final class GetCitiesRequest extends Request
{
    public function __construct(public string $countryCode) {}
    public function method(): HttpMethod { return HttpMethod::POST; }
    public function endpoint(): string { return '/cities.json'; }
}
$cities = (new EcontConnector())->send(new GetCitiesRequest('BGR'))->object();
```

APIs that expect credentials inside every payload (Speedy): return them from `Connector::defaultBody()` instead of declaring them on a base request class.

## Laravel

The bridge is automatic when `illuminate/http` is installed. Connectors then use `Http::`, so existing `Http::fake()` tests apply. Guzzle is still required (default client outside Laravel).
