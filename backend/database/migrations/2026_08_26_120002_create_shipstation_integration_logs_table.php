<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipstation_integration_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('request_type');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fulfillment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('shipstation_order_id')->nullable();
            $table->unsignedBigInteger('shipstation_shipment_id')->nullable();
            $table->boolean('success')->default(false);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['request_type', 'created_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipstation_integration_logs');
    }
};
