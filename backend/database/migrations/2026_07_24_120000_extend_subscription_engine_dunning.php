<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dunning_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('stages');
            $table->timestamps();
        });

        Schema::table('subscription_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscription_plans', 'dunning_policy_id')) {
                $table->foreignId('dunning_policy_id')
                    ->nullable()
                    ->after('retry_policy_id')
                    ->constrained('dunning_policies')
                    ->nullOnDelete();
            }
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscriptions', 'dunning_stage')) {
                $table->unsignedSmallInteger('dunning_stage')->default(0)->after('retry_count');
            }

            if (! Schema::hasColumn('subscriptions', 'next_dunning_at')) {
                $table->timestamp('next_dunning_at')->nullable()->after('next_billing_at');
            }
        });

        Schema::create('subscription_dunning_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('stage')->default(0);
            $table->string('channel')->nullable();
            $table->string('template')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_dunning_events');

        Schema::table('subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('subscriptions', 'next_dunning_at')) {
                $table->dropColumn('next_dunning_at');
            }

            if (Schema::hasColumn('subscriptions', 'dunning_stage')) {
                $table->dropColumn('dunning_stage');
            }
        });

        Schema::table('subscription_plans', function (Blueprint $table): void {
            if (Schema::hasColumn('subscription_plans', 'dunning_policy_id')) {
                $table->dropForeign(['dunning_policy_id']);
                $table->dropColumn('dunning_policy_id');
            }
        });

        Schema::dropIfExists('dunning_policies');
    }
};
