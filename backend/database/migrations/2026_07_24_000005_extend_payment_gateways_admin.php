<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->unsignedSmallInteger('weight')->default(100)->after('sort_order');
            $table->decimal('daily_limit_amount', 15, 2)->nullable()->after('weight');
            $table->decimal('monthly_limit_amount', 15, 2)->nullable()->after('daily_limit_amount');
            $table->boolean('failover_enabled')->default(true)->after('monthly_limit_amount');
            $table->string('webhook_status')->default('unknown')->after('failover_enabled');
            $table->timestamp('webhook_verified_at')->nullable()->after('webhook_status');
            $table->text('webhook_last_error')->nullable()->after('webhook_verified_at');

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'sort_order']);
            $table->dropColumn([
                'weight',
                'daily_limit_amount',
                'monthly_limit_amount',
                'failover_enabled',
                'webhook_status',
                'webhook_verified_at',
                'webhook_last_error',
            ]);
        });
    }
};
