<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Support;

use DOMDocument;
use DOMElement;
use DOMException;
use Givanov95\TypedHttp\Enums\BodyFormat;
use Givanov95\TypedHttp\Exceptions\TypedHttpException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Utils;
use JsonException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use SplFileInfo;
use Stringable;

final class BodyEncoder
{
    private const PART_KEYS = ['contents', 'filename', 'headers'];

    /**
     * @param  array<string,mixed>     $data
     * @throws TypedHttpException when the data cannot be written in the format
     */
    public static function encode(BodyFormat $format, array $data): EncodedBody
    {
        if ($format === BodyFormat::Multipart) {
            $stream = new MultipartStream(self::multipartElements($data));

            return new EncodedBody($stream, 'multipart/form-data; boundary=' . $stream->getBoundary());
        }

        self::rejectFiles($data);

        $content = match ($format) {
            BodyFormat::Json => self::json($data),
            BodyFormat::Form => http_build_query($data),
            BodyFormat::Xml  => self::xml($data),
            BodyFormat::Text => self::text($data),
        };

        return new EncodedBody(Utils::streamFor($content), (string) $format->contentType());
    }

    /**
     * A file or stream outside multipart would silently turn into its path, `{}` or nothing.
     *
     * @param array<array-key,mixed> $data
     */
    private static function rejectFiles(array $data): void
    {
        foreach ($data as $value) {
            if (is_array($value)) {
                self::rejectFiles($value);
            } elseif ($value instanceof SplFileInfo || $value instanceof StreamInterface || is_resource($value)) {
                throw new TypedHttpException('Files and streams can only be sent with BodyFormat::Multipart.');
            }
        }
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

        try {
            self::appendXml($document, $root, $data);
        } catch (DOMException $e) {
            throw new TypedHttpException('Cannot encode the request body as XML: ' . $e->getMessage(), 0, $e);
        }

        return (string) $document->saveXML();
    }

    /**
     * @param array<array-key,mixed> $data
     */
    private static function appendXml(DOMDocument $document, DOMElement $parent, array $data): void
    {
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

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
        return implode("\n", array_map(self::scalar(...), array_values(array_filter($data, static fn (mixed $value) => $value !== null))));
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

            if ($value === null) {
                continue;
            }

            if (is_array($value) && ! self::isPartDefinition($value)) {
                array_push($elements, ...self::multipartElements($value, $name));

                continue;
            }

            if (is_array($value)) {
                // Explicit part definition: contents + optional filename and headers.
                $part = ['name' => $name, ...$value];

                if ($part['contents'] instanceof SplFileInfo) {
                    $file = $part['contents'];
                    $part['contents'] = self::open($file);
                    $part['filename'] ??= self::fileName($file);
                }

                $elements[] = $part;

                continue;
            }

            if ($value instanceof SplFileInfo) {
                $elements[] = ['name' => $name, 'contents' => self::open($value), 'filename' => self::fileName($value)];

                continue;
            }

            $elements[] = [
                'name'     => $name,
                'contents' => ($value instanceof StreamInterface || is_resource($value)) ? $value : self::scalar($value),
            ];
        }

        return $elements;
    }

    /**
     * Only an array made of nothing but part keys is a part; a field that happens to be called "contents"
     * next to other data is just a field.
     *
     * @param array<array-key,mixed> $value
     */
    private static function isPartDefinition(array $value): bool
    {
        return array_key_exists('contents', $value) && array_diff(array_keys($value), self::PART_KEYS) === [];
    }

    /**
     * @return resource
     */
    private static function open(SplFileInfo $file)
    {
        if (! $file->isFile() || ! $file->isReadable()) {
            throw new TypedHttpException('Cannot read the file "' . $file->getPathname() . '" for the multipart body.');
        }

        try {
            return Utils::tryFopen($file->getPathname(), 'r');
        } catch (RuntimeException $e) {
            throw new TypedHttpException('Cannot open the file "' . $file->getPathname() . '" for the multipart body: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * The name the uploader gave the file when there is one (Laravel's and Symfony's UploadedFile), else the name on disk.
     */
    private static function fileName(SplFileInfo $file): string
    {
        if (method_exists($file, 'getClientOriginalName')) {
            $name = $file->getClientOriginalName();

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return $file->getFilename();
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            is_bool($value)                                  => $value ? '1' : '0',
            is_scalar($value), $value instanceof Stringable  => (string) $value,
            default                                          => throw new TypedHttpException(
                'Cannot use a value of type ' . get_debug_type($value) . ' in this body format.'
            ),
        };
    }
}
