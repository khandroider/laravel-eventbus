<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Listeners;

use Illuminate\Contracts\Bus\Dispatcher;
use Khandroider\EventBus\Commands\StoreOutboundMessageCommand;
use Khandroider\EventBus\Contracts\PublishableEvent;

/**
 * Подписан на интерфейс PublishableEvent: Laravel вызывает его для любого события, реализующего маркер.
 */
final readonly class StoreInOutboxOnPublishableEventListener
{
    public function __construct(
        private Dispatcher $bus,
    ) {}

    public function handle(PublishableEvent $event): void
    {
        $this->bus->dispatch(new StoreOutboundMessageCommand($event));
    }
}
