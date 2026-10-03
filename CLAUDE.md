# CLAUDE.md

## Работен флоу (gws@claude-flow)

Работният флоу (issue-та, PR-и) идва от плъгина `gws@claude-flow` — `/gws:issue <N>`. Комуникация с потребителя: български. Код, commit-и и PR-и: английски.

### Branch-ове
- Базов branch: `main`. Issue branch-ове: `fix|feat|chore/N-kratko-ime` от него, PR към него, squash merge.
- Issue-то се затваря с `Fixes #N` в тялото на commit-а (базовият branch е default — затваря се при merge на PR-а).

### Deploy
- Няма — проектът не се качва на сървър. `/gws:ship` не е приложим тук; доставката е merge в базовия branch.

### Build и commit-и
- Няма билд стъпка. Тестове: `vendor/bin/phpunit`. Pre-commit hook от `givanov95/laravel-git-hooks` пуска php-cs-fixer и тестовете при commit.
- Commit стил: Conventional Commits на английски (`fix(scope): ...`).

### GitHub
- Нови issue-та се добавят в project board „gws".

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`givanov95/typed-http` — v2: typed, object-oriented HTTP requests for PHP 8.2+. A `Connector` is one API (base URL, auth, timeouts, client), a `Request` subclass is one call (its public properties are the payload), `Response` reads the result. The core is plain PHP on PSR-18 (Guzzle is the default client); an optional Laravel bridge sends requests through `Http::`. Public docs: `README.md`, migration from 1.x: `UPGRADE.md`.

## Commands

```bash
composer install
vendor/bin/phpunit                              # core tests + Laravel bridge tests (orchestra/testbench)
vendor/bin/php-cs-fixer fix                     # code style (rules in .php-cs-fixer.php), covers src/ and tests/
vendor/bin/php-cs-fixer fix --dry-run --diff
```

## Architecture (`src/TypedHttp/`)

- `Connector::send(Request)` builds the PSR-7 request (URL from `baseUrl()` + `endpoint()`, payload → query for GET/HEAD/OPTIONS or body otherwise, headers, `Accept`), applies the `Authenticator`, then sends it through the client. Status >= 400 → `RequestException` (see `shouldThrow()`), `ClientExceptionInterface` → `ConnectionException`.
- `Request` — abstract: `method()`, `endpoint()`; optional `query()`, `headers()`, `bodyFormat()`, `accepts()`, `authenticator()`, `body()`, `createDto()`. `Support/Payload` collects public properties **including inherited ones** (skips null/uninitialized/static/`#[Ignore]`, honours `#[Field]`). `Support/BodyEncoder` encodes the body (`Enums/BodyFormat`).
- Client: any PSR-18 `ClientInterface`. `Contracts/Transport` adds `withOptions()` (timeouts, `cert`, `ssl_key`, `verify` — Guzzle option names); the connector passes options only to `Transport`s; options given to a transport's constructor win over the connector's (`timeout()`/`connectTimeout()` may be `null` = pass nothing). `Contracts/RequestAware` lets `Testing/MockClient` match by request class. Transports: `Transport/GuzzleTransport` (default, `http_errors` off), `Transport/RetryClient` (decorator; repeats only idempotent methods unless `retryUnsafe`, and never a body that cannot be rewound), `Laravel/LaravelTransport`.
- `Auth/`: `BasicAuth`, `BearerToken`, `Certificate` implement `Contracts/Authenticator` (`apply(RequestInterface)` + `options()`).
- `Testing/MockClient` + `MockResponse`: prepared responses, recorded requests, `assert*` methods; no network.
- `Laravel/TypedHttpServiceProvider` (auto-discovered via `composer.json` `extra.laravel`) binds `Transport` to `LaravelTransport` (wrapped in `RetryClient` when `typed-http.retry.times` > 0) and registers it with `Connector::useDefaultClient()`. `illuminate/*` is a dev dependency only; the core must not import `Illuminate\`.
- Exceptions: `TypedHttpException` base; `RequestException` (has `Response`), `ConnectionException`, `ResponseException`, `NetworkException` (PSR-18 network error raised by transports).

## Conventions

- All files `declare(strict_types=1)`; namespace root `Givanov95\TypedHttp\` → `src/TypedHttp/`, tests `Givanov95\TypedHttp\Tests\` → `tests/`.
- Keep the core free of Laravel; Laravel-only code lives in `src/TypedHttp/Laravel/` and is tested with testbench (`tests/Laravel/`).
- Avoid PHP 8.3+ syntax: CI runs 8.2, 8.3 and 8.4.
- New behaviour comes with a test; the `MockClient` is the way to test connectors without network.
