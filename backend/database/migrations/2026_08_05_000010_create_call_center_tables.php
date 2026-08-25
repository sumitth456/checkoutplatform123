<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_centers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('timezone', 64)->default('UTC');
            $table->string('phone_number', 32)->nullable();
            $table->string('extension', 16)->nullable();
            $table->string('country_code', 8)->nullable();
            $table->string('default_language', 16)->default('en');
            $table->json('allowed_campaign_ids')->nullable();
            $table->json('assigned_user_ids')->nullable();
            $table->json('assigned_role_ids')->nullable();
            $table->json('permissions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });

        Schema::create('call_center_agent_groups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('call_center_id')->constrained('call_centers')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('assigned_campaign_ids')->nullable();
            $table->json('permissions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('call_center_agents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('call_center_id')->nullable()->constrained('call_centers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('phone', 32)->nullable();
            $table->string('extension', 16)->nullable();
            $table->string('status', 32)->default('offline');
            $table->json('assigned_campaign_ids')->nullable();
            $table->string('role_label')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_logged_in')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });

        Schema::create('call_center_agent_group_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_group_id')->constrained('call_center_agent_groups')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('call_center_agents')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['agent_group_id', 'agent_id']);
        });

        Schema::create('call_dispositions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code', 64)->unique();
            $table->text('description')->nullable();
            $table->string('color', 32)->default('gray');
            $table->boolean('is_active')->default(true);
            $table->boolean('counts_as_sale')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('call_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('call_id')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('phone_number', 32);
            $table->foreignId('agent_id')->nullable()->constrained('call_center_agents')->nullOnDelete();
            $table->foreignId('call_center_id')->nullable()->constrained('call_centers')->nullOnDelete();
            $table->foreignId('platform_campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('disposition_id')->nullable()->constrained('call_dispositions')->nullOnDelete();
            $table->string('call_type', 32)->default('outbound');
            $table->string('call_status', 32)->default('completed');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('recording_url')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['call_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
        Schema::dropIfExists('call_dispositions');
        Schema::dropIfExists('call_center_agent_group_members');
        Schema::dropIfExists('call_center_agents');
        Schema::dropIfExists('call_center_agent_groups');
        Schema::dropIfExists('call_centers');
    }
};
