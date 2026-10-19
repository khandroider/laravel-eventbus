<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Contracts;

/**
 * Маркер: событие с этим интерфейсом при event() записывается в outbox
 * в той же транзакции и затем публикуется в брокер.
 */
interface PublishableEvent {}
