<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Requests\Enums;

use SimpleXMLElement;

enum ContentType: string
{
    case JSON = 'application/json';
    case FORM = 'multipart/form-data';
    case FORM_URLENCODED = 'application/x-www-form-urlencoded';
    case XML = 'application/xml';
    case HTML = 'text/html';
    case TEXT = 'text/plain';
    case CSV = 'text/csv';

    public function encodeBody(array $body): string|array
    {
        return match ($this) {
            self::JSON => json_encode($body),
            self::FORM => $body,
            self::FORM_URLENCODED => http_build_query($body),
            self::XML => $this->arrayToXml($body),
            self::HTML, self::TEXT => implode("\n", $body),
            self::CSV => $this->arrayToCsv($body),
        };
    }

    private function arrayToXml(array $data): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><root/>');
        array_walk_recursive($data, function ($value, $key) use ($xml) {
            $xml->addChild($key, $value);
        });

        return $xml->asXML();
    }

    private function arrayToCsv(array $data): string
    {
        $output = fopen('php://temp', 'r+');
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        rewind($output);

        return stream_get_contents($output);
    }
}
