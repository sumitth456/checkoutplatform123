<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('email');
            $table->string('status')->default('active')->after('password');
            $table->string('timezone')->default('UTC')->after('status');
            $table->string('locale', 10)->default('en')->after('timezone');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->json('metadata')->nullable()->after('last_login_at');
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex(['status']);
            $table->dropColumn([
                'uuid',
                'first_name',
                'last_name',
                'phone',
                'status',
                'timezone',
                'locale',
                'last_login_at',
                'metadata',
            ]);
        });
    }
};
