<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp;

use Givanov95\TypedHttp\Contracts\Authenticator;
use Givanov95\TypedHttp\Enums\BodyFormat;
use Givanov95\TypedHttp\Enums\HttpMethod;
use Givanov95\TypedHttp\Support\Payload;

/**
 * One request = one class. The public properties of the subclass (and of its parents) are the payload.
 */
abstract class Request
{
    abstract public function method(): HttpMethod;

    /**
     * Path relative to the connector's base URL, or an absolute URL.
     */
    abstract public function endpoint(): string;

    /**
     * Query string parameters.
     *
     * @return array<string,mixed>
     */
    public function query(): array
    {
        return [];
    }

    /**
     * @return array<string,string>
     */
    public function headers(): array
    {
        return [];
    }

    public function bodyFormat(): BodyFormat
    {
        return BodyFormat::Json;
    }

    /**
     * Value of the Accept header (null = none). Follows the body format: XML in, XML expected; otherwise JSON.
     */
    public function accepts(): ?string
    {
        return $this->bodyFormat() === BodyFormat::Xml ? 'application/xml' : 'application/json';
    }

    /**
     * Overrides the connector's authenticator for this request.
     */
    public function authenticator(): ?Authenticator
    {
        return null;
    }

    /**
     * Send this request without any authentication, even if the connector has one.
     */
    public function skipsAuthentication(): bool
    {
        return false;
    }

    /**
     * Payload: query string for GET/HEAD/OPTIONS, body otherwise. By default collected from public properties.
     *
     * @return array<string,mixed>
     */
    public function body(): array
    {
        return Payload::collect($this);
    }

    /**
     * Turns the response into the typed result. Override to return a DTO.
     */
    public function createDto(Response $response): mixed
    {
        return $response->data();
    }
}
