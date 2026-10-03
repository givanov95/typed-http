<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Fixtures;

final class FindSiteRequest extends CredentialsRequest
{
    public function __construct(
        public ?string $name = null,
        public ?string $countryId = '100',
    ) {
    }

    public function endpoint(): string
    {
        return '/location/site/';
    }
}
