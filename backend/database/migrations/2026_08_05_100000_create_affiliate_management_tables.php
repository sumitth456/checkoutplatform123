<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('company')->nullable();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone', 32)->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('default_commission_type', 32)->default('percentage');
            $table->decimal('default_commission_value', 12, 4)->default(0);
            $table->string('payment_method', 32)->nullable();
            $table->json('tax_information')->nullable();
            $table->json('address')->nullable();
            $table->string('country_code', 8)->nullable();
            $table->text('notes')->nullable();
            $table->string('api_key', 64)->unique();
            $table->string('tracking_id', 32)->unique();
            $table->string('postback_url')->nullable();
            $table->boolean('allow_coupon_attribution')->default(true);
            $table->boolean('allow_self_referrals')->default(false);
            $table->unsignedInteger('cookie_duration_days')->default(30);
            $table->string('recurring_commission_mode', 32)->default('first_order_only');
            $table->unsignedInteger('recurring_commission_rebill_limit')->nullable();
            $table->decimal('pending_commission', 12, 2)->default(0);
            $table->decimal('paid_commission', 12, 2)->default(0);
            $table->decimal('lifetime_revenue', 12, 2)->default(0);
            $table->unsignedBigInteger('total_clicks')->default(0);
            $table->unsignedBigInteger('total_conversions')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });

        Schema::create('affiliate_offers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('commission_type', 32)->default('percentage');
            $table->decimal('commission_value', 12, 4)->default(0);
            $table->json('allowed_campaign_ids')->nullable();
            $table->json('allowed_product_ids')->nullable();
            $table->json('allowed_landing_page_ids')->nullable();
            $table->string('recurring_commission_mode', 32)->default('first_order_only');
            $table->unsignedInteger('recurring_commission_rebill_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('affiliate_offer_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained('affiliate_offers')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['affiliate_id', 'offer_id']);
        });

        Schema::create('affiliate_campaign_mappings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('platform_campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained('affiliate_offers')->nullOnDelete();
            $table->string('commission_type', 32)->nullable();
            $table->decimal('commission_value', 12, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['affiliate_id', 'platform_campaign_id'], 'aff_camp_map_unique');
        });

        Schema::create('affiliate_tracking_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained('affiliate_offers')->nullOnDelete();
            $table->foreignId('platform_campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('landing_page_id')->nullable()->constrained('landing_pages')->nullOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name')->nullable();
            $table->string('destination_url');
            $table->string('generated_url');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('click_count')->default(0);
            $table->unsignedBigInteger('conversion_count')->default(0);
            $table->json('default_sub_ids')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('affiliate_clicks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('click_id', 64)->unique();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained('affiliate_offers')->nullOnDelete();
            $table->foreignId('platform_campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('tracking_link_id')->nullable()->constrained('affiliate_tracking_links')->nullOnDelete();
            $table->foreignId('landing_page_id')->nullable()->constrained('landing_pages')->nullOnDelete();
            $table->string('sub_id_1')->nullable();
            $table->string('sub_id_2')->nullable();
            $table->string('sub_id_3')->nullable();
            $table->string('sub_id_4')->nullable();
            $table->string('sub_id_5')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('device_type', 32)->nullable();
            $table->string('country_code', 8)->nullable();
            $table->string('landing_url')->nullable();
            $table->string('referrer_url')->nullable();
            $table->boolean('is_unique')->default(true);
            $table->timestamp('clicked_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['affiliate_id', 'clicked_at']);
        });

        Schema::create('affiliate_conversions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained('affiliate_offers')->nullOnDelete();
            $table->foreignId('platform_campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('click_id')->nullable()->constrained('affiliate_clicks')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('conversion_type', 32)->default('sale');
            $table->decimal('revenue', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->string('sub_id_1')->nullable();
            $table->string('sub_id_2')->nullable();
            $table->string('sub_id_3')->nullable();
            $table->string('sub_id_4')->nullable();
            $table->string('sub_id_5')->nullable();
            $table->unsignedInteger('rebill_number')->nullable();
            $table->json('product_ids')->nullable();
            $table->timestamp('converted_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['affiliate_id', 'converted_at']);
            $table->unique(['order_id', 'affiliate_id', 'conversion_type', 'rebill_number'], 'aff_conv_order_unique');
        });

        Schema::create('affiliate_payouts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->string('payment_method', 32)->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['affiliate_id', 'status']);
        });

        Schema::create('affiliate_commissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('conversion_id')->nullable()->constrained('affiliate_conversions')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained('affiliate_payouts')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('commission_type', 32);
            $table->decimal('commission_rate', 12, 4)->default(0);
            $table->decimal('order_amount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->string('rule_source', 64)->nullable();
            $table->unsignedInteger('rebill_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('earned_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['affiliate_id', 'status']);
        });

        Schema::create('affiliate_promo_materials', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('type', 32);
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_url')->nullable();
            $table->foreignId('offer_id')->nullable()->constrained('affiliate_offers')->nullOnDelete();
            $table->foreignId('landing_page_id')->nullable()->constrained('landing_pages')->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->json('allowed_affiliate_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('download_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('affiliate_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('affiliate_id')->nullable()->after('platform_campaign_id')->constrained('affiliates')->nullOnDelete();
            $table->foreignId('affiliate_click_id')->nullable()->after('affiliate_id')->constrained('affiliate_clicks')->nullOnDelete();
            $table->foreignId('affiliate_offer_id')->nullable()->after('affiliate_click_id')->constrained('affiliate_offers')->nullOnDelete();
            $table->json('affiliate_sub_ids')->nullable()->after('affiliate_offer_id');
        });

        Schema::table('coupons', function (Blueprint $table): void {
            $table->foreignId('affiliate_id')->nullable()->after('description')->constrained('affiliates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('affiliate_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('affiliate_offer_id');
            $table->dropConstrainedForeignId('affiliate_click_id');
            $table->dropConstrainedForeignId('affiliate_id');
            $table->dropColumn('affiliate_sub_ids');
        });

        Schema::dropIfExists('affiliate_settings');
        Schema::dropIfExists('affiliate_promo_materials');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_conversions');
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliate_tracking_links');
        Schema::dropIfExists('affiliate_campaign_mappings');
        Schema::dropIfExists('affiliate_offer_assignments');
        Schema::dropIfExists('affiliate_offers');
        Schema::dropIfExists('affiliates');
    }
};
