<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Contracts;

use Givanov95\TypedHttp\Request;

/**
 * A client that wants to know which typed request is about to be sent (used by the mock client).
 */
interface RequestAware
{
    public function forRequest(Request $request): void;
}
