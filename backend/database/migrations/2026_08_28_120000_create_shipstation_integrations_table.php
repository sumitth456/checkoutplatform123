<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipstation_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('shipstation');
            $table->string('external_order_id')->nullable();
            $table->string('external_shipment_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->string('service')->nullable();
            $table->string('status')->default('not_sent');
            $table->string('label_status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'provider']);
            $table->index('status');
            $table->index('external_order_id');
        });

        Schema::table('shipstation_integration_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipstation_integration_logs', 'request_payload')) {
                $table->json('request_payload')->nullable()->after('context');
            }

            if (! Schema::hasColumn('shipstation_integration_logs', 'response_payload')) {
                $table->json('response_payload')->nullable()->after('request_payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipstation_integration_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('shipstation_integration_logs', 'response_payload')) {
                $table->dropColumn('response_payload');
            }

            if (Schema::hasColumn('shipstation_integration_logs', 'request_payload')) {
                $table->dropColumn('request_payload');
            }
        });

        Schema::dropIfExists('shipstation_integrations');
    }
};
