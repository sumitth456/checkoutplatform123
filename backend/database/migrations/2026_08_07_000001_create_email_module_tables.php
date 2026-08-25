<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_providers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('provider_type');
            $table->text('api_key')->nullable();
            $table->text('secret_key')->nullable();
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_encryption')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_name');
            $table->string('from_email');
            $table->string('reply_to_email')->nullable();
            $table->string('reply_to_name')->nullable();
            $table->string('status')->default('disabled');
            $table->unsignedTinyInteger('priority')->default(100);
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedInteger('sent_today')->default(0);
            $table->date('sent_today_date')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
        });

        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('trigger_event')->nullable();
            $table->string('subject');
            $table->longText('html_body');
            $table->longText('plain_text_body')->nullable();
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('email_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('email_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('subject');
            $table->longText('html_body');
            $table->longText('plain_text_body')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['email_template_id', 'version']);
        });

        Schema::create('email_automations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('trigger_event');
            $table->foreignId('email_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->json('conditions')->nullable();
            $table->unsignedTinyInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['trigger_event', 'is_active']);
        });

        Schema::create('email_queue_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_automation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('subject');
            $table->longText('html_body');
            $table->longText('plain_text_body')->nullable();
            $table->json('metadata')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->nullableMorphs('context');
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('email_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('email_queue_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('subject');
            $table->string('status');
            $table->longText('html_body')->nullable();
            $table->longText('raw_email')->nullable();
            $table->json('provider_response')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('spam_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->nullableMorphs('context');
            $table->timestamps();

            $table->index(['recipient_email', 'status']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_queue_items');
        Schema::dropIfExists('email_automations');
        Schema::dropIfExists('email_template_versions');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_providers');
    }
};
