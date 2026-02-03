<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Responses;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class ResponseParser
{
    /**
     * Parse the HTTP response body based on the given content type.
     *
     * @param  StreamInterface  $body
     * @param  string           $expectedContentType
     * @param  bool             $associative
     * @return mixed
     * @throws RuntimeException
     */
    public static function parse(StreamInterface $body, string $expectedContentType, bool $associative = false): mixed
    {
        $content = (string) $body;

        return match ($expectedContentType) {
            'application/json' => json_decode($content, $associative, flags: \JSON_THROW_ON_ERROR),
            'application/xml' => self::parseXml($content, $associative),
            'text/plain' => $content,
            default => throw new RuntimeException("Unsupported response format: {$expectedContentType}")
        };
    }

    /**
     * Parse XML into array or SimpleXMLElement.
     *
     * @param  string $content
     * @param  bool   $associative
     * @return mixed
     */
    private static function parseXml(string $content, bool $associative): mixed
    {
        $xml = simplexml_load_string($content, 'SimpleXMLElement', \LIBXML_NOCDATA | \LIBXML_NONET);

        if ($xml === false) {
            throw new RuntimeException('Invalid XML response.');
        }

        return $associative
            ? json_decode(json_encode($xml), true, flags: \JSON_THROW_ON_ERROR)
            : $xml;
    }
}
