# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`givanov95/typed-http` — a PHP 8.2+ library that wraps Guzzle with typed, abstract `Request` classes. Consumers define a request as a subclass; the library handles parameter collection, content-type encoding, auth headers, and response parsing.

## Commands

```bash
composer install                            # install deps
vendor/bin/php-cs-fixer fix                 # apply code style (PSR-12 + rules in .php-cs.fixer.php)
vendor/bin/php-cs-fixer fix --dry-run --diff  # check style without writing
```

There is no test suite yet (README explicitly notes this).

## Architecture

The library is built around a single inheritance pattern: subclass `Givanov95\TypedHttp\Requests\Request` and implement the abstract methods. The base class wires everything together.

### Request lifecycle (`src/TypedHttp/Requests/Request.php`)

1. **Parameter collection** — `getRequestParams()` uses reflection to read **public properties declared on the child class only** (inherited properties are skipped via `getDeclaringClass()->getName() === get_class($this)`). `null` values are filtered out. This is how subclasses expose their request payload — there is no setter/builder API; properties *are* the payload.
2. **Body encoding** — `ContentType::encodeBody()` dispatches by enum case (JSON → `json_encode`, FORM_URLENCODED → `http_build_query`, XML → `SimpleXMLElement`, CSV → `fputcsv`, etc.). For `GET`, the encoded params are appended as a query string; otherwise they go in the body and `Content-Type` is added.
3. **Auth headers** — `getAuthenticator()` may return `null` (returns no headers) or an `AuthenticatorInterface` implementation whose `getAuthHeaders(): array` is merged into the request. `CertificateAuthenticator` additionally exposes `getClientOptions()` for mTLS settings, but **note: `Request::executeRequest()` does not currently pass these to the Guzzle client** — only header-based auth is wired through.
4. **Execution** — Guzzle `sendAsync()->wait()`. Any non-2xx/3xx status (`>= 400`) throws `RequestException`; all Guzzle exceptions are caught and re-wrapped as `RequestException`.
5. **Response parsing** — `ResponseParser::parse()` matches on the `ExpectedResponseFormat` enum value (JSON, XML, plain text). `getParsedBody()` lazily calls `executeRequest()` if it hasn't run yet.

### Authenticators (`src/TypedHttp/Requests/Authorization/`)

Each auth method has a paired interface + authenticator class (e.g., `BasicAuthInterface` + `BasicAuthAuthenticator`). The interface is a marker for the request class to implement; the authenticator is what `getAuthenticator()` returns. Existing pairs: BasicAuth, BearerToken, Certificate. To add a new auth method, create both files under a new `Authorization/<Name>/` directory and implement `AuthenticatorInterface::getAuthHeaders()`.

### Conventions

- All files are `declare(strict_types=1)`.
- Code style is PSR-12 with additional rules in `.php-cs.fixer.php` (aligned `=>`, single quotes, trailing commas in multiline arrays, ordered imports). Run the fixer before committing.
- Namespace root is `Givanov95\TypedHttp\` mapped to `src/TypedHttp/`.
- Exceptions live in per-layer `Exceptions/` directories (`Requests/Exceptions/RequestException`, `Responses/Exceptions/ResponseException`, top-level `Exceptions/TypedHttpException`).
