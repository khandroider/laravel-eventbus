<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Handlers;

use Illuminate\Contracts\Bus\Dispatcher;
use Khandroider\EventBus\Commands\ProcessInboundMessageCommand;
use Khandroider\EventBus\Commands\ReceiveInboundMessageCommand;
use Khandroider\EventBus\Contracts\Repositories\StoreMessageRepository;
use Khandroider\EventBus\Mappers\IncomingMessageDTOToMessageMapper;
use Psr\Log\LoggerInterface;

final readonly class ReceiveInboundMessageHandler
{
    public function __construct(
        private IncomingMessageDTOToMessageMapper $mapper,
        private StoreMessageRepository $repository,
        private Dispatcher $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(ReceiveInboundMessageCommand $command): void
    {
        $message = $this->mapper->map($command->dto);

        if (! $this->repository->storeIfAbsent($message)) {
            $this->logger->info('[EVENTBUS] Duplicate inbound message, already known', [
                'message_id' => $message->getId()->toString(),
                'type' => $message->getType(),
            ]);
        }

        $this->bus->dispatch(new ProcessInboundMessageCommand($message->getId()));
    }
}
