<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Contracts\Repositories;

use Closure;
use Khandroider\EventBus\Models\Message;

interface BrokerRepository
{
    /** Публикует с подтверждением брокера; исключение — сообщение не доставлено. */
    public function publish(Message $message): void;

    /**
     * Блокирующий цикл чтения своей очереди. $onMessage(string $body) вызывается на каждое сообщение;
     * вернулся без исключения — ack, выбросил — nack с возвратом в очередь.
     *
     * @param Closure(string): void $onMessage
     */
    public function consume(Closure $onMessage): void;
}
