<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_name')->nullable();
            $table->string('support_email')->nullable();
            $table->char('default_currency', 3)->default('USD');
            $table->string('timezone')->default('UTC');
            $table->string('payment_default_provider')->nullable();
            $table->unsignedInteger('payment_idempotency_ttl_minutes')->nullable();
            $table->unsignedInteger('payment_retry_attempts')->nullable();
            $table->unsignedInteger('payment_retry_delay_ms')->nullable();
            $table->unsignedInteger('payment_timeout_seconds')->nullable();
            $table->unsignedInteger('payment_circuit_failure_threshold')->nullable();
            $table->unsignedInteger('payment_circuit_recovery_seconds')->nullable();
            $table->unsignedInteger('fulfillment_default_delay_days')->default(0);
            $table->unsignedInteger('subscription_charge_lock_seconds')->default(30);
            $table->string('checkout_queue')->default('default');
            $table->string('checkout_fulfillment_queue')->default('default');
            $table->string('fulfillment_queue')->default('default');
            $table->string('subscription_billing_queue')->default('default');
            $table->string('subscription_dunning_queue')->default('default');
            $table->string('subscription_webhook_queue')->default('default');
            $table->boolean('checkout_require_matching_billing')->default(false);
            $table->boolean('maintenance_mode_checkout')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
