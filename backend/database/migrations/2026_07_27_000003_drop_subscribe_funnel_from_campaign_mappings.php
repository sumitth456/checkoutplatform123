<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_funnel_mappings', function (Blueprint $table): void {
            $table->dropUnique(['campaign_id', 'subscribe_funnel_id']);
            $table->dropIndex(['subscribe_funnel_id']);
            $table->dropColumn('subscribe_funnel_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_funnel_mappings', function (Blueprint $table): void {
            $table->string('subscribe_funnel_id')->nullable()->after('landing_url');
            $table->unique(['campaign_id', 'subscribe_funnel_id']);
            $table->index('subscribe_funnel_id');
        });
    }
};
