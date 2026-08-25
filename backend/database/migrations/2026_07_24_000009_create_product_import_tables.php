<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('format');
            $table->string('status')->default('pending');
            $table->json('headers')->nullable();
            $table->json('column_mapping')->nullable();
            $table->json('preview_rows')->nullable();
            $table->json('stats')->nullable();
            $table->foreignId('target_funnel_id')->nullable()->constrained('funnels')->nullOnDelete();
            $table->text('failure_message')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('product_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('sku')->nullable();
            $table->string('field')->nullable();
            $table->string('code');
            $table->text('message');
            $table->json('row_data')->nullable();
            $table->timestamps();

            $table->index(['product_import_batch_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_errors');
        Schema::dropIfExists('product_import_batches');
    }
};
