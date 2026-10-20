# khandroider/laravel-eventbus

Transactional outbox + idempotent inbox over RabbitMQ for Laravel 11+.
Built on top of [`vladimir-yuldashev/laravel-queue-rabbitmq`](https://github.com/vyuldashev/laravel-queue-rabbitmq),
so the same connection also serves ordinary Laravel queue jobs.

Mark an event with `PublishableEvent` and it is written to the database in the same transaction as your data,
then delivered to RabbitMQ by a relay process. Incoming messages are deduplicated by id, retried with exponential
backoff, and turned into ordinary Laravel events.

## Install

```bash
composer require khandroider/laravel-eventbus
php artisan vendor:publish --tag=eventbus-config
php artisan vendor:publish --tag=eventbus-migrations
php artisan migrate
```

Add the RabbitMQ connection to `config/queue.php` and point the default queue at it:

```php
'default' => env('QUEUE_CONNECTION', 'rabbitmq'),

'connections' => [
    'rabbitmq' => [
        'driver' => 'rabbitmq',
        'hosts' => [[
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
        ]],
        'options' => ['queue' => []],
        'after_commit' => true,
    ],
],
```

Run the two long-lived processes next to your queue workers (supervisor / docker `restart: unless-stopped`):

```bash
php artisan eventbus:relay     # outbox table -> RabbitMQ
php artisan eventbus:consume   # RabbitMQ -> inbox table -> event()
```

And schedule the retry pass in `routes/console.php`:

```php
Schedule::command('eventbus:retry-inbox')->everyMinute()->withoutOverlapping();
```

## Publish an event

```php
use Khandroider\EventBus\Contracts\PublishableEvent;

final readonly class CandidateCreatedEvent implements PublishableEvent
{
    public function __construct(
        public string $candidateId,
        public Carbon $createdAt,
        public CandidateSourceEnum $source,
    ) {}

    public function getEventName(): string
    {
        return 'candidate_created';
    }
}

// anywhere, preferably inside DB::transaction():
event(new CandidateCreatedEvent($id, now(), CandidateSourceEnum::Site));
```

Nothing else. With `EVENTBUS_SOURCE=hr` (the project / channel name, defaults to `APP_NAME`) the event is stored
as an outbound message and published as

```
exchange:    events                 (topic, durable)
routing key: hr.candidate_created   ({source}.{type})
body:        {"version":1,"id":"...","type":"candidate_created","source":"hr",
              "occurred_at":"2026-10-19T10:00:00.000000Z",
              "payload":{"candidateId":"...","createdAt":"2026-10-19T10:00:00.000000Z","source":"site"}}
```

`type` is whatever `getEventName()` returns (snake_case, no dots). Public properties become `payload`: dates as
ISO-8601 UTC, enums as values, UUIDs as strings, nested objects recursively. Eloquent models are rejected: put ids
in events.

## Receive events

```php
// config/eventbus.php
'inbox' => [
    'queue' => 'hr.inbox',
    'bindings' => ['events' => ['crm.#', 'billing.invoice_*']],
    'events' => [
        'deal_won' => \App\Modules\Crm\Events\DealWonEvent::class,
    ],
],
```

`eventbus:consume` stores each message in `eventbus_messages` (direction `inbound`; a duplicate id is ignored),
builds the mapped event class from `payload` via its constructor (named arguments, types cast back from JSON)
and fires it with `event()`. Your normal listeners take it from there. An unmapped `type` is marked processed with
`last_error = unmapped type` and logged as a warning.

## Failures and monitoring

* A failed publish or handling bumps `attempts`, stores `last_error` and sets `next_retry_at`
  (`backoff_seconds * 2^(attempts-1)`, capped at one day).
* After `max_attempts` the message is **dead**: `[EVENTBUS] Message dead` is logged at `error` level and
  `Khandroider\EventBus\Events\MessageDeadEvent` is fired for your own alerting.
* `php artisan eventbus:status` prints pending / dead counts and the age of the oldest pending message per direction
  and exits with code 1 when something is dead or the lag exceeds `max_lag_seconds`. Use it as a healthcheck.
* Broker outages are logged as `error` at most once a minute by the relay; it keeps retrying.

## Switching jobs from the database queue

```bash
# stop workers, set QUEUE_CONNECTION=rabbitmq, then:
php artisan eventbus:migrate-jobs --from=database --to=rabbitmq
# start workers
```

Pending rows of the `jobs` table are pushed to the new connection (delays preserved) and deleted.
Reserved rows (a worker is on them) are left alone and counted in the output.

## License

MIT
