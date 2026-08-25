<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->longText('content_html')->nullable();
            $table->longText('content_css')->nullable();
            $table->longText('content_js')->nullable();
            $table->string('preview_image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('landing_pages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('draft');
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('landing_page_templates')->nullOnDelete();
            $table->string('seo_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image_path')->nullable();
            $table->longText('content_html')->nullable();
            $table->longText('content_css')->nullable();
            $table->longText('content_js')->nullable();
            $table->text('custom_header_scripts')->nullable();
            $table->text('custom_footer_scripts')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('conversion_count')->default(0);
            $table->decimal('revenue_total', 15, 2)->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('last_auto_saved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('campaign_id');
        });

        Schema::create('landing_page_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('label')->nullable();
            $table->longText('content_html')->nullable();
            $table->longText('content_css')->nullable();
            $table->longText('content_js')->nullable();
            $table->text('custom_header_scripts')->nullable();
            $table->text('custom_footer_scripts')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['landing_page_id', 'version_number'], 'lp_versions_page_ver_uq');
        });

        Schema::create('landing_page_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('landing_page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('alt_text')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });

        Schema::create('landing_page_forms', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('form_key')->default('checkout-form');
            $table->json('field_schema')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['landing_page_id', 'form_key'], 'lp_forms_page_key_uq');
        });

        Schema::create('landing_page_view_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referrer')->nullable();
            $table->timestamp('viewed_at');

            $table->index(['landing_page_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_view_events');
        Schema::dropIfExists('landing_page_forms');
        Schema::dropIfExists('landing_page_assets');
        Schema::dropIfExists('landing_page_versions');
        Schema::dropIfExists('landing_pages');
        Schema::dropIfExists('landing_page_templates');
    }
};