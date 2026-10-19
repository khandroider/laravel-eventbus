<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Enums;

enum MessageDirectionEnum: string
{
    case Outbound = 'outbound';
    case Inbound = 'inbound';
}
