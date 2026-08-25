<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->foreignId('payment_gateway_id')
                ->nullable()
                ->after('dunning_policy_id')
                ->constrained('payment_gateways')
                ->nullOnDelete();
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->foreignId('subscription_plan_id')
                ->nullable()
                ->after('brand_id')
                ->constrained('subscription_plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subscription_plan_id');
        });

        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_gateway_id');
        });
    }
};
