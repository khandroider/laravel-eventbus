<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Repositories\Eloquent;

use Carbon\Carbon;
use Khandroider\EventBus\Contracts\Repositories\StoreMessageRepository;
use Khandroider\EventBus\Contracts\Repositories\UpdateMessageRepository;
use Khandroider\EventBus\Models\Message;

final class MessageRepository implements StoreMessageRepository, UpdateMessageRepository
{
    public function store(Message $message): void
    {
        $message->saveOrFail();
    }

    public function storeIfAbsent(Message $message): bool
    {
        $attributes = $message->getAttributes() + [Message::CREATED_AT => Carbon::now()];
        $attributes['payload'] = json_encode($message->getPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $inserted = Message::query()->insertOrIgnore($attributes);

        if ($inserted === 1) {
            $message->exists = true;
            $message->syncOriginal();
        }

        return $inserted === 1;
    }

    public function update(Message $message): void
    {
        $message->saveOrFail();
    }
}
