<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Handlers;

use Khandroider\EventBus\Commands\StoreOutboundMessageCommand;
use Khandroider\EventBus\Contracts\Repositories\StoreMessageRepository;
use Khandroider\EventBus\Mappers\PublishableEventToMessageMapper;

final readonly class StoreOutboundMessageHandler
{
    public function __construct(
        private PublishableEventToMessageMapper $mapper,
        private StoreMessageRepository $repository,
    ) {}

    public function handle(StoreOutboundMessageCommand $command): void
    {
        // Пишем тем же соединением, что и бизнес-данные: внутри их транзакции — атомарно с ними.
        $this->repository->store($this->mapper->map($command->event));
    }
}
