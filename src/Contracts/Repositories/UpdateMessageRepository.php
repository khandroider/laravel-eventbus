<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Contracts\Repositories;

use Khandroider\EventBus\Models\Message;

interface UpdateMessageRepository
{
    public function update(Message $message): void;
}
