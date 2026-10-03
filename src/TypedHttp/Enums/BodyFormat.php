<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Enums;

enum BodyFormat
{
    case Json;
    case Form;
    case Multipart;
    case Xml;
    case Text;

    /**
     * Content-Type header value. Multipart is built with a boundary, so it has none here.
     */
    public function contentType(): ?string
    {
        return match ($this) {
            self::Json      => 'application/json',
            self::Form      => 'application/x-www-form-urlencoded',
            self::Multipart => null,
            self::Xml       => 'application/xml',
            self::Text      => 'text/plain',
        };
    }
}
