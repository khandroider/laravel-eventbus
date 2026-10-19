<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Contracts;

/**
 * Событие с этим интерфейсом при event() записывается в outbox в той же транзакции
 * и затем публикуется в брокер с ключом маршрутизации {source}.{getEventName()}.
 */
interface PublishableEvent
{
    /** Имя события в сообщении (поле `type`), snake_case без точек: candidate_created. */
    public function getEventName(): string;
}
