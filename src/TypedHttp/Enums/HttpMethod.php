<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Enums;

enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case HEAD = 'HEAD';
    case OPTIONS = 'OPTIONS';

    /**
     * Whether the request payload travels in the body (otherwise it goes into the query string).
     */
    public function hasBody(): bool
    {
        return ! in_array($this, [self::GET, self::HEAD, self::OPTIONS], true);
    }

    /**
     * Safe to repeat without side effects (used by the retry client).
     */
    public function isIdempotent(): bool
    {
        return $this !== self::POST && $this !== self::PATCH;
    }
}
