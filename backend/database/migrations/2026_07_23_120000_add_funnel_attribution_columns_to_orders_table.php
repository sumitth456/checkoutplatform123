<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('campaign_id')->nullable()->after('funnel_step_id');
            $table->char('country_code', 2)->nullable()->after('currency');
            $table->string('device_type', 32)->nullable()->after('user_agent');
            $table->text('landing_page_url')->nullable()->after('device_type');
            $table->string('landing_page_id')->nullable()->after('landing_page_url');
            $table->text('referrer_url')->nullable()->after('landing_page_id');
            $table->json('utm_parameters')->nullable()->after('referrer_url');
            $table->json('landing_page_data')->nullable()->after('utm_parameters');

            $table->index('campaign_id');
            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['campaign_id']);
            $table->dropIndex(['country_code']);
            $table->dropColumn([
                'campaign_id',
                'country_code',
                'device_type',
                'landing_page_url',
                'landing_page_id',
                'referrer_url',
                'utm_parameters',
                'landing_page_data',
            ]);
        });
    }
};
