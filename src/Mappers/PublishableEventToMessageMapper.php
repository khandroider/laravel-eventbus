<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Mappers;

use Carbon\Carbon;
use Illuminate\Contracts\Config\Repository;
use Khandroider\EventBus\Contracts\PublishableEvent;
use Khandroider\EventBus\Enums\MessageDirectionEnum;
use Khandroider\EventBus\Models\Message;
use Khandroider\EventBus\Services\EventSerializer;
use Ramsey\Uuid\Uuid;

/**
 * Событие → исходящая строка: type = getEventName(), routing_key = {source}.{type}.
 */
final readonly class PublishableEventToMessageMapper
{
    private const string ROUTING_KEY_SEPARATOR = '.';

    public function __construct(
        private EventSerializer $serializer,
        private Repository $config,
    ) {}

    public function map(PublishableEvent $event): Message
    {
        $type = $event->getEventName();

        $message = new Message;
        $message->setId(Uuid::uuid4());
        $message->setDirection(MessageDirectionEnum::Outbound);
        $message->setType($type);
        $message->setRoutingKey($this->config->get('eventbus.source').self::ROUTING_KEY_SEPARATOR.$type);
        $message->setPayload($this->serializer->toArray($event));
        $message->setOccurredAt(Carbon::now());

        return $message;
    }
}
