<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_gateway_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('parent_transaction_id')->nullable();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->string('external_id')->nullable();
            $table->string('external_status')->nullable();
            $table->char('currency', 3);
            $table->decimal('amount', 15, 2);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->nullable();
            $table->string('payment_method_type')->nullable();
            $table->string('payment_method_last4', 4)->nullable();
            $table->string('payment_method_brand')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'type']);
            $table->index(['customer_id', 'status']);
            $table->index(['payment_gateway_id', 'external_id']);
            $table->index(['status', 'processed_at']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('parent_transaction_id')
                ->references('id')
                ->on('transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
