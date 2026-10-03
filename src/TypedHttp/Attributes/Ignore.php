<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Attributes;

use Attribute;

/**
 * Keeps a public property of a request out of the payload.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Ignore
{
}
