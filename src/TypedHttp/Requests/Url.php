<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Requests;

use InvalidArgumentException;

class Url
{
    private $url;

    public function __construct(string $url)
    {
        $this->ensureUrlIsValid($url);

        $this->url = $url;
    }

    public function toString(): string
    {
        return $this->url;
    }

    /**
     * @param  string              $url
     * @throws InvalidUrlException
     */
    private function ensureUrlIsValid(string $url): void
    {
        if (filter_var($url, \FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Invalid url');
        }
    }
}
