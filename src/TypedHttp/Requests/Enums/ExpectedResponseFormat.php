<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Requests\Enums;

enum ExpectedResponseFormat: string
{
    case JSON = 'application/json';
    case XML = 'application/xml';
    case TEXT = 'text/plain';
    case HTML = 'text/html';
    case CSV = 'text/csv';
}
