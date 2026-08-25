<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retry_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('max_attempts')->default(3);
            $table->json('retry_offsets');
            $table->timestamps();
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->char('currency', 3)->default('USD');
            $table->foreignId('retry_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_plan_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('billing_type');
            $table->string('cycle_type')->nullable();
            $table->unsignedSmallInteger('days_from_start')->nullable();
            $table->unsignedSmallInteger('interval_days')->nullable();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->nullable();
            $table->timestamps();

            $table->unique(['subscription_plan_id', 'step_order']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable()->after('variant_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('current_cycle_step')->default(1)->after('subscription_plan_id');
            $table->unsignedSmallInteger('retry_count')->default(0)->after('current_cycle_step');
            $table->string('payment_token')->nullable()->after('retry_count');
            $table->string('payment_provider')->nullable()->after('payment_token');
            $table->unsignedInteger('paused_remaining_days')->nullable()->after('payment_provider');
            $table->timestamp('paused_at')->nullable()->after('paused_remaining_days');
            $table->timestamp('started_at')->nullable()->after('paused_at');
        });

        Schema::create('billing_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('cycle_step');
            $table->unsignedSmallInteger('retry_attempt')->default(0);
            $table->string('outcome');
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->string('idempotency_key');
            $table->string('transaction_id')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('attempted_at');

            $table->index(['subscription_id', 'attempted_at']);
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_ledger_entries');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_plan_id');
            $table->dropColumn([
                'current_cycle_step',
                'retry_count',
                'payment_token',
                'payment_provider',
                'paused_remaining_days',
                'paused_at',
                'started_at',
            ]);
        });

        Schema::dropIfExists('subscription_plan_steps');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('retry_policies');
    }
};
