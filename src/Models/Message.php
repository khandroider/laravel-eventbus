<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Khandroider\EventBus\Enums\MessageDirectionEnum;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @property string $id
 * @property string $direction
 * @property string $type
 * @property string|null $routing_key
 * @property array $payload
 * @property Carbon $occurred_at
 * @property Carbon|null $processed_at
 * @property int $attempts
 * @property Carbon|null $next_retry_at
 * @property string|null $last_error
 * @property-read Carbon|null $created_at
 */
final class Message extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'eventbus_messages';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'occurred_at' => 'datetime',
        'processed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function getId(): UuidInterface
    {
        return Uuid::fromString($this->id);
    }

    public function setId(UuidInterface $id): void
    {
        $this->id = $id->toString();
    }

    public function getDirection(): MessageDirectionEnum
    {
        return MessageDirectionEnum::from($this->direction);
    }

    public function setDirection(MessageDirectionEnum $direction): void
    {
        $this->direction = $direction->value;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getRoutingKey(): ?string
    {
        return $this->routing_key;
    }

    public function setRoutingKey(?string $routingKey): void
    {
        $this->routing_key = $routingKey;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getOccurredAt(): Carbon
    {
        return $this->occurred_at;
    }

    public function setOccurredAt(Carbon $occurredAt): void
    {
        $this->occurred_at = $occurredAt;
    }

    public function getProcessedAt(): ?Carbon
    {
        return $this->processed_at;
    }

    public function setProcessedAt(?Carbon $processedAt): void
    {
        $this->processed_at = $processedAt;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): void
    {
        $this->attempts = $attempts;
    }

    public function getNextRetryAt(): ?Carbon
    {
        return $this->next_retry_at;
    }

    public function setNextRetryAt(?Carbon $nextRetryAt): void
    {
        $this->next_retry_at = $nextRetryAt;
    }

    public function getLastError(): ?string
    {
        return $this->last_error;
    }

    public function setLastError(?string $lastError): void
    {
        $this->last_error = $lastError;
    }
}
