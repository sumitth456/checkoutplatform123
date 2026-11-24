<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('shipstation_order_id')->nullable()->after('metadata');
            $table->string('shipstation_status')->nullable()->after('shipstation_order_id');
            $table->timestamp('shipstation_synced_at')->nullable()->after('shipstation_status');
            $table->text('shipstation_sync_error')->nullable()->after('shipstation_synced_at');

            $table->index('shipstation_order_id');
            $table->index('shipstation_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['shipstation_order_id']);
            $table->dropIndex(['shipstation_status']);
            $table->dropColumn([
                'shipstation_order_id',
                'shipstation_status',
                'shipstation_synced_at',
                'shipstation_sync_error',
            ]);
        });
    }
};
