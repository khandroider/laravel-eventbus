<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Bus\Dispatcher;
use InvalidArgumentException;
use JsonException;
use Khandroider\EventBus\Commands\ReceiveInboundMessageCommand;
use Khandroider\EventBus\Contracts\Repositories\BrokerRepository;
use Khandroider\EventBus\DTO\IncomingMessageDTO;
use Psr\Log\LoggerInterface;

/**
 * Читает свою очередь брокера и складывает сообщения во входящий ящик. Долгоживущий процесс.
 */
final class ConsumeCommand extends Command
{
    protected $signature = 'eventbus:consume';

    protected $description = 'Принимает сообщения из брокера во входящий ящик и обрабатывает их';

    private const int JSON_DEPTH = 512;

    public function __construct(
        private readonly BrokerRepository $broker,
        private readonly Dispatcher $bus,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->broker->consume(function (string $body): void {
            try {
                $dto = IncomingMessageDTO::fromArray(json_decode($body, true, self::JSON_DEPTH, JSON_THROW_ON_ERROR));
            } catch (JsonException|InvalidArgumentException $e) {
                // Конверт не наш или битый: повтор не поможет, подтверждаем и оставляем след в логе.
                $this->logger->warning('[EVENTBUS] Unreadable inbound message dropped', [
                    'error' => $e->getMessage(),
                    'body' => mb_substr($body, 0, 1000),
                ]);

                return;
            }

            $this->bus->dispatch(new ReceiveInboundMessageCommand($dto));
        });

        return self::SUCCESS;
    }
}
