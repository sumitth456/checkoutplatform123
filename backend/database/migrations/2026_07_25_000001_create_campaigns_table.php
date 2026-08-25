<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('public_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('brand_id')->nullable()->constrained('product_brands')->nullOnDelete();
            $table->string('store_name')->nullable();
            $table->string('store_code')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('timezone')->default('UTC');
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('store_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
