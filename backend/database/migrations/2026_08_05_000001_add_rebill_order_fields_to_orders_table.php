<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('order_type', 32)->default('initial')->after('order_number');
            $table->foreignId('parent_order_id')->nullable()->after('duplicate_of_order_id')->constrained('orders')->nullOnDelete();
            $table->foreignId('source_subscription_id')->nullable()->after('parent_order_id')->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('source_billing_ledger_entry_id')->nullable()->after('source_subscription_id')->constrained('billing_ledger_entries')->nullOnDelete();
            $table->unsignedInteger('billing_cycle_step')->nullable()->after('source_billing_ledger_entry_id');
            $table->unsignedInteger('rebill_number')->nullable()->after('billing_cycle_step');
            $table->unsignedInteger('rebill_retry_count')->default(0)->after('rebill_number');

            $table->unique('source_billing_ledger_entry_id');
            $table->index('order_type');
            $table->index('source_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_billing_ledger_entry_id');
            $table->dropConstrainedForeignId('source_subscription_id');
            $table->dropConstrainedForeignId('parent_order_id');
            $table->dropColumn([
                'order_type',
                'billing_cycle_step',
                'rebill_number',
                'rebill_retry_count',
            ]);
        });
    }
};
