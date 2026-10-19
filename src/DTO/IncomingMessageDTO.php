<?php

declare(strict_types=1);

namespace Khandroider\EventBus\DTO;

use Carbon\Carbon;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Конверт входящего сообщения: {version, id, type, source, occurred_at, payload}.
 */
final readonly class IncomingMessageDTO
{
    public function __construct(
        public UuidInterface $id,
        public string $type,
        public array $payload,
        public Carbon $occurredAt,
        public ?string $source,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? throw new InvalidArgumentException('id is required');

        if (! is_string($id) || ! Uuid::isValid($id)) {
            throw new InvalidArgumentException('id must be a UUID string');
        }

        $type = $data['type'] ?? throw new InvalidArgumentException('type is required');

        if (! is_string($type) || $type === '') {
            throw new InvalidArgumentException('type must be a non-empty string');
        }

        $payload = $data['payload'] ?? [];

        if (! is_array($payload)) {
            throw new InvalidArgumentException('payload must be an object');
        }

        return new self(
            id: Uuid::fromString($id),
            type: $type,
            payload: $payload,
            occurredAt: isset($data['occurred_at']) ? Carbon::parse((string) $data['occurred_at']) : Carbon::now(),
            source: isset($data['source']) ? (string) $data['source'] : null,
        );
    }
}
