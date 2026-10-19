<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Queries\Eloquent;

use Carbon\Carbon;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Khandroider\EventBus\Contracts\Queries\MessageQuery as MessageQueryContract;
use Khandroider\EventBus\Enums\MessageDirectionEnum;
use Khandroider\EventBus\Models\Message;
use Ramsey\Uuid\UuidInterface;

final class MessageQuery implements MessageQueryContract
{
    private const string SKIP_LOCKED = 'for update skip locked';

    public function __construct(
        private readonly Repository $config,
    ) {
    }

    public function getById(UuidInterface $id): ?Message
    {
        return Message::query()->find($id->toString());
    }

    public function nextOutbound(int $limit): Collection
    {
        return $this->due(MessageDirectionEnum::Outbound)
            ->lock(self::SKIP_LOCKED)
            ->limit($limit)
            ->get();
    }

    public function dueInbound(int $limit): Collection
    {
        return $this->due(MessageDirectionEnum::Inbound)
            ->limit($limit)
            ->get();
    }

    public function countPending(MessageDirectionEnum $direction): int
    {
        return $this->pending($direction)
            ->where('attempts', '<', $this->maxAttempts())
            ->count();
    }

    public function countDead(MessageDirectionEnum $direction): int
    {
        return $this->pending($direction)
            ->where('attempts', '>=', $this->maxAttempts())
            ->count();
    }

    public function oldestPendingAt(MessageDirectionEnum $direction): ?Carbon
    {
        $value = $this->pending($direction)
            ->where('attempts', '<', $this->maxAttempts())
            ->min('occurred_at');

        return $value === null ? null : Carbon::parse($value);
    }

    private function due(MessageDirectionEnum $direction): Builder
    {
        return $this->pending($direction)
            ->where('attempts', '<', $this->maxAttempts())
            ->where(fn (Builder $q) => $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', Carbon::now()))
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    private function pending(MessageDirectionEnum $direction): Builder
    {
        return Message::query()
            ->where('direction', $direction->value)
            ->whereNull('processed_at');
    }

    private function maxAttempts(): int
    {
        return (int) $this->config->get('eventbus.max_attempts');
    }
}
