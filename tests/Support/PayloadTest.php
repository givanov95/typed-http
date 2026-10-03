<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Tests\Support;

use DateTimeImmutable;
use Givanov95\TypedHttp\Attributes\Field;
use Givanov95\TypedHttp\Attributes\Ignore;
use Givanov95\TypedHttp\Enums\HttpMethod;
use Givanov95\TypedHttp\Request;
use Givanov95\TypedHttp\Support\Payload;
use Givanov95\TypedHttp\Tests\Fixtures\FindSiteRequest;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use stdClass;
use Stringable;

enum Size: string
{
    case Small = 's';
}

final class PayloadTest extends TestCase
{
    public function test_nested_objects_are_normalized_like_top_level_values(): void
    {
        $address = new class () {
            public Size $size = Size::Small;

            public DateTimeImmutable $since;

            public ?string $note = null;

            public function __construct()
            {
                $this->since = new DateTimeImmutable('2026-10-03T10:00:00+00:00');
            }
        };
        $code = new class () implements Stringable {
            public function __toString(): string
            {
                return 'BG-1';
            }
        };
        $file = new SplFileInfo(__FILE__);
        $request = new class ($address, $code, $file) extends Request {
            /** @var array<string,mixed> */
            public array $parts;

            public function __construct(public object $address, public Stringable $code, SplFileInfo $file)
            {
                $this->parts = ['code' => $code, 'file' => $file, 'empty' => new stdClass()];
            }

            public function method(): HttpMethod
            {
                return HttpMethod::POST;
            }

            public function endpoint(): string
            {
                return '/';
            }
        };

        $payload = Payload::collect($request);

        $this->assertSame(['size' => 's', 'since' => '2026-10-03T10:00:00+00:00', 'note' => null], $payload['address']);
        $this->assertSame('BG-1', $payload['code']);
        $this->assertSame('BG-1', $payload['parts']['code']);
        // Files stay objects for the multipart encoder, an empty stdClass stays an object for JSON.
        $this->assertSame($file, $payload['parts']['file']);
        $this->assertEquals(new stdClass(), $payload['parts']['empty']);
    }

    public function test_properties_from_parent_classes_are_included(): void
    {
        $payload = Payload::collect(new FindSiteRequest(name: 'Varna'));
        ksort($payload);

        $this->assertSame([
            'countryId' => '100',
            'language'  => 'EN',
            'name'      => 'Varna',
            'password'  => 'secret',
            'userName'  => 'user',
        ], $payload);
    }

    public function test_null_uninitialized_static_and_ignored_properties_are_skipped(): void
    {
        $request = new class () extends Request {
            public static string $static = 'x';

            public ?string $nullable = null;

            public string $uninitialized;

            #[Ignore]
            public string $internal = 'secret';

            public string $kept = 'yes';

            protected string $notPublic = 'no';

            public function method(): HttpMethod
            {
                return HttpMethod::POST;
            }

            public function endpoint(): string
            {
                return '/';
            }
        };

        $this->assertSame(['kept' => 'yes'], Payload::collect($request));
    }

    public function test_field_attribute_renames_and_values_are_normalized(): void
    {
        $request = new class () extends Request {
            #[Field('site_id')]
            public int $siteId = 7;

            public Size $size = Size::Small;

            public DateTimeImmutable $at;

            /** @var array<string,mixed> */
            public array $nested = ['size' => Size::Small, 'empty' => null];

            public function __construct()
            {
                $this->at = new DateTimeImmutable('2026-10-03T10:00:00+00:00');
            }

            public function method(): HttpMethod
            {
                return HttpMethod::POST;
            }

            public function endpoint(): string
            {
                return '/';
            }
        };

        $this->assertSame([
            'site_id' => 7,
            'size'    => 's',
            'at'      => '2026-10-03T10:00:00+00:00',
            'nested'  => ['size' => 's', 'empty' => null],
        ], Payload::collect($request));
    }
}
