<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests;

use Givanov95\TypedHttp\Enums\BodyFormat;
use Givanov95\TypedHttp\Enums\HttpMethod;
use Givanov95\TypedHttp\Exceptions\RequestException;
use Givanov95\TypedHttp\Exceptions\ResponseException;
use Givanov95\TypedHttp\Request;
use Givanov95\TypedHttp\Response;
use Givanov95\TypedHttp\Testing\MockResponse;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

final class ResponseTest extends TestCase
{
    public function test_json_with_dot_notation_and_default(): void
    {
        $response = new Response(MockResponse::json(['a' => ['b' => 5], 'list' => [1, 2]]));

        $this->assertSame(5, $response->json('a.b'));
        $this->assertSame('d', $response->json('a.x', 'd'));
        $this->assertSame([1, 2], $response->json('list'));
        $this->assertSame(['a' => ['b' => 5], 'list' => [1, 2]], $response->json());
    }

    public function test_object_returns_std_class(): void
    {
        $object = (new Response(MockResponse::json(['label' => ['price' => 10]])))->object();

        $this->assertSame(10, $object->label->price);
    }

    public function test_body_can_be_read_more_than_once(): void
    {
        $response = new Response(MockResponse::make('hello'));

        $this->assertSame('hello', $response->body());
        $this->assertSame('hello', $response->body());
    }

    // `'stream' => true` or a NoSeekStream gives a body that can be read only once.
    public function test_a_body_that_cannot_be_rewound_is_read_once_and_kept(): void
    {
        $stream = new NoSeekStream(Utils::streamFor('{"a":1}'));
        $response = new Response(new Psr7Response(200, ['Content-Type' => 'application/json'], $stream));

        $this->assertSame(['a' => 1], $response->data());
        $this->assertSame(1, $response->json('a'));
        $this->assertSame('{"a":1}', $response->body());
        $this->assertSame(1, $response->object()->a);
    }

    // Regression for v1: invalid or empty JSON surfaced as a raw JsonException.
    public function test_invalid_and_empty_json_throw_response_exception(): void
    {
        foreach ([MockResponse::make('not json'), MockResponse::empty(204)] as $psr) {
            try {
                (new Response($psr))->json();
                $this->fail('ResponseException expected');
            } catch (ResponseException $e) {
                $this->assertNotNull($e->response());
            }
        }
    }

    public function test_xml(): void
    {
        $xml = (new Response(MockResponse::make('<r><a>1</a></r>', 200, ['Content-Type' => 'application/xml'])))->xml();

        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $this->assertSame('1', (string) $xml->a);
    }

    public function test_invalid_xml_throws_response_exception(): void
    {
        $this->expectException(ResponseException::class);

        (new Response(MockResponse::make('<r><a></r>')))->xml();
    }

    public function test_data_follows_the_content_type(): void
    {
        $this->assertSame(['a' => 1], (new Response(MockResponse::json(['a' => 1])))->data());
        $this->assertSame(['a' => 1], (new Response(MockResponse::make('{"a":1}', 200, ['Content-Type' => 'application/problem+json'])))->data());
        $this->assertInstanceOf(SimpleXMLElement::class, (new Response(MockResponse::make('<r/>', 200, ['Content-Type' => 'text/xml'])))->data());
        $this->assertSame('plain', (new Response(MockResponse::make('plain', 200, ['Content-Type' => 'text/plain'])))->data());
        $this->assertNull((new Response(MockResponse::empty()))->data());
    }

    public function test_data_falls_back_to_the_format_the_request_accepts(): void
    {
        $request = new class () extends Request {
            public function method(): HttpMethod
            {
                return HttpMethod::GET;
            }

            public function endpoint(): string
            {
                return '/';
            }
        };

        // An API that sends JSON as text/plain.
        $this->assertSame(['a' => 1], (new Response(MockResponse::make('{"a":1}', 200, ['Content-Type' => 'text/plain']), $request))->data());
        $this->assertSame(['a' => 1], (new Response(MockResponse::make('{"a":1}', 200), $request))->data());
        // Not JSON, or only a JSON scalar: stays text.
        $this->assertSame('plain', (new Response(MockResponse::make('plain', 200, ['Content-Type' => 'text/plain']), $request))->data());
        $this->assertSame('123', (new Response(MockResponse::make('123', 200, ['Content-Type' => 'text/plain']), $request))->data());
        // Without a request there is nothing to go by.
        $this->assertSame('{"a":1}', (new Response(MockResponse::make('{"a":1}', 200, ['Content-Type' => 'text/plain'])))->data());
    }

    public function test_data_falls_back_to_xml_for_an_xml_request_and_xml_is_accepted_by_default(): void
    {
        $request = new class () extends Request {
            public function method(): HttpMethod
            {
                return HttpMethod::POST;
            }

            public function endpoint(): string
            {
                return '/';
            }

            public function bodyFormat(): BodyFormat
            {
                return BodyFormat::Xml;
            }
        };

        $this->assertSame('application/xml', $request->accepts());
        $this->assertInstanceOf(SimpleXMLElement::class, (new Response(MockResponse::make('<r/>', 200, ['Content-Type' => 'text/plain']), $request))->data());
        $this->assertSame('<r>', (new Response(MockResponse::make('<r>', 200, ['Content-Type' => 'text/plain']), $request))->data());
    }

    public function test_status_helpers_headers_and_throw(): void
    {
        $ok = new Response(MockResponse::make('', 201, ['X-Id' => '7']));
        $this->assertTrue($ok->successful());
        $this->assertFalse($ok->failed());
        $this->assertSame('7', $ok->header('x-id'));
        $this->assertNull($ok->header('missing'));
        $this->assertSame($ok, $ok->throw());

        $this->expectException(RequestException::class);
        (new Response(MockResponse::make('', 500)))->throw();
    }
}
