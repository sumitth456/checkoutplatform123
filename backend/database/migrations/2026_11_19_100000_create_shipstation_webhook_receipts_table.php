<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipstation_webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('delivery_fingerprint', 64)->index();
            $table->string('resource_type', 64)->nullable();
            $table->text('resource_url')->nullable();
            $table->string('scope_type', 32)->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fulfillment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('received');
            $table->string('outbox_status', 32)->default('pending');
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('ignore_reason')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('payload_meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'ss_wh_rcpt_status_created_idx');
            $table->index(['scope_type', 'scope_id', 'created_at'], 'ss_wh_rcpt_scope_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipstation_webhook_receipts');
    }
};