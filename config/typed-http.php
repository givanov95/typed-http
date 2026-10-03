<?php

declare(strict_types=1);

return [
    /*
    | Retry for connectors that use the Laravel client (the default inside Laravel).
    | `times` = 0 disables it. Only idempotent requests (GET, PUT, DELETE...) are repeated
    | unless `unsafe` is true, because repeating a POST can create a duplicate.
    */
    'retry' => [
        'times'    => 0,
        'delay'    => 200, // milliseconds, doubled after every attempt
        'statuses' => [429, 502, 503, 504],
        'unsafe'   => false,
    ],
];
