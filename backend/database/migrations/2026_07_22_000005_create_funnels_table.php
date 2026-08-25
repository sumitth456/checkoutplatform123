<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('entry_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('default_payment_gateway_id')->nullable()->constrained('payment_gateways')->nullOnDelete();
            $table->char('currency', 3)->default('USD');
            $table->json('settings')->nullable();
            $table->json('tracking')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('funnel_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('name');
            $table->string('type');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('next_step_id')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['funnel_id', 'step_order']);
            $table->index(['funnel_id', 'type']);
        });

        Schema::table('funnel_steps', function (Blueprint $table) {
            $table->foreign('next_step_id')
                ->references('id')
                ->on('funnel_steps')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_steps');
        Schema::dropIfExists('funnels');
    }
};
