<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('provider');
            $table->text('description')->nullable();
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('supports_subscriptions')->default(false);
            $table->boolean('supports_refunds')->default(true);
            $table->boolean('supports_partial_refunds')->default(false);
            $table->boolean('test_mode')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_default']);
            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
