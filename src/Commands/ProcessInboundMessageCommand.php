<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Commands;

use Ramsey\Uuid\UuidInterface;

final readonly class ProcessInboundMessageCommand
{
    public function __construct(
        public UuidInterface $messageId,
    ) {}
}
