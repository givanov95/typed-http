<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Responses\Exceptions;

use Givanov95\TypedHttp\Exceptions\TypedHttpException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class ResponseException extends TypedHttpException
{
    private ?ResponseInterface $response;

    public function __construct(
        string $message = "Response error",
        int $code = 0,
        ?Throwable $previous = null,
        ?ResponseInterface $response = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    /**
     * Returns the HTTP response associated with the exception, if any.
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * Returns the HTTP status code if response exists.
     */
    public function getStatusCode(): ?int
    {
        return $this->response?->getStatusCode();
    }

    /**
     * Returns the response body as string, if available.
     */
    public function getResponseBody(): ?string
    {
        return $this->response ? (string) $this->response->getBody() : null;
    }
}
