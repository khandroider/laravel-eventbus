<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Services;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

/**
 * Массив из JSON → объект события через конструктор (именованные аргументы).
 * Типы приводятся по сигнатуре конструктора: обратная сторона EventSerializer.
 */
final readonly class EventHydrator
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    public function hydrate(string $class, array $payload): object
    {
        $constructor = (new ReflectionClass($class))->getConstructor();

        if ($constructor === null) {
            return new $class;
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (! array_key_exists($name, $payload)) {
                if ($parameter->isDefaultValueAvailable()) {
                    continue;
                }

                if ($parameter->allowsNull()) {
                    $arguments[$name] = null;

                    continue;
                }

                throw new InvalidArgumentException(sprintf('Field "%s" is required to build %s.', $name, $class));
            }

            $arguments[$name] = $this->cast($payload[$name], $parameter->getType());
        }

        return new $class(...$arguments);
    }

    private function cast(mixed $value, ?ReflectionType $type): mixed
    {
        if ($value === null || $type === null) {
            return $value;
        }

        if ($type instanceof ReflectionUnionType) {
            $classTypes = array_filter(
                $type->getTypes(),
                static fn (ReflectionType $t) => $t instanceof ReflectionNamedType && ! $t->isBuiltin(),
            );

            // Один класс в объединении (например Carbon|null) — приводим к нему, иначе отдаём как есть.
            return count($classTypes) === 1 ? $this->cast($value, reset($classTypes)) : $value;
        }

        if (! $type instanceof ReflectionNamedType) {
            return $value;
        }

        $name = $type->getName();

        if ($type->isBuiltin()) {
            return match ($name) {
                'int' => (int) $value,
                'float' => (float) $value,
                'string' => (string) $value,
                'bool' => (bool) $value,
                default => $value,
            };
        }

        return match (true) {
            is_a($name, BackedEnum::class, true) => $name::from($value),
            is_a($name, UnitEnum::class, true) => constant($name.'::'.$value),
            is_a($name, UuidInterface::class, true) => Uuid::fromString((string) $value),
            is_a($name, DateTimeInterface::class, true) => (new ReflectionClass($name))->isInstantiable()
                ? new $name((string) $value)
                : new CarbonImmutable((string) $value),
            is_array($value) && class_exists($name) => $this->hydrate($name, $value),
            default => $value,
        };
    }
}
