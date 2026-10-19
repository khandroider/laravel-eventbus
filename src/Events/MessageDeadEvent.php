<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Events;

use Khandroider\EventBus\Enums\MessageDirectionEnum;
use Ramsey\Uuid\UuidInterface;

/**
 * Попытки обработки/отправки исчерпаны. Приложение может повесить своего слушателя (алерт, тикет).
 */
final readonly class MessageDeadEvent
{
    public function __construct(
        public UuidInterface $messageId,
        public MessageDirectionEnum $direction,
        public string $type,
        public string $error,
    ) {}
}
