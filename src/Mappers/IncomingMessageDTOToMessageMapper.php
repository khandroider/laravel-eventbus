<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Mappers;

use Khandroider\EventBus\DTO\IncomingMessageDTO;
use Khandroider\EventBus\Enums\MessageDirectionEnum;
use Khandroider\EventBus\Models\Message;

/**
 * Конверт → входящая строка. routing_key восстанавливаем из конверта ({source}.{type}):
 * брокер его не передаёт, а без него не видно, от какого сервиса пришло сообщение.
 */
final readonly class IncomingMessageDTOToMessageMapper
{
    private const string ROUTING_KEY_SEPARATOR = '.';

    public function map(IncomingMessageDTO $dto): Message
    {
        $message = new Message;
        $message->setId($dto->id);
        $message->setDirection(MessageDirectionEnum::Inbound);
        $message->setType($dto->type);
        $message->setRoutingKey($dto->source === null ? null : $dto->source.self::ROUTING_KEY_SEPARATOR.$dto->type);
        $message->setPayload($dto->payload);
        $message->setOccurredAt($dto->occurredAt);

        return $message;
    }
}
