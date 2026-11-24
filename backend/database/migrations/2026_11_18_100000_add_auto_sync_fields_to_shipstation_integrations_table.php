<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipstation_integrations', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipstation_integrations', 'remote_shipment_status')) {
                $table->string('remote_shipment_status')->nullable()->after('label_status');
            }
            if (! Schema::hasColumn('shipstation_integrations', 'remote_shipped_at')) {
                $table->timestamp('remote_shipped_at')->nullable()->after('remote_shipment_status');
            }
            if (! Schema::hasColumn('shipstation_integrations', 'last_auto_sync_at')) {
                $table->timestamp('last_auto_sync_at')->nullable()->after('synced_at');
            }
            if (! Schema::hasColumn('shipstation_integrations', 'last_auto_sync_result')) {
                $table->string('last_auto_sync_result', 64)->nullable()->after('last_auto_sync_at');
            }
            if (! Schema::hasColumn('shipstation_integrations', 'auto_sync_meta')) {
                $table->json('auto_sync_meta')->nullable()->after('last_auto_sync_result');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipstation_integrations', function (Blueprint $table): void {
            foreach ([
                'remote_shipment_status',
                'remote_shipped_at',
                'last_auto_sync_at',
                'last_auto_sync_result',
                'auto_sync_meta',
            ] as $column) {
                if (Schema::hasColumn('shipstation_integrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};