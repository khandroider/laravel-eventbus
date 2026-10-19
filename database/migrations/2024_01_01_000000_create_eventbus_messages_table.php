<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Одна таблица для исходящих (outbox) и входящих (inbox) сообщений.
 * processed_at: для исходящих — отправлено в брокер, для входящих — обработано.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('eventbus_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('direction', 16);
            $table->string('type');
            $table->string('routing_key')->nullable();
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventbus_messages');
    }
};
