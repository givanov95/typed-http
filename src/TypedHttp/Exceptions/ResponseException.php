<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Exceptions;

use Givanov95\TypedHttp\Response;
use Throwable;

/**
 * The response arrived but its body cannot be read in the requested format (empty or invalid JSON/XML).
 */
class ResponseException extends TypedHttpException
{
    public function __construct(string $message, private readonly ?Response $response = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $response?->status() ?? 0, $previous);
    }

    public function response(): ?Response
    {
        return $this->response;
    }
}
