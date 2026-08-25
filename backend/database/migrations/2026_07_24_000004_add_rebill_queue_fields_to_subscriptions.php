<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscriptions', 'rebill_queue_status')) {
                $table->string('rebill_queue_status')->default('queued')->after('next_billing_at');
            }

            if (! Schema::hasColumn('subscriptions', 'rebill_skipped_until')) {
                $table->timestamp('rebill_skipped_until')->nullable()->after('rebill_queue_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('subscriptions', 'rebill_skipped_until')) {
                $table->dropColumn('rebill_skipped_until');
            }

            if (Schema::hasColumn('subscriptions', 'rebill_queue_status')) {
                $table->dropColumn('rebill_queue_status');
            }
        });
    }
};
