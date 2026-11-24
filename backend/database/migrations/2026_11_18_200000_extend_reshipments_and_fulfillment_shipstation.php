<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reship_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('reship_requests', 'reference')) {
                $table->string('reference')->nullable()->unique()->after('uuid');
            }
        });

        if (! Schema::hasTable('reship_request_items')) {
            Schema::create('reship_request_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('reship_request_id')->constrained('reship_requests')->cascadeOnDelete();
                $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
                $table->unsignedInteger('quantity');
                $table->timestamps();

                $table->unique(['reship_request_id', 'order_item_id'], 'reship_req_items_req_item_uq');
                $table->index('order_item_id', 'reship_req_items_order_item_idx');
            });
        }

        if (
            Schema::hasTable('fulfillment_shipstation_integrations')
            && (int) DB::table('fulfillment_shipstation_integrations')->count() === 0
        ) {
            Schema::drop('fulfillment_shipstation_integrations');
        }

        if (! Schema::hasTable('fulfillment_shipstation_integrations')) {
            Schema::create('fulfillment_shipstation_integrations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('fulfillment_id')->constrained('fulfillments')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('reship_request_id')->nullable()->constrained('reship_requests')->nullOnDelete();
                $table->string('provider')->default('shipstation');
                $table->string('external_reference');
                $table->string('external_shipment_id')->nullable();
                $table->string('external_label_id')->nullable();
                $table->string('tracking_number')->nullable();
                $table->string('carrier')->nullable();
                $table->string('service_code')->nullable();
                $table->string('remote_shipment_status')->nullable();
                $table->timestamp('remote_shipped_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique('external_reference', 'fulfillment_ss_ext_ref_uq');
                $table->unique('external_shipment_id', 'fulfillment_ss_ext_ship_uq');
                $table->unique(['fulfillment_id', 'provider'], 'fulfillment_ss_fulfillment_prov_uq');
                $table->index(['order_id', 'provider'], 'fulfillment_ss_order_provider_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_shipstation_integrations');
        Schema::dropIfExists('reship_request_items');

        Schema::table('reship_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('reship_requests', 'reference')) {
                $table->dropUnique(['reference']);
                $table->dropColumn('reference');
            }
        });
    }
};