<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table): void {
            $table->json('builder_json')->nullable()->after('custom_footer_scripts');
        });

        Schema::table('landing_page_versions', function (Blueprint $table): void {
            $table->json('builder_json')->nullable()->after('custom_footer_scripts');
        });

        Schema::table('landing_page_templates', function (Blueprint $table): void {
            $table->json('builder_json')->nullable()->after('content_js');
        });
    }

    public function down(): void
    {
        Schema::table('landing_page_templates', function (Blueprint $table): void {
            $table->dropColumn('builder_json');
        });

        Schema::table('landing_page_versions', function (Blueprint $table): void {
            $table->dropColumn('builder_json');
        });

        Schema::table('landing_pages', function (Blueprint $table): void {
            $table->dropColumn('builder_json');
        });
    }
};
