<?php

declare(strict_types=1);

namespace Khandroider\EventBus;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Khandroider\EventBus\Commands\ProcessInboundMessageCommand;
use Khandroider\EventBus\Commands\ReceiveInboundMessageCommand;
use Khandroider\EventBus\Commands\StoreOutboundMessageCommand;
use Khandroider\EventBus\Console\ConsumeCommand;
use Khandroider\EventBus\Console\MigrateJobsCommand;
use Khandroider\EventBus\Console\RelayCommand;
use Khandroider\EventBus\Console\RetryInboundCommand;
use Khandroider\EventBus\Console\StatusCommand;
use Khandroider\EventBus\Contracts\PublishableEvent;
use Khandroider\EventBus\Contracts\Queries\MessageQuery as MessageQueryContract;
use Khandroider\EventBus\Contracts\Repositories\BrokerRepository;
use Khandroider\EventBus\Contracts\Repositories\StoreMessageRepository;
use Khandroider\EventBus\Contracts\Repositories\UpdateMessageRepository;
use Khandroider\EventBus\Handlers\ProcessInboundMessageHandler;
use Khandroider\EventBus\Handlers\ReceiveInboundMessageHandler;
use Khandroider\EventBus\Handlers\StoreOutboundMessageHandler;
use Khandroider\EventBus\Listeners\StoreInOutboxOnPublishableEventListener;
use Khandroider\EventBus\Queries\Eloquent\MessageQuery;
use Khandroider\EventBus\Repositories\Eloquent\MessageRepository;
use Khandroider\EventBus\Repositories\RabbitMQ\RabbitMQBrokerRepository;

final class EventBusServiceProvider extends ServiceProvider
{
    private const string CONFIG_PATH = __DIR__ . '/../config/eventbus.php';
    private const string MIGRATIONS_PATH = __DIR__ . '/../database/migrations';
    private const string CONFIG_TAG = 'eventbus-config';

    public array $bindings = [
        StoreMessageRepository::class => MessageRepository::class,
        UpdateMessageRepository::class => MessageRepository::class,
        MessageQueryContract::class => MessageQuery::class,
    ];

    public array $singletons = [
        BrokerRepository::class => RabbitMQBrokerRepository::class,
    ];

    private array $commandHandlers = [
        StoreOutboundMessageCommand::class => StoreOutboundMessageHandler::class,
        ReceiveInboundMessageCommand::class => ReceiveInboundMessageHandler::class,
        ProcessInboundMessageCommand::class => ProcessInboundMessageHandler::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'eventbus');
    }

    public function boot(): void
    {
        $this->publishes([self::CONFIG_PATH => config_path('eventbus.php')], self::CONFIG_TAG);
        $this->loadMigrationsFrom(self::MIGRATIONS_PATH);

        Bus::map($this->commandHandlers);
        Event::listen(PublishableEvent::class, StoreInOutboxOnPublishableEventListener::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RelayCommand::class,
                ConsumeCommand::class,
                RetryInboundCommand::class,
                StatusCommand::class,
                MigrateJobsCommand::class,
            ]);
        }
    }
}
