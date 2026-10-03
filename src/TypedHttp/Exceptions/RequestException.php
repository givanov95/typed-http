<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Exceptions;

use Givanov95\TypedHttp\Response;

/**
 * The server answered with an error status (4xx/5xx). The full response stays available.
 */
class RequestException extends TypedHttpException
{
    public function __construct(private readonly Response $response, ?string $message = null)
    {
        parent::__construct(
            $message ?? 'HTTP request returned status code ' . $response->status() . ($response->reason() !== '' ? ' ' . $response->reason() : ''),
            $response->status(),
        );
    }

    public function response(): Response
    {
        return $this->response;
    }

    public function status(): int
    {
        return $this->response->status();
    }

    public function body(): string
    {
        return $this->response->body();
    }
}
