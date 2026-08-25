<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('delay_days')->default(0);
            $table->date('scheduled_ship_date');
            $table->timestamp('shipped_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('warehouse_reference')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->string('shipment_status')->nullable();
            $table->string('delivery_status')->nullable();
            $table->json('warehouse_request')->nullable();
            $table->json('warehouse_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_ship_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillments');
    }
};
