<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Services;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonSerializable;
use Ramsey\Uuid\UuidInterface;
use Stringable;
use UnitEnum;

/**
 * Объект события → массив для JSON. Только публичные поля, рекурсивно.
 * Даты — ISO-8601 в UTC, enum — значение, Uuid — строка. Формат нейтрален к языку.
 */
final readonly class EventSerializer
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.u\Z';

    public function toArray(object $event): array
    {
        $result = [];

        foreach (get_object_vars($event) as $name => $value) {
            $result[$name] = $this->normalize($value);
        }

        return $result;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return array_map($this->normalize(...), $value);
        }

        if ($value instanceof Model) {
            throw new InvalidArgumentException(sprintf(
                'Eloquent model %s cannot be published to the event bus; put its id into the event instead.',
                $value::class,
            ));
        }

        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new DateTimeZone('UTC'))
                ->format(self::DATE_FORMAT),
            $value instanceof UuidInterface => $value->toString(),
            $value instanceof JsonSerializable => $this->normalize($value->jsonSerialize()),
            get_object_vars($value) !== [] => $this->toArray($value),
            $value instanceof Stringable => (string) $value,
            default => throw new InvalidArgumentException(sprintf(
                'Cannot serialize %s: no public properties, not Stringable, not JsonSerializable.',
                $value::class,
            )),
        };
    }
}
