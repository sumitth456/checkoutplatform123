<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipstation_integrations', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipstation_integrations', 'external_label_id')) {
                $table->string('external_label_id')->nullable()->after('external_shipment_id');
            }

            if (! Schema::hasColumn('shipstation_integrations', 'carrier_id')) {
                $table->string('carrier_id')->nullable()->after('carrier');
            }

            if (! Schema::hasColumn('shipstation_integrations', 'service_code')) {
                $table->string('service_code')->nullable()->after('service');
            }

            if (! Schema::hasColumn('shipstation_integrations', 'label_url')) {
                $table->text('label_url')->nullable()->after('label_status');
            }

            if (! Schema::hasColumn('shipstation_integrations', 'shipment_cost')) {
                $table->decimal('shipment_cost', 10, 2)->nullable()->after('label_url');
            }

            if (! Schema::hasColumn('shipstation_integrations', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('shipment_cost');
            }

            if (! Schema::hasColumn('shipstation_integrations', 'last_api_endpoint')) {
                $table->string('last_api_endpoint')->nullable()->after('synced_at');
            }

            if (! Schema::hasColumn('shipstation_integrations', 'last_api_method')) {
                $table->string('last_api_method', 10)->nullable()->after('last_api_endpoint');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipstation_integrations', function (Blueprint $table): void {
            foreach ([
                'external_label_id',
                'carrier_id',
                'service_code',
                'label_url',
                'shipment_cost',
                'synced_at',
                'last_api_endpoint',
                'last_api_method',
            ] as $column) {
                if (Schema::hasColumn('shipstation_integrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
