<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipstation_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->default('ShipStation');
            $table->string('base_url')->default('https://ssapi.shipstation.com');
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->default(30);
            $table->boolean('is_active')->default(false);
            $table->string('connection_status')->default('unknown');
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_test_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipstation_settings');
    }
};
