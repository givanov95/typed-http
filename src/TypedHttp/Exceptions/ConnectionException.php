<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Exceptions;

use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * The request never produced a response (DNS failure, refused connection, timeout...).
 */
class ConnectionException extends TypedHttpException
{
    public function __construct(
        string $message = 'Connection failed',
        ?Throwable $previous = null,
        private readonly ?RequestInterface $request = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getRequest(): ?RequestInterface
    {
        return $this->request;
    }
}
