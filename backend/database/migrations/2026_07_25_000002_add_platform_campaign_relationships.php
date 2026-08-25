<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table): void {
            $table->foreignId('platform_campaign_id')
                ->nullable()
                ->after('uuid')
                ->constrained('campaigns')
                ->nullOnDelete();

            $table->index('platform_campaign_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('platform_campaign_id')
                ->nullable()
                ->after('funnel_id')
                ->constrained('campaigns')
                ->nullOnDelete();

            $table->index('platform_campaign_id');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscriptions', 'platform_campaign_id')) {
                $table->foreignId('platform_campaign_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('campaigns')
                    ->nullOnDelete();

                $table->index('platform_campaign_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('subscriptions', 'platform_campaign_id')) {
                $table->dropConstrainedForeignId('platform_campaign_id');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('platform_campaign_id');
        });

        Schema::table('funnels', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('platform_campaign_id');
        });
    }
};
