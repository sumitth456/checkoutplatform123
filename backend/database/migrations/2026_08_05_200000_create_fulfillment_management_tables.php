<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_houses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('provider_type')->default('internal');
            $table->string('status')->default('active');
            $table->unsignedInteger('priority')->default(100);
            $table->string('api_type')->nullable();
            $table->string('api_url')->nullable();
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->string('api_username')->nullable();
            $table->string('api_password')->nullable();
            $table->string('webhook_url')->nullable();
            $table->json('supported_countries')->nullable();
            $table->json('supported_shipping_methods')->nullable();
            $table->boolean('auto_sync')->default(true);
            $table->boolean('retry_failed_requests')->default(true);
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('api_endpoint')->nullable();
            $table->json('settings')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fulfillment_house_id')->nullable()->constrained('fulfillment_houses')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('country_code', 2)->default('US');
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('timezone')->default('UTC');
            $table->unsignedInteger('priority')->default(100);
            $table->json('supported_shipping_methods')->nullable();
            $table->boolean('inventory_sync')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shipping_carriers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('tracking_url_template')->nullable();
            $table->json('services')->nullable();
            $table->unsignedInteger('estimated_delivery_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shipping_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->unsignedInteger('priority')->default(100);
            $table->string('country_code', 2)->nullable();
            $table->string('state_code', 10)->nullable();
            $table->decimal('min_weight', 10, 3)->nullable();
            $table->decimal('max_weight', 10, 3)->nullable();
            $table->decimal('min_order_value', 12, 2)->nullable();
            $table->decimal('max_order_value', 12, 2)->nullable();
            $table->string('product_type')->nullable();
            $table->boolean('subscription_only')->default(false);
            $table->foreignId('platform_campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('fulfillment_house_id')->nullable()->constrained('fulfillment_houses')->nullOnDelete();
            $table->foreignId('shipping_carrier_id')->nullable()->constrained('shipping_carriers')->nullOnDelete();
            $table->string('shipping_method')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fulfillment_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('status')->default('pending');
            $table->string('batch_type')->default('export');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('fulfillment_house_id')->nullable()->constrained('fulfillment_houses')->nullOnDelete();
            $table->unsignedInteger('fulfillment_count')->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fulfillment_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::table('fulfillments', function (Blueprint $table): void {
            $table->string('fulfillment_number')->nullable()->unique()->after('uuid');
            $table->foreignId('customer_id')->nullable()->after('order_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->after('customer_id')->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('fulfillment_house_id')->nullable()->after('subscription_id')->constrained('fulfillment_houses')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('fulfillment_house_id')->constrained('warehouses')->nullOnDelete();
            $table->foreignId('shipping_carrier_id')->nullable()->after('warehouse_id')->constrained('shipping_carriers')->nullOnDelete();
            $table->foreignId('shipping_rule_id')->nullable()->after('shipping_carrier_id')->constrained('shipping_rules')->nullOnDelete();
            $table->foreignId('fulfillment_batch_id')->nullable()->after('shipping_rule_id')->constrained('fulfillment_batches')->nullOnDelete();
            $table->foreignId('parent_fulfillment_id')->nullable()->after('fulfillment_batch_id')->constrained('fulfillments')->nullOnDelete();
            $table->string('shipping_method')->nullable()->after('carrier');
            $table->string('tracking_url')->nullable()->after('tracking_number');
            $table->timestamp('queued_at')->nullable()->after('scheduled_ship_date');
            $table->timestamp('on_hold_at')->nullable()->after('queued_at');
            $table->timestamp('packed_at')->nullable()->after('on_hold_at');
            $table->timestamp('processing_at')->nullable()->after('packed_at');
            $table->timestamp('ready_to_ship_at')->nullable()->after('processing_at');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->timestamp('returned_at')->nullable()->after('delivered_at');
            $table->timestamp('cancelled_at')->nullable()->after('returned_at');
            $table->timestamp('failed_at')->nullable()->after('cancelled_at');
            $table->timestamp('estimated_delivery_at')->nullable()->after('failed_at');
            $table->string('rma_number')->nullable()->after('estimated_delivery_at');
            $table->text('notes')->nullable()->after('failure_reason');
            $table->json('metadata')->nullable()->after('notes');
        });

        Schema::create('fulfillment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fulfillment_id')->constrained('fulfillments')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('variants')->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('quantity_shipped')->default(0);
            $table->unsignedInteger('quantity_returned')->default(0);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('fulfillment_timeline_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fulfillment_id')->constrained('fulfillments')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status_label')->nullable();
            $table->string('actor_type')->nullable();
            $table->string('actor_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['fulfillment_id', 'occurred_at']);
        });

        Schema::create('warehouse_inventories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('variants')->cascadeOnDelete();
            $table->integer('available')->default(0);
            $table->integer('reserved')->default(0);
            $table->integer('committed')->default(0);
            $table->integer('returned')->default(0);
            $table->integer('damaged')->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'variant_id']);
        });

        Schema::create('rmas', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('rma_number')->unique();
            $table->foreignId('fulfillment_id')->constrained('fulfillments')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('status')->default('requested');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('restock_inventory')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reship_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fulfillment_id')->constrained('fulfillments')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('new_fulfillment_id')->nullable()->constrained('fulfillments')->nullOnDelete();
            $table->string('status')->default('requested');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reship_requests');
        Schema::dropIfExists('rmas');
        Schema::dropIfExists('warehouse_inventories');
        Schema::dropIfExists('fulfillment_timeline_events');
        Schema::dropIfExists('fulfillment_items');

        Schema::table('fulfillments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_fulfillment_id');
            $table->dropConstrainedForeignId('fulfillment_batch_id');
            $table->dropConstrainedForeignId('shipping_rule_id');
            $table->dropConstrainedForeignId('shipping_carrier_id');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropConstrainedForeignId('fulfillment_house_id');
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn([
                'fulfillment_number', 'shipping_method', 'tracking_url',
                'queued_at', 'on_hold_at', 'packed_at', 'processing_at', 'ready_to_ship_at',
                'delivered_at', 'returned_at', 'cancelled_at', 'failed_at',
                'estimated_delivery_at', 'rma_number', 'notes', 'metadata',
            ]);
        });

        Schema::dropIfExists('fulfillment_settings');
        Schema::dropIfExists('fulfillment_batches');
        Schema::dropIfExists('shipping_rules');
        Schema::dropIfExists('shipping_carriers');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('fulfillment_houses');
    }
};
