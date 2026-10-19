<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Repositories\RabbitMQ;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Queue\QueueManager;
use Khandroider\EventBus\Contracts\Repositories\BrokerRepository;
use Khandroider\EventBus\Models\Message;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

/**
 * Канал берём у соединения из config/queue.php (драйвер rabbitmq), поэтому адрес брокера настраивается один раз.
 */
final class RabbitMQBrokerRepository implements BrokerRepository
{
    private const string EXCHANGE_TYPE = 'topic';

    private const string CONTENT_TYPE = 'application/json';

    private const int ENVELOPE_VERSION = 1;

    private const int PREFETCH_COUNT = 10;

    private const float CONFIRM_TIMEOUT_SECONDS = 5.0;

    private const int REQUEUE_PAUSE_SECONDS = 1;

    private ?AMQPChannel $channel = null;

    public function __construct(
        private readonly QueueManager $queue,
        private readonly Repository $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function publish(Message $message): void
    {
        $exchange = (string) $this->config->get('eventbus.exchange');

        try {
            $channel = $this->channel();
            $channel->basic_publish($this->toAmqpMessage($message), $exchange, (string) $message->getRoutingKey());
            $channel->wait_for_pending_acks(self::CONFIRM_TIMEOUT_SECONDS);
        } catch (AMQPExceptionInterface $e) {
            // Канал после ошибки протокола непригоден: следующий вызов откроет новый.
            $this->channel = null;
            throw $e;
        }

        $this->logger->info('[EVENTBUS] Published', [
            'message_id' => $message->getId()->toString(),
            'type' => $message->getType(),
            'routing_key' => $message->getRoutingKey(),
        ]);
    }

    public function consume(Closure $onMessage): void
    {
        $channel = $this->channel();
        $queue = (string) $this->config->get('eventbus.inbox.queue');

        $channel->queue_declare($queue, false, true, false, false);

        /** @var array<string, array<int, string>> $bindings */
        $bindings = $this->config->get('eventbus.inbox.bindings', []);

        foreach ($bindings as $exchange => $routingKeys) {
            $channel->exchange_declare($exchange, self::EXCHANGE_TYPE, false, true, false);

            foreach ($routingKeys as $routingKey) {
                $channel->queue_bind($queue, $exchange, $routingKey);
            }
        }

        $channel->basic_qos(0, self::PREFETCH_COUNT, false);
        $channel->basic_consume($queue, '', false, false, false, false, function (AMQPMessage $amqpMessage) use ($onMessage): void {
            try {
                $onMessage($amqpMessage->getBody());
                $amqpMessage->ack();
            } catch (Throwable $e) {
                // Не смогли даже записать во входящий ящик (например, база недоступна): вернём в очередь и переждём.
                $this->logger->error('[EVENTBUS] Inbound message could not be accepted, requeued', [
                    'error' => $e->getMessage(),
                ]);
                $amqpMessage->nack(true);
                sleep(self::REQUEUE_PAUSE_SECONDS);
            }
        });

        $this->logger->info('[EVENTBUS] Consuming', ['queue' => $queue, 'bindings' => $bindings]);

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    private function toAmqpMessage(Message $message): AMQPMessage
    {
        $body = json_encode([
            'version' => self::ENVELOPE_VERSION,
            'id' => $message->getId()->toString(),
            'type' => $message->getType(),
            'source' => $this->config->get('eventbus.source'),
            'occurred_at' => $message->getOccurredAt()->toIso8601ZuluString('microsecond'),
            'payload' => $message->getPayload(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return new AMQPMessage($body, [
            'content_type' => self::CONTENT_TYPE,
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $message->getId()->toString(),
            'type' => $message->getType(),
            'app_id' => (string) $this->config->get('eventbus.source'),
            'timestamp' => $message->getOccurredAt()->getTimestamp(),
        ]);
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel !== null && $this->channel->is_open()) {
            return $this->channel;
        }

        $connection = $this->queue->connection((string) $this->config->get('eventbus.connection'));

        if (! $connection instanceof RabbitMQQueue) {
            throw new RuntimeException(sprintf(
                'Queue connection "%s" must use the rabbitmq driver (config eventbus.connection).',
                $this->config->get('eventbus.connection'),
            ));
        }

        $channel = $connection->getChannel(true);
        $channel->exchange_declare((string) $this->config->get('eventbus.exchange'), self::EXCHANGE_TYPE, false, true, false);
        $channel->confirm_select();
        $channel->set_nack_handler(static function (AMQPMessage $amqpMessage): void {
            throw new RuntimeException('Broker rejected message '.($amqpMessage->get('message_id') ?? ''));
        });

        return $this->channel = $channel;
    }
}
