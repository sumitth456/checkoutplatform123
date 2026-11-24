<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipstation_integrations', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipstation_integrations', 'tracking_sync_meta')) {
                $table->json('tracking_sync_meta')->nullable()->after('auto_sync_meta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipstation_integrations', function (Blueprint $table): void {
            if (Schema::hasColumn('shipstation_integrations', 'tracking_sync_meta')) {
                $table->dropColumn('tracking_sync_meta');
            }
        });
    }
};