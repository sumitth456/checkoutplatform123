<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_page_templates', function (Blueprint $table): void {
            $table->string('category')->nullable()->after('description');
            $table->text('custom_header_scripts')->nullable()->after('content_js');
            $table->text('custom_footer_scripts')->nullable()->after('custom_header_scripts');
            $table->json('metadata')->nullable()->after('builder_json');
            $table->foreignId('source_landing_page_id')
                ->nullable()
                ->after('metadata')
                ->constrained('landing_pages')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->after('source_landing_page_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('landing_page_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_landing_page_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'category',
                'custom_header_scripts',
                'custom_footer_scripts',
                'metadata',
            ]);
        });
    }
};
