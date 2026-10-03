<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Exceptions;

use RuntimeException;
use Throwable;

class TypedHttpException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
