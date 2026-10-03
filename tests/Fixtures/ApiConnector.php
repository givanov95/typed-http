<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Fixtures;

use Givanov95\TypedHttp\Connector;
use Givanov95\TypedHttp\Contracts\Authenticator;

class ApiConnector extends Connector
{
    public function __construct(private readonly ?Authenticator $auth = null)
    {
    }

    public function baseUrl(): string
    {
        return 'https://api.test/v1/';
    }

    public function authenticator(): ?Authenticator
    {
        return $this->auth;
    }
}
