<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Enums;

use Givanov95\TypedHttp\Enums\BodyFormat;
use Givanov95\TypedHttp\Enums\HttpMethod;
use PHPUnit\Framework\TestCase;

final class HttpMethodTest extends TestCase
{
    public function test_only_get_head_and_options_have_no_body(): void
    {
        $withoutBody = array_filter(HttpMethod::cases(), fn (HttpMethod $m) => ! $m->hasBody());

        $this->assertEqualsCanonicalizing(
            [HttpMethod::GET, HttpMethod::HEAD, HttpMethod::OPTIONS],
            array_values($withoutBody),
        );
    }

    public function test_post_and_patch_are_not_idempotent(): void
    {
        $this->assertFalse(HttpMethod::POST->isIdempotent());
        $this->assertFalse(HttpMethod::PATCH->isIdempotent());
        $this->assertTrue(HttpMethod::GET->isIdempotent());
        $this->assertTrue(HttpMethod::PUT->isIdempotent());
        $this->assertTrue(HttpMethod::DELETE->isIdempotent());
    }

    public function test_multipart_has_no_fixed_content_type(): void
    {
        $this->assertNull(BodyFormat::Multipart->contentType());
        $this->assertSame('application/json', BodyFormat::Json->contentType());
    }
}
