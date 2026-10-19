<?php

declare(strict_types=1);

namespace Khandroider\EventBus\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Khandroider\EventBus\Contracts\Queries\MessageQuery;
use Khandroider\EventBus\Enums\MessageDirectionEnum;

/**
 * Здоровье шины: код выхода 1, если есть «мёртвые» сообщения или самое старое ждёт дольше max_lag_seconds.
 */
final class StatusCommand extends Command
{
    protected $signature = 'eventbus:status';

    protected $description = 'Показывает очередь исходящих/входящих сообщений и возвращает 1 при проблемах';

    public function __construct(
        private readonly MessageQuery $query,
        private readonly Repository $config,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $maxLag = (int) $this->config->get('eventbus.max_lag_seconds');
        $rows = [];
        $healthy = true;

        foreach (MessageDirectionEnum::cases() as $direction) {
            $pending = $this->query->countPending($direction);
            $dead = $this->query->countDead($direction);
            $oldest = $this->query->oldestPendingAt($direction);
            $lag = $oldest === null ? 0 : (int) $oldest->diffInSeconds(Carbon::now());

            $rows[] = [$direction->value, $pending, $dead, $lag];

            if ($dead > 0 || $lag > $maxLag) {
                $healthy = false;
            }
        }

        $this->table(['direction', 'pending', 'dead', 'oldest pending, sec'], $rows);

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
