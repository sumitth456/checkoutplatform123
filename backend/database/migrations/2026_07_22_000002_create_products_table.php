<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('type')->default('digital');
            $table->string('status')->default('draft');
            $table->string('sku')->nullable()->unique();
            $table->char('currency', 3)->default('USD');
            $table->decimal('base_price', 15, 2)->nullable();
            $table->decimal('compare_at_price', 15, 2)->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('requires_shipping')->default(false);
            $table->boolean('is_subscription')->default(false);
            $table->string('billing_interval')->nullable();
            $table->unsignedSmallInteger('billing_interval_count')->nullable();
            $table->unsignedInteger('trial_days')->nullable();
            $table->json('images')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'type']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
