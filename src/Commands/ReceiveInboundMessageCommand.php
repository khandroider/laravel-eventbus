<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Commands;

use Khandroider\EventBus\DTO\IncomingMessageDTO;

final readonly class ReceiveInboundMessageCommand
{
    public function __construct(
        public IncomingMessageDTO $dto,
    ) {}
}
