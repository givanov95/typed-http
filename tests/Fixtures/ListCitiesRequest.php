<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Fixtures;

use Givanov95\TypedHttp\Enums\HttpMethod;
use Givanov95\TypedHttp\Request;
use Givanov95\TypedHttp\Response;

final class ListCitiesRequest extends Request
{
    public function __construct(
        public string $countryCode,
        public ?int $page = null,
        public ?string $search = null,
    ) {
    }

    public function method(): HttpMethod
    {
        return HttpMethod::GET;
    }

    public function endpoint(): string
    {
        return 'cities';
    }

    /**
     * @return list<string>
     */
    public function createDto(Response $response): array
    {
        return array_map(fn (array $city) => $city['name'], $response->json('cities'));
    }
}
