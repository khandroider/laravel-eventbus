<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Khandroider\EventBus\Contracts\Repositories\UpdateMessageRepository;
use Khandroider\EventBus\Events\MessageDeadEvent;
use Khandroider\EventBus\Models\Message;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Единая реакция на неудачу: попытка +1, пауза растёт вдвое, после max_attempts — «мёртвое» сообщение.
 */
final readonly class MessageFailureService
{
    private const int MAX_BACKOFF_SECONDS = 86400;

    public function __construct(
        private UpdateMessageRepository $repository,
        private Repository $config,
        private Dispatcher $events,
        private LoggerInterface $logger,
    ) {
    }

    public function fail(Message $message, Throwable $error): void
    {
        $attempts = $message->getAttempts() + 1;
        $maxAttempts = (int) $this->config->get('eventbus.max_attempts');
        $backoff = min(
            (int) $this->config->get('eventbus.backoff_seconds') * 2 ** ($attempts - 1),
            self::MAX_BACKOFF_SECONDS,
        );

        $message->setAttempts($attempts);
        $message->setLastError($error::class . ': ' . $error->getMessage());
        $message->setNextRetryAt(Carbon::now()->addSeconds($backoff));
        $this->repository->update($message);

        $context = [
            'message_id' => $message->getId()->toString(),
            'direction' => $message->getDirection()->value,
            'type' => $message->getType(),
            'attempts' => $attempts,
            'error' => $message->getLastError(),
        ];

        if ($attempts < $maxAttempts) {
            $this->logger->warning('[EVENTBUS] Message failed, will retry', $context + ['next_retry_at' => $message->getNextRetryAt()?->toIso8601String()]);

            return;
        }

        $this->logger->error('[EVENTBUS] Message dead: attempts exhausted', $context);
        $this->events->dispatch(new MessageDeadEvent(
            messageId: $message->getId(),
            direction: $message->getDirection(),
            type: $message->getType(),
            error: (string) $message->getLastError(),
        ));
    }
}
