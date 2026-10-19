<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionInterface;
use Khandroider\EventBus\Contracts\Queries\MessageQuery;
use Khandroider\EventBus\Contracts\Repositories\BrokerRepository;
use Khandroider\EventBus\Contracts\Repositories\UpdateMessageRepository;
use Khandroider\EventBus\Models\Message;
use Khandroider\EventBus\Services\MessageFailureService;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Реле исходящего ящика: таблица → брокер. Долгоживущий процесс; --once делает один проход.
 */
final class RelayCommand extends Command
{
    protected $signature = 'eventbus:relay {--once : Один проход и выход}';

    protected $description = 'Отправляет исходящие сообщения из eventbus_messages в брокер';

    private const int IDLE_SLEEP_SECONDS = 1;

    private const int BROKER_DOWN_SLEEP_SECONDS = 5;

    private const int BROKER_ERROR_LOG_INTERVAL_SECONDS = 60;

    private ?Carbon $lastBrokerErrorLoggedAt = null;

    public function __construct(
        private readonly MessageQuery $query,
        private readonly BrokerRepository $broker,
        private readonly UpdateMessageRepository $repository,
        private readonly MessageFailureService $failure,
        private readonly ConnectionInterface $db,
        private readonly Repository $config,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $once = (bool) $this->option('once');

        do {
            try {
                $sent = $this->relayBatch();
            } catch (AMQPExceptionInterface|Throwable $e) {
                $this->reportBrokerError($e);
                $sent = 0;

                if (! $once) {
                    sleep(self::BROKER_DOWN_SLEEP_SECONDS);

                    continue;
                }
            }

            if ($sent === 0 && ! $once) {
                sleep(self::IDLE_SLEEP_SECONDS);
            }
        } while (! $once);

        return self::SUCCESS;
    }

    /**
     * Строки под блокировкой до конца транзакции, поэтому несколько реле не возьмут одно сообщение.
     * Ошибка соединения с брокером прерывает партию: уже отправленное фиксируем, остальное ждёт следующего прохода.
     */
    private function relayBatch(): int
    {
        $connectionError = null;

        $sent = $this->db->transaction(function () use (&$connectionError): int {
            $sent = 0;

            /** @var Message $message */
            foreach ($this->query->nextOutbound((int) $this->config->get('eventbus.batch')) as $message) {
                try {
                    $this->broker->publish($message);
                } catch (AMQPExceptionInterface $e) {
                    $connectionError = $e;
                    break;
                } catch (Throwable $e) {
                    $this->failure->fail($message, $e);

                    continue;
                }

                $message->setProcessedAt(Carbon::now());
                $message->setLastError(null);
                $this->repository->update($message);
                $sent++;
            }

            return $sent;
        });

        if ($connectionError !== null) {
            throw $connectionError;
        }

        return $sent;
    }

    private function reportBrokerError(Throwable $e): void
    {
        $now = Carbon::now();
        $context = ['error' => $e->getMessage(), 'class' => $e::class];

        if ($this->lastBrokerErrorLoggedAt === null
            || $this->lastBrokerErrorLoggedAt->diffInSeconds($now) >= self::BROKER_ERROR_LOG_INTERVAL_SECONDS) {
            $this->logger->error('[EVENTBUS] Broker unavailable, relay is waiting', $context);
            $this->lastBrokerErrorLoggedAt = $now;

            return;
        }

        $this->logger->debug('[EVENTBUS] Broker still unavailable', $context);
    }
}
