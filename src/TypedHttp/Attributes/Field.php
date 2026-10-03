<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Attributes;

use Attribute;

/**
 * Sends a public property of a request under a different name.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Field
{
    public function __construct(public readonly string $name)
    {
    }
}
