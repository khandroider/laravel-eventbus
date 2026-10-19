<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Mappers;

use Khandroider\EventBus\DTO\IncomingMessageDTO;
use Khandroider\EventBus\Enums\MessageDirectionEnum;
use Khandroider\EventBus\Models\Message;

final readonly class IncomingMessageDTOToMessageMapper
{
    public function map(IncomingMessageDTO $dto): Message
    {
        $message = new Message();
        $message->setId($dto->id);
        $message->setDirection(MessageDirectionEnum::Inbound);
        $message->setType($dto->type);
        $message->setPayload($dto->payload);
        $message->setOccurredAt($dto->occurredAt);

        return $message;
    }
}
