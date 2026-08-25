<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->string('operation');
            $table->string('request_hash');
            $table->json('response_payload');
            $table->string('status');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
        });

        Schema::create('payment_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('entry_type');
            $table->string('status_from')->nullable();
            $table->string('status_to');
            $table->decimal('amount', 15, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('external_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->json('payload');
            $table->timestamp('recorded_at');

            $table->index(['provider', 'external_id']);
            $table->index(['entry_type', 'recorded_at']);
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_ledger_entries');
        Schema::dropIfExists('payment_idempotency_keys');
    }
};
