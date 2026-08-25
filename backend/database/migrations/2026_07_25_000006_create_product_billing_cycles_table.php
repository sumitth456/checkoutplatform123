<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_billing_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('cycle_number');
            $table->foreignId('bill_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('shipping_price', 15, 2)->nullable();
            $table->unsignedSmallInteger('bill_delay_days')->default(0);
            $table->boolean('delay_fulfillment')->default(false);
            $table->boolean('shippable')->default(true);
            $table->string('warehouse')->nullable();
            $table->boolean('subscription_enabled')->default(false);
            $table->boolean('repeat_forever')->default(false);
            $table->string('inventory_rule')->nullable();
            $table->unsignedInteger('inventory_quantity')->nullable();
            $table->json('coupon_override')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'cycle_number'], 'product_bill_cycles_prod_cycle_uq');
            $table->index(['product_id', 'cycle_number'], 'product_bill_cycles_prod_cycle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_billing_cycles');
    }
};
