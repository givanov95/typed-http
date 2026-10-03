<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Fixtures;

use Givanov95\TypedHttp\Enums\HttpMethod;
use Givanov95\TypedHttp\Request;

/**
 * Mirrors laravel-shop's BaseSpeedyRequest: credentials live in an intermediate abstract class.
 */
abstract class CredentialsRequest extends Request
{
    public string $userName = 'user';

    public string $password = 'secret';

    public string $language = 'EN';

    public function method(): HttpMethod
    {
        return HttpMethod::POST;
    }
}
