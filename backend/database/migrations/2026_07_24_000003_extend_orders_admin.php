<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('fraud_status')->default('clear')->after('payment_status');
            $table->decimal('fraud_score', 5, 2)->nullable()->after('fraud_status');
            $table->foreignId('duplicate_of_order_id')
                ->nullable()
                ->after('fraud_score')
                ->constrained('orders')
                ->nullOnDelete();

            $table->index('fraud_status');
            $table->index('duplicate_of_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('duplicate_of_order_id');
            $table->dropColumn(['fraud_status', 'fraud_score']);
        });
    }
};
