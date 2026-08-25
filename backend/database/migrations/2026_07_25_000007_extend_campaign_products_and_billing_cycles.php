<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('campaign_product', 'variant_id')) {
            Schema::table('campaign_product', function (Blueprint $table): void {
                $table->foreignId('variant_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
                $table->unsignedInteger('quantity')->default(1)->after('variant_id');
                $table->string('sku_override')->nullable()->after('quantity');
            });
        }

        if (! Schema::hasTable('campaign_product_billing_cycles')) {
            Schema::create('campaign_product_billing_cycles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('campaign_product_id')->constrained('campaign_product')->cascadeOnDelete();
                $table->unsignedSmallInteger('cycle_number');
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
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

                $table->unique(['campaign_product_id', 'cycle_number'], 'cp_bill_cycles_cp_cycle_uq');
                $table->index(['campaign_product_id', 'cycle_number'], 'cp_bill_cycles_cp_cycle_idx');
            });
        } else {
            Schema::table('campaign_product_billing_cycles', function (Blueprint $table): void {
                $table->unique(['campaign_product_id', 'cycle_number'], 'cp_bill_cycles_cp_cycle_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_product_billing_cycles');

        if (Schema::hasColumn('campaign_product', 'variant_id')) {
            Schema::table('campaign_product', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('variant_id');
                $table->dropColumn(['quantity', 'sku_override']);
            });
        }
    }
};
