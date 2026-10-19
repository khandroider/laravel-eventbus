<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Commands;

use Khandroider\EventBus\Contracts\PublishableEvent;

final readonly class StoreOutboundMessageCommand
{
    public function __construct(
        public PublishableEvent $event,
    ) {
    }
}
