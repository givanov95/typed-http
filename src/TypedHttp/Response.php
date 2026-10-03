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
    private ?string $content = null;

    /** @var array<int,mixed> decoded JSON by the `$associative` flag, so the body is parsed once */
    private array $decoded = [];

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

    /**
     * The body as a string. The stream is read once and the result is kept, so this also works for streams that cannot be rewound.
     */
    public function body(): string
    {
        if ($this->content === null) {
            $stream = $this->response->getBody();

            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            $this->content = (string) $stream;
        }

        return $this->content;
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
     * When the server names no JSON/XML type (some APIs send JSON as text/plain), the format the request accepts
     * is tried instead and the raw string is returned if the body does not parse. An empty body gives null.
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
            default                            => $this->guessFromAccepts(),
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

    private function guessFromAccepts(): mixed
    {
        $accepts = strtolower((string) $this->request?->accepts());

        try {
            if (str_contains($accepts, 'json')) {
                $json = $this->json();

                // A bare number or word is more likely plain text than a JSON document.
                return is_array($json) ? $json : $this->body();
            }

            if (str_contains($accepts, 'xml')) {
                return $this->xml();
            }
        } catch (ResponseException) {
            // not in the format the request hoped for
        }

        return $this->body();
    }

    private function decodeJson(bool $associative): mixed
    {
        $key = (int) $associative;

        if (array_key_exists($key, $this->decoded)) {
            return $this->decoded[$key];
        }

        $content = $this->body();

        if (trim($content) === '') {
            throw new ResponseException('The response body is empty.', $this);
        }

        try {
            return $this->decoded[$key] = json_decode($content, $associative, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ResponseException('The response body is not valid JSON: ' . $e->getMessage(), $this, $e);
        }
    }
}
