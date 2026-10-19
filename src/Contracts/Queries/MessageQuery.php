<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Contracts\Queries;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Khandroider\EventBus\Enums\MessageDirectionEnum;
use Khandroider\EventBus\Models\Message;
use Ramsey\Uuid\UuidInterface;

interface MessageQuery
{
    public function getById(UuidInterface $id): ?Message;

    /** Исходящие, готовые к отправке, под блокировкой строк (вызывать внутри транзакции). @return Collection<int, Message> */
    public function nextOutbound(int $limit): Collection;

    /** Входящие, которым пришло время повтора. @return Collection<int, Message> */
    public function dueInbound(int $limit): Collection;

    public function countPending(MessageDirectionEnum $direction): int;

    public function countDead(MessageDirectionEnum $direction): int;

    public function oldestPendingAt(MessageDirectionEnum $direction): ?Carbon;
}
