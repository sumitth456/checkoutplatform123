<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('campaign_order_bumps');
        Schema::dropIfExists('campaign_downsells');
        Schema::dropIfExists('campaign_upsells');

        Schema::create('campaign_upsells', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('trigger')->default('always');
            $table->json('trigger_config')->nullable();
            $table->string('position')->default('post_checkout');
            $table->string('discount_type')->default('none');
            $table->decimal('discount_value', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['campaign_id', 'is_active', 'priority']);
        });

        Schema::create('campaign_downsells', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('trigger')->default('declined_upsell');
            $table->json('trigger_config')->nullable();
            $table->string('discount_type')->default('none');
            $table->decimal('discount_value', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['campaign_id', 'is_active', 'priority']);
        });

        Schema::create('campaign_order_bumps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('bump_price', 15, 2)->nullable();
            $table->string('position')->default('pre_checkout');
            $table->string('display_rule')->default('always');
            $table->json('display_rule_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['campaign_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_order_bumps');
        Schema::dropIfExists('campaign_downsells');
        Schema::dropIfExists('campaign_upsells');
    }
};
