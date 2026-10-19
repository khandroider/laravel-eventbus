<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Contracts\Repositories;

use Khandroider\EventBus\Models\Message;

interface StoreMessageRepository
{
    public function store(Message $message): void;

    /** @return bool true, если строка создана; false, если сообщение с таким id уже было. */
    public function storeIfAbsent(Message $message): bool;
}
