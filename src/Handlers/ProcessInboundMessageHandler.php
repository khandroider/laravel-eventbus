<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Handlers;

use Carbon\Carbon;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Khandroider\EventBus\Commands\ProcessInboundMessageCommand;
use Khandroider\EventBus\Contracts\Queries\MessageQuery;
use Khandroider\EventBus\Contracts\Repositories\UpdateMessageRepository;
use Khandroider\EventBus\Services\EventHydrator;
use Khandroider\EventBus\Services\MessageFailureService;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ProcessInboundMessageHandler
{
    private const string UNMAPPED_TYPE_ERROR = 'unmapped type';

    public function __construct(
        private MessageQuery $query,
        private UpdateMessageRepository $repository,
        private EventHydrator $hydrator,
        private MessageFailureService $failure,
        private Dispatcher $events,
        private Repository $config,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(ProcessInboundMessageCommand $command): void
    {
        $message = $this->query->getById($command->messageId);

        if ($message === null || $message->getProcessedAt() !== null) {
            return;
        }

        /** @var array<string, class-string> $map */
        $map = $this->config->get('eventbus.inbox.events', []);
        $eventClass = $map[$message->getType()] ?? null;

        if ($eventClass === null) {
            $this->logger->warning('[EVENTBUS] Inbound message type is not mapped to an event, skipped', [
                'message_id' => $message->getId()->toString(),
                'type' => $message->getType(),
            ]);
            $message->setLastError(self::UNMAPPED_TYPE_ERROR);
            $message->setProcessedAt(Carbon::now());
            $this->repository->update($message);

            return;
        }

        try {
            $this->events->dispatch($this->hydrator->hydrate($eventClass, $message->getPayload()));
        } catch (Throwable $error) {
            $this->failure->fail($message, $error);

            return;
        }

        $message->setProcessedAt(Carbon::now());
        $message->setLastError(null);
        $this->repository->update($message);
    }
}
