<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ach_providers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('provider');
            $table->string('status')->default('enabled');
            $table->boolean('sandbox_mode')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('priority')->default(100);
            $table->text('api_key')->nullable();
            $table->text('secret_key')->nullable();
            $table->string('merchant_id')->nullable();
            $table->json('settings')->nullable();
            $table->decimal('daily_limit', 12, 2)->nullable();
            $table->decimal('daily_volume', 12, 2)->default(0);
            $table->date('daily_volume_date')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ach_provider_id')->nullable()->constrained('ach_providers')->nullOnDelete();
            $table->string('payment_method', 32);
            $table->string('payment_token');
            $table->string('provider')->nullable();
            $table->string('account_type', 32)->nullable();
            $table->string('holder_type', 32)->nullable();
            $table->string('last4', 4)->nullable();
            $table->string('bank_name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['customer_id', 'payment_token']);
            $table->index(['customer_id', 'payment_method']);
        });

        Schema::create('ach_payment_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ach_provider_id')->nullable()->constrained('ach_providers')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->string('status', 32);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamps();

            $table->index(['action', 'status']);
            $table->index('created_at');
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'payment_method_type')) {
                $table->string('payment_method_type', 32)->nullable()->after('payment_gateway_id');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscriptions', 'payment_method_type')) {
                $table->string('payment_method_type', 32)->nullable()->after('payment_provider');
            }
        });

        Schema::table('transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('transactions', 'authorization_id')) {
                $table->string('authorization_id')->nullable()->after('external_status');
            }
            if (! Schema::hasColumn('transactions', 'refund_id')) {
                $table->string('refund_id')->nullable()->after('authorization_id');
            }
            if (! Schema::hasColumn('transactions', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('refund_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn(['authorization_id', 'refund_id', 'refunded_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('payment_method_type');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('payment_method_type');
        });

        Schema::dropIfExists('ach_payment_logs');
        Schema::dropIfExists('customer_payment_methods');
        Schema::dropIfExists('ach_providers');
    }
};
