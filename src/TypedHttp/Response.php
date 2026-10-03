<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp;

use Givanov95\TypedHttp\Exceptions\RequestException;
use Givanov95\TypedHttp\Exceptions\ResponseException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

final class Response
{
    public function __construct(
        private readonly ResponseInterface $response,
        private readonly ?Request $request = null,
    ) {
    }

    public function psr(): ResponseInterface
    {
        return $this->response;
    }

    public function request(): ?Request
    {
        return $this->request;
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function reason(): string
    {
        return $this->response->getReasonPhrase();
    }

    public function successful(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    public function failed(): bool
    {
        return $this->status() >= 400;
    }

    /**
     * @return array<string,list<string>>
     */
    public function headers(): array
    {
        return $this->response->getHeaders();
    }

    public function header(string $name): ?string
    {
        return $this->response->hasHeader($name) ? $this->response->getHeaderLine($name) : null;
    }

    public function body(): string
    {
        $stream = $this->response->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return (string) $stream;
    }

    /**
     * Decoded JSON body as an array; `$key` reads a nested value with dot notation.
     *
     * @throws ResponseException when the body is empty or not valid JSON
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        $data = $this->decodeJson(true);

        if ($key === null) {
            return $data;
        }

        foreach (explode('.', $key) as $segment) {
            if (! is_array($data) || ! array_key_exists($segment, $data)) {
                return $default;
            }

            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * Decoded JSON body with objects as stdClass.
     *
     * @throws ResponseException when the body is empty or not valid JSON
     */
    public function object(): mixed
    {
        return $this->decodeJson(false);
    }

    /**
     * @throws ResponseException when the body is empty or not valid XML
     */
    public function xml(): SimpleXMLElement
    {
        $content = $this->body();

        if (trim($content) === '') {
            throw new ResponseException('The response body is empty.', $this);
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($content, SimpleXMLElement::class, \LIBXML_NOCDATA | \LIBXML_NONET);
            $error = libxml_get_last_error();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml === false) {
            throw new ResponseException('The response body is not valid XML' . ($error ? ': ' . trim($error->message) : '.'), $this);
        }

        return $xml;
    }

    /**
     * The body parsed according to its Content-Type: JSON → array, XML → SimpleXMLElement, anything else → string.
     * An empty body gives null.
     */
    public function data(): mixed
    {
        if (trim($this->body()) === '') {
            return null;
        }

        $contentType = strtolower((string) $this->header('Content-Type'));

        return match (true) {
            str_contains($contentType, 'json') => $this->json(),
            str_contains($contentType, 'xml')  => $this->xml(),
            default                            => $this->body(),
        };
    }

    /**
     * The typed result: whatever the request's createDto() returns.
     */
    public function dto(): mixed
    {
        return $this->request === null ? $this->data() : $this->request->createDto($this);
    }

    /**
     * @throws RequestException when the status is 4xx or 5xx
     */
    public function throw(): self
    {
        if ($this->failed()) {
            throw new RequestException($this);
        }

        return $this;
    }

    private function decodeJson(bool $associative): mixed
    {
        $content = $this->body();

        if (trim($content) === '') {
            throw new ResponseException('The response body is empty.', $this);
        }

        try {
            return json_decode($content, $associative, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ResponseException('The response body is not valid JSON: ' . $e->getMessage(), $this, $e);
        }
    }
}
