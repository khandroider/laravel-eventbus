<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Collection;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

/**
 * Одноразовый перенос незавершённых задач из очереди на базе данных (таблица jobs) в другое соединение.
 * Порядок на проде: остановить воркеры → сменить QUEUE_CONNECTION → эта команда → запустить воркеры.
 */
final class MigrateJobsCommand extends Command
{
    protected $signature = 'eventbus:migrate-jobs
        {--from=database : Соединение-источник с драйвером database}
        {--to= : Соединение-приёмник, по умолчанию queue.default}';

    protected $description = 'Переносит незавершённые задачи из database-очереди в текущее соединение';

    private const int CHUNK = 100;
    private const string DEFAULT_TABLE = 'jobs';

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly QueueManager $queue,
        private readonly Repository $config,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $to = (string) ($this->option('to') ?: $this->config->get('queue.default'));

        if ($this->config->get("queue.connections.$from.driver") !== 'database') {
            $this->error(sprintf('Connection "%s" is not a database queue.', $from));

            return self::FAILURE;
        }

        if ($from === $to) {
            $this->error('Source and target connections must differ.');

            return self::FAILURE;
        }

        $table = (string) $this->config->get("queue.connections.$from.table", self::DEFAULT_TABLE);
        $connection = $this->db->connection($this->config->get("queue.connections.$from.connection"));
        $target = $this->queue->connection($to);

        $reserved = $connection->table($table)->whereNotNull('reserved_at')->count();
        $moved = 0;

        $connection->table($table)
            ->whereNull('reserved_at')
            ->orderBy('id')
            ->chunkById(self::CHUNK, function (Collection $rows) use ($connection, $table, $target, &$moved): void {
                foreach ($rows as $row) {
                    $delay = max(0, (int) $row->available_at - time());

                    if ($delay > 0 && $target instanceof RabbitMQQueue) {
                        $target->laterRaw($delay, (string) $row->payload, (string) $row->queue);
                    } else {
                        $target->pushRaw((string) $row->payload, (string) $row->queue, ['delay' => $delay]);
                    }

                    $connection->table($table)->where('id', $row->id)->delete();
                    $moved++;
                }
            });

        $this->info(sprintf('Moved %d job(s) from "%s" to "%s".', $moved, $from, $to));

        if ($reserved > 0) {
            $this->warn(sprintf('%d reserved job(s) left in "%s": a worker is still processing them.', $reserved, $from));
        }

        return self::SUCCESS;
    }
}
