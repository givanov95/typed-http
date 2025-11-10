<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Requests\Enums;

enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
}
