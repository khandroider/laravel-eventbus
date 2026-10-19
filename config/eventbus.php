<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [
    // Имя этого приложения в сообщениях (поле `source` и первый сегмент ключа маршрутизации).
    'source' => env('EVENTBUS_SOURCE', Str::snake((string) env('APP_NAME', 'laravel'))),

    // Соединение из config/queue.php с драйвером rabbitmq, у которого берём канал.
    'connection' => env('EVENTBUS_QUEUE_CONNECTION', 'rabbitmq'),

    // Точка обмена (topic, durable), куда уходят исходящие события.
    'exchange' => env('EVENTBUS_EXCHANGE', 'events'),

    // Сколько строк берёт реле / повтор за один проход.
    'batch' => 100,

    // После этого числа неудачных попыток сообщение считается «мёртвым»: MessageDeadEvent + error в лог.
    'max_attempts' => 10,

    // Пауза перед повтором: backoff_seconds * 2^(attempts-1), не больше суток.
    'backoff_seconds' => 60,

    // eventbus:status вернёт код 1, если самое старое неотправленное сообщение старше этого (секунды).
    'max_lag_seconds' => 300,

    'inbox' => [
        // Своя очередь этого приложения.
        'queue' => env('EVENTBUS_INBOX_QUEUE', Str::snake((string) env('APP_NAME', 'laravel')) . '.inbox'),

        // Что слушать: точка обмена => ключи маршрутизации. Пусто — ничего не слушаем.
        // 'bindings' => ['events' => ['crm.#', 'billing.invoice.*']],
        'bindings' => [],

        // Входящий `type` => класс события этого приложения, которое будет вызвано через event().
        // 'events' => ['candidate_created' => \App\Events\CandidateCreatedEvent::class],
        'events' => [],
    ],
];
