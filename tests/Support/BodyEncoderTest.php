<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Support;

use Givanov95\TypedHttp\Enums\BodyFormat;
use Givanov95\TypedHttp\Exceptions\TypedHttpException;
use Givanov95\TypedHttp\Support\BodyEncoder;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

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

    // Regression: SplFileInfo is Stringable, so a file used to be sent as a text field holding its path.
    public function test_multipart_sends_a_file_object_as_a_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'typed-http');
        file_put_contents($path, 'FILEDATA');

        try {
            $body = (string) BodyEncoder::encode(BodyFormat::Multipart, ['doc' => new SplFileInfo($path), 'named' => ['contents' => new SplFileInfo($path), 'filename' => 'x.txt']])->stream;
        } finally {
            unlink($path);
        }

        $this->assertStringContainsString('name="doc"; filename="' . basename($path) . '"', $body);
        $this->assertStringContainsString('name="named"; filename="x.txt"', $body);
        $this->assertSame(2, substr_count($body, "\r\n\r\nFILEDATA\r\n"));
        $this->assertStringNotContainsString($path . "\r\n", $body);
    }

    public function test_multipart_file_that_cannot_be_read_throws_library_exception(): void
    {
        $this->expectException(TypedHttpException::class);

        BodyEncoder::encode(BodyFormat::Multipart, ['doc' => new SplFileInfo('/nonexistent/file.txt')]);
    }

    public function test_files_outside_multipart_are_rejected(): void
    {
        foreach ([BodyFormat::Json, BodyFormat::Form, BodyFormat::Xml, BodyFormat::Text] as $format) {
            try {
                BodyEncoder::encode($format, ['nested' => ['doc' => new SplFileInfo(__FILE__)]]);
                $this->fail('TypedHttpException expected for ' . $format->name);
            } catch (TypedHttpException $e) {
                $this->assertStringContainsString('Multipart', $e->getMessage());
            }
        }
    }

    // Regression: a field called "contents" turned the whole array into one part and dropped its siblings.
    public function test_a_field_named_contents_next_to_other_fields_is_not_a_part(): void
    {
        $body = (string) BodyEncoder::encode(BodyFormat::Multipart, ['article' => ['title' => 'Hi', 'contents' => 'Body text']])->stream;

        $this->assertStringContainsString('name="article[title]"', $body);
        $this->assertStringContainsString('name="article[contents]"', $body);
        $this->assertStringContainsString("\r\n\r\nBody text\r\n", $body);
    }

    public function test_nested_nulls_are_skipped_outside_json(): void
    {
        $multipart = (string) BodyEncoder::encode(BodyFormat::Multipart, ['meta' => ['note' => null, 'k' => 'v']])->stream;
        $this->assertStringNotContainsString('meta[note]', $multipart);
        $this->assertStringContainsString('meta[k]', $multipart);

        $xml = (string) BodyEncoder::encode(BodyFormat::Xml, ['a' => null, 'b' => ['c' => null, 'd' => 'x']])->stream;
        $this->assertStringNotContainsString('<a', $xml);
        $this->assertStringNotContainsString('<c', $xml);
        $this->assertStringContainsString('<d>x</d>', $xml);

        $this->assertSame("a\nb", (string) BodyEncoder::encode(BodyFormat::Text, ['x' => 'a', 'n' => null, 'y' => 'b'])->stream);
    }

    public function test_invalid_xml_element_names_throw_library_exception(): void
    {
        foreach (['user name', '@id', '2fa'] as $name) {
            try {
                BodyEncoder::encode(BodyFormat::Xml, [$name => 'x']);
                $this->fail('TypedHttpException expected for "' . $name . '"');
            } catch (TypedHttpException $e) {
                $this->assertInstanceOf(\DOMException::class, $e->getPrevious());
            }
        }
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
        $this->expectException(TypedHttpException::class);

        BodyEncoder::encode(BodyFormat::Text, ['x' => ['nested']]);
    }
}
