<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Support;

use Givanov95\TypedHttp\Enums\BodyFormat;
use Givanov95\TypedHttp\Exceptions\TypedHttpException;
use Givanov95\TypedHttp\Support\BodyEncoder;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BodyEncoderTest extends TestCase
{
    public function test_json_body(): void
    {
        $encoded = BodyEncoder::encode(BodyFormat::Json, ['a' => 1, 'list' => [1, 2], 'n' => null]);

        $this->assertSame('application/json', $encoded->contentType);
        $this->assertSame('{"a":1,"list":[1,2],"n":null}', (string) $encoded->stream);
    }

    public function test_empty_json_body_is_an_object(): void
    {
        $this->assertSame('{}', (string) BodyEncoder::encode(BodyFormat::Json, [])->stream);
    }

    public function test_unencodable_json_throws_library_exception(): void
    {
        $this->expectException(TypedHttpException::class);

        BodyEncoder::encode(BodyFormat::Json, ['bad' => "\xB1\x31"]);
    }

    public function test_form_body(): void
    {
        $encoded = BodyEncoder::encode(BodyFormat::Form, ['a' => 'x y', 'b' => ['c' => 1]]);

        $this->assertSame('application/x-www-form-urlencoded', $encoded->contentType);
        $this->assertSame('a=x+y&b%5Bc%5D=1', (string) $encoded->stream);
    }

    public function test_multipart_body_with_nested_fields_files_and_booleans(): void
    {
        $encoded = BodyEncoder::encode(BodyFormat::Multipart, [
            'title' => 'Hi',
            'flag'  => true,
            'meta'  => ['k' => 'v'],
            'file'  => ['contents' => Utils::streamFor('DATA'), 'filename' => 'a.txt'],
        ]);
        $body = (string) $encoded->stream;

        $this->assertStringStartsWith('multipart/form-data; boundary=', $encoded->contentType);
        $this->assertStringContainsString('name="title"', $body);
        $this->assertStringContainsString("\r\n\r\nHi\r\n", $body);
        $this->assertStringContainsString("name=\"flag\"\r\n", $body);
        $this->assertStringContainsString('name="meta[k]"', $body);
        $this->assertStringContainsString('name="file"; filename="a.txt"', $body);
        $this->assertStringContainsString('DATA', $body);
    }

    public function test_xml_body_is_nested_and_escaped(): void
    {
        $encoded = BodyEncoder::encode(BodyFormat::Xml, ['name' => 'A & B <c>', 'items' => ['x', 'y']]);
        $xml = simplexml_load_string((string) $encoded->stream);

        $this->assertSame('application/xml', $encoded->contentType);
        $this->assertSame('A & B <c>', (string) $xml->name);
        $this->assertSame(['x', 'y'], [(string) $xml->items->item[0], (string) $xml->items->item[1]]);
    }

    public function test_text_body_joins_values(): void
    {
        $this->assertSame("a\n1", (string) BodyEncoder::encode(BodyFormat::Text, ['x' => 'a', 'y' => 1])->stream);
    }

    public function test_text_body_rejects_arrays(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BodyEncoder::encode(BodyFormat::Text, ['x' => ['nested']]);
    }
}
