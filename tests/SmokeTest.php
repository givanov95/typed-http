<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests;

use Givanov95\TypedHttp\Requests\Request;
use Givanov95\TypedHttp\Requests\Url;
use Givanov95\TypedHttp\Responses\ResponseParser;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function test_core_classes_autoload(): void
    {
        $this->assertTrue(class_exists(Request::class));
        $this->assertTrue(class_exists(Url::class));
        $this->assertTrue(class_exists(ResponseParser::class));
    }
}
