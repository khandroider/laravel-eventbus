<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Khandroider\EventBus\Commands\ProcessInboundMessageCommand;
use Khandroider\EventBus\Contracts\Queries\MessageQuery;
use Khandroider\EventBus\Models\Message;

/**
 * Повторяет входящие сообщения, которым пришло время (next_retry_at). Ставится в планировщик раз в минуту.
 */
final class RetryInboundCommand extends Command
{
    protected $signature = 'eventbus:retry-inbox';

    protected $description = 'Повторяет обработку входящих сообщений, упавших ранее';

    public function __construct(
        private readonly MessageQuery $query,
        private readonly Dispatcher $bus,
        private readonly Repository $config,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $messages = $this->query->dueInbound((int) $this->config->get('eventbus.batch'));

        /** @var Message $message */
        foreach ($messages as $message) {
            $this->bus->dispatch(new ProcessInboundMessageCommand($message->getId()));
        }

        $this->info(sprintf('Retried %d inbound message(s).', $messages->count()));

        return self::SUCCESS;
    }
}
