<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Support;

use DOMDocument;
use DOMElement;
use Givanov95\TypedHttp\Enums\BodyFormat;
use Givanov95\TypedHttp\Exceptions\TypedHttpException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\StreamInterface;
use Stringable;

final class BodyEncoder
{
    /**
     * @param array<string,mixed> $data
     */
    public static function encode(BodyFormat $format, array $data): EncodedBody
    {
        if ($format === BodyFormat::Multipart) {
            $stream = new MultipartStream(self::multipartElements($data));

            return new EncodedBody($stream, 'multipart/form-data; boundary=' . $stream->getBoundary());
        }

        $content = match ($format) {
            BodyFormat::Json => self::json($data),
            BodyFormat::Form => http_build_query($data),
            BodyFormat::Xml  => self::xml($data),
            BodyFormat::Text => self::text($data),
        };

        return new EncodedBody(Utils::streamFor($content), (string) $format->contentType());
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function json(array $data): string
    {
        if ($data === []) {
            return '{}';
        }

        try {
            return json_encode($data, \JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new TypedHttpException('Cannot encode the request body as JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function xml(array $data): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElement('root');
        $document->appendChild($root);

        self::appendXml($document, $root, $data);

        return (string) $document->saveXML();
    }

    /**
     * @param array<array-key,mixed> $data
     */
    private static function appendXml(DOMDocument $document, DOMElement $parent, array $data): void
    {
        foreach ($data as $key => $value) {
            $element = $document->createElement(is_int($key) ? 'item' : (string) $key);
            $parent->appendChild($element);

            if (is_array($value)) {
                self::appendXml($document, $element, $value);

                continue;
            }

            $element->appendChild($document->createTextNode(self::scalar($value)));
        }
    }

    /**
     * @param array<array-key,mixed> $data
     */
    private static function text(array $data): string
    {
        return implode("\n", array_map(self::scalar(...), array_values($data)));
    }

    /**
     * @param  array<array-key,mixed>                                       $data
     * @return list<array{name: string, contents: mixed, filename?: string, headers?: array<string,string>}>
     */
    private static function multipartElements(array $data, string $prefix = ''): array
    {
        $elements = [];

        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

            if (is_array($value) && ! array_key_exists('contents', $value)) {
                array_push($elements, ...self::multipartElements($value, $name));

                continue;
            }

            if (is_array($value)) {
                // Explicit part definition: contents + optional filename and headers.
                $elements[] = ['name' => $name, ...$value];

                continue;
            }

            $elements[] = [
                'name'     => $name,
                'contents' => ($value instanceof StreamInterface || is_resource($value)) ? $value : self::scalar($value),
            ];
        }

        return $elements;
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            is_bool($value)                                  => $value ? '1' : '0',
            is_scalar($value), $value instanceof Stringable  => (string) $value,
            default                                          => throw new InvalidArgumentException(
                'Cannot use a value of type ' . get_debug_type($value) . ' in this body format.'
            ),
        };
    }
}
