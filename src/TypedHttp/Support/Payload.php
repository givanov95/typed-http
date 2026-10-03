<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Support;

use BackedEnum;
use DateTimeInterface;
use Givanov95\TypedHttp\Attributes\Field;
use Givanov95\TypedHttp\Attributes\Ignore;
use Givanov95\TypedHttp\Request;
use JsonSerializable;
use Psr\Http\Message\StreamInterface;
use ReflectionObject;
use ReflectionProperty;
use SplFileInfo;
use stdClass;
use Stringable;
use UnitEnum;

final class Payload
{
    /**
     * Public properties of the request, including inherited ones. Skips null, uninitialized, static and #[Ignore] properties.
     *
     * @return array<string,mixed>
     */
    public static function collect(Request $request): array
    {
        $data = [];

        foreach ((new ReflectionObject($request))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || $property->getAttributes(Ignore::class) !== []) {
                continue;
            }

            if (! $property->isInitialized($request)) {
                continue;
            }

            $value = $property->getValue($request);

            if ($value === null) {
                continue;
            }

            $field = $property->getAttributes(Field::class)[0] ?? null;
            $name = $field !== null ? $field->newInstance()->name : $property->getName();

            $data[$name] = self::normalize($value);
        }

        return $data;
    }

    /**
     * Applied at every depth. Files and streams stay as they are for the multipart encoder
     * (SplFileInfo is Stringable, so it has to be matched before that case).
     */
    private static function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum                                     => $value->value,
            $value instanceof UnitEnum                                       => $value->name,
            $value instanceof DateTimeInterface                              => $value->format(DateTimeInterface::ATOM),
            $value instanceof SplFileInfo, $value instanceof StreamInterface => $value,
            $value instanceof JsonSerializable                               => self::normalize($value->jsonSerialize()),
            $value instanceof Stringable                                     => (string) $value,
            is_array($value)                                                 => array_map(self::normalize(...), $value),
            $value instanceof stdClass                                       => (object) array_map(self::normalize(...), get_object_vars($value)),
            is_object($value)                                                => array_map(self::normalize(...), get_object_vars($value)),
            default                                                          => $value,
        };
    }
}
