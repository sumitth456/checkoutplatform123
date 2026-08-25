<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable()->unique()->after('email');
            $table->json('ip_whitelist')->nullable()->after('metadata');
            $table->boolean('two_factor_enabled')->default(false)->after('ip_whitelist');
            $table->boolean('require_password_change')->default(false)->after('two_factor_enabled');
            $table->boolean('force_logout_other_devices')->default(false)->after('require_password_change');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->foreignId('created_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::create('campaign_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_user');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'username',
                'ip_whitelist',
                'two_factor_enabled',
                'require_password_change',
                'force_logout_other_devices',
                'last_login_ip',
            ]);
        });
    }
};
