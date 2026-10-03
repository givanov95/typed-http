<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Testing;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

final class MockResponse
{
    /**
     * @param array<string,string> $headers
     */
    public static function make(string $body = '', int $status = 200, array $headers = []): ResponseInterface
    {
        return new Response($status, $headers, $body);
    }

    /**
     * @param array<string,string> $headers
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json', ...$headers], json_encode($data, \JSON_THROW_ON_ERROR));
    }

    public static function empty(int $status = 204): ResponseInterface
    {
        return new Response($status);
    }
}
