<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::table('landing_pages', function (Blueprint $table): void {
            $table->boolean('is_tshirt_campaign')->default(false)->after('slug');
            $table->foreignId('product_id')->nullable()->after('campaign_id')->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('external_hosting_provider')->nullable()->after('variant_id');
            $table->string('external_hosting_url')->nullable()->after('external_hosting_provider');
            $table->string('external_hosting_target')->nullable()->after('external_hosting_url');
            $table->string('hosting_status')->nullable()->after('external_hosting_target');

            $table->index('is_tshirt_campaign');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('variant_id');
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn([
                'is_tshirt_campaign',
                'external_hosting_provider',
                'external_hosting_url',
                'external_hosting_target',
                'hosting_status',
            ]);
        });

        Schema::dropIfExists('landing_page_settings');
    }
};
