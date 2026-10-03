<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Support;

use BackedEnum;
use DateTimeInterface;
use Givanov95\TypedHttp\Attributes\Field;
use Givanov95\TypedHttp\Attributes\Ignore;
use Givanov95\TypedHttp\Request;
use JsonSerializable;
use ReflectionObject;
use ReflectionProperty;
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

    private static function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum        => $value->value,
            $value instanceof UnitEnum          => $value->name,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof JsonSerializable  => self::normalize($value->jsonSerialize()),
            is_array($value)                    => array_map(self::normalize(...), $value),
            default                             => $value,
        };
    }
}
