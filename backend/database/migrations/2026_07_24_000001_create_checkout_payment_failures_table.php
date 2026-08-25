<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_payment_failures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('idempotency_key')->index();
            $table->string('customer_email')->nullable()->index();
            $table->decimal('amount', 15, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('provider')->default('stripe');
            $table->string('failure_code');
            $table->text('failure_message');
            $table->string('payment_intent_id')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('gateway_response')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_payment_failures');
    }
};
