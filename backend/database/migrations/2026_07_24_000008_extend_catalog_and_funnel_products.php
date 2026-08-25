<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('product_brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('short_description')->constrained('product_categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->after('category_id')->constrained('product_brands')->nullOnDelete();
            $table->decimal('cost', 15, 2)->nullable()->after('compare_at_price');
            $table->decimal('weight', 12, 3)->nullable()->after('cost');
        });

        Schema::create('funnel_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('campaign_price', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['funnel_id', 'product_id']);
            $table->index(['funnel_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_product');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['cost', 'weight']);
        });

        Schema::dropIfExists('product_brands');
        Schema::dropIfExists('product_categories');
    }
};
