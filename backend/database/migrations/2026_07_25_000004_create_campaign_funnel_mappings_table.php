<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_funnel_mappings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funnel_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('landing_url')->nullable();
            $table->string('subscribe_funnel_id')->nullable();
            $table->text('api_key_encrypted');
            $table->string('api_key_preview', 32);
            $table->string('status')->default('active');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'subscribe_funnel_id']);
            $table->index(['campaign_id', 'status', 'sort_order']);
            $table->index('subscribe_funnel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_funnel_mappings');
    }
};
