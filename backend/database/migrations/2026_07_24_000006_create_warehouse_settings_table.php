<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('base_url');
            $table->text('api_key')->nullable();
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->string('create_shipment_path')->default('/v1/shipments');
            $table->boolean('is_active')->default(true);
            $table->string('connection_status')->default('unknown');
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_test_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_settings');
    }
};
