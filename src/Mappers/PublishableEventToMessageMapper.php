<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Mappers;

use Carbon\Carbon;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Khandroider\EventBus\Contracts\PublishableEvent;
use Khandroider\EventBus\Enums\MessageDirectionEnum;
use Khandroider\EventBus\Models\Message;
use Khandroider\EventBus\Services\EventSerializer;
use Ramsey\Uuid\Uuid;

/**
 * App\Modules\Candidate\Events\CandidateCreatedEvent
 *   → type        = candidate_created
 *   → routing_key = {source}.candidate.candidate_created
 */
final readonly class PublishableEventToMessageMapper
{
    private const string EVENTS_NAMESPACE_SEGMENT = 'Events';
    private const string EVENT_SUFFIX = 'Event';
    private const string DEFAULT_MODULE = 'app';

    public function __construct(
        private EventSerializer $serializer,
        private Repository $config,
    ) {
    }

    public function map(PublishableEvent $event): Message
    {
        $segments = explode('\\', $event::class);
        $shortName = (string) array_pop($segments);
        $eventsIndex = array_search(self::EVENTS_NAMESPACE_SEGMENT, $segments, true);
        $module = $eventsIndex !== false && $eventsIndex > 0 ? $segments[$eventsIndex - 1] : self::DEFAULT_MODULE;

        $type = Str::snake((string) preg_replace('/' . self::EVENT_SUFFIX . '$/', '', $shortName));

        $message = new Message();
        $message->setId(Uuid::uuid4());
        $message->setDirection(MessageDirectionEnum::Outbound);
        $message->setType($type);
        $message->setRoutingKey(implode('.', [$this->config->get('eventbus.source'), Str::snake($module), $type]));
        $message->setPayload($this->serializer->toArray($event));
        $message->setOccurredAt(Carbon::now());

        return $message;
    }
}
