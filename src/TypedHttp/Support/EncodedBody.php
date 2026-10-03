<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Support;

use Psr\Http\Message\StreamInterface;

final readonly class EncodedBody
{
    public function __construct(
        public StreamInterface $stream,
        public string $contentType,
    ) {
    }
}
