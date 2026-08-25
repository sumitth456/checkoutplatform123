<?php

namespace Database\Seeders;

use App\Models\Affiliate;
use App\Models\AffiliateCampaignMapping;
use App\Models\AffiliateClick;
use App\Models\AffiliateCommission;
use App\Models\AffiliateConversion;
use App\Models\AffiliateOffer;
use App\Models\AffiliatePromoMaterial;
use App\Models\AffiliateTrackingLink;
use App\Models\Campaign;
use App\Models\LandingPage;
use App\Modules\Affiliate\Enums\AffiliatePaymentMethod;
use App\Modules\Affiliate\Enums\AffiliateStatus;
use App\Modules\Affiliate\Enums\CommissionStatus;
use App\Modules\Affiliate\Enums\CommissionType;
use App\Modules\Affiliate\Enums\PromoMaterialType;
use App\Modules\Affiliate\Enums\RecurringCommissionMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AffiliateDemoSeeder extends Seeder
{
    public function run(): void
    {
        $campaign = Campaign::query()->first();
        $landingPage = LandingPage::query()->first();

        $affiliateA = Affiliate::query()->updateOrCreate(
            ['email' => 'partner.alpha@checkoutplatform.test'],
            [
                'company' => 'Alpha Media LLC',
                'name' => 'Alpha Partner',
                'username' => 'alpha_partner',
                'password' => Hash::make('affiliate123'),
                'phone' => '+1-555-0201',
                'status' => AffiliateStatus::Active,
                'default_commission_type' => CommissionType::Percentage,
                'default_commission_value' => 15,
                'payment_method' => AffiliatePaymentMethod::Paypal,
                'country_code' => 'US',
                'allow_coupon_attribution' => true,
                'cookie_duration_days' => 30,
                'recurring_commission_mode' => RecurringCommissionMode::EveryRebill,
                'approved_at' => now()->subDays(30),
            ],
        );

        $affiliateB = Affiliate::query()->updateOrCreate(
            ['email' => 'partner.beta@checkoutplatform.test'],
            [
                'company' => 'Beta Traffic Co',
                'name' => 'Beta Partner',
                'username' => 'beta_partner',
                'password' => Hash::make('affiliate123'),
                'status' => AffiliateStatus::Pending,
                'default_commission_type' => CommissionType::Fixed,
                'default_commission_value' => 5,
                'payment_method' => AffiliatePaymentMethod::Wire,
                'country_code' => 'US',
            ],
        );

        $offer = AffiliateOffer::query()->updateOrCreate(
            ['name' => 'Default Subscription Offer'],
            [
                'description' => 'Standard commission offer for subscription products.',
                'commission_type' => CommissionType::Percentage,
                'commission_value' => 20,
                'allowed_campaign_ids' => $campaign ? [$campaign->id] : [],
                'allowed_landing_page_ids' => $landingPage ? [$landingPage->id] : [],
                'recurring_commission_mode' => RecurringCommissionMode::EveryRebill,
                'is_active' => true,
            ],
        );

        $affiliateA->offers()->syncWithoutDetaching([$offer->id]);

        if ($campaign) {
            AffiliateCampaignMapping::query()->updateOrCreate(
                [
                    'affiliate_id' => $affiliateA->id,
                    'platform_campaign_id' => $campaign->id,
                ],
                [
                    'offer_id' => $offer->id,
                    'commission_type' => CommissionType::Percentage,
                    'commission_value' => 25,
                    'is_active' => true,
                ],
            );
        }

        $destination = $landingPage
            ? url($landingPage->publicUrl())
            : rtrim((string) config('app.url'), '/');

        $link = AffiliateTrackingLink::query()->updateOrCreate(
            [
                'affiliate_id' => $affiliateA->id,
                'name' => 'Primary LP Link',
            ],
            [
                'offer_id' => $offer->id,
                'platform_campaign_id' => $campaign?->id,
                'landing_page_id' => $landingPage?->id,
                'destination_url' => $destination,
                'is_active' => true,
            ],
        );

        foreach (range(1, 5) as $i) {
            AffiliateClick::query()->updateOrCreate(
                ['click_id' => 'clk_demo_'.$affiliateA->tracking_id.'_'.$i],
                [
                    'affiliate_id' => $affiliateA->id,
                    'offer_id' => $offer->id,
                    'platform_campaign_id' => $campaign?->id,
                    'tracking_link_id' => $link->id,
                    'landing_page_id' => $landingPage?->id,
                    'sub_id_1' => 'demo',
                    'ip_address' => '127.0.0.1',
                    'browser' => 'Chrome',
                    'os' => 'Windows',
                    'device_type' => 'desktop',
                    'country_code' => 'US',
                    'landing_url' => $link->generated_url,
                    'is_unique' => $i <= 3,
                    'clicked_at' => now()->subHours($i),
                ],
            );
        }

        $affiliateA->update([
            'total_clicks' => 5,
            'total_conversions' => 2,
            'lifetime_revenue' => 29.98,
            'pending_commission' => 7.50,
        ]);

        AffiliateConversion::query()->updateOrCreate(
            [
                'affiliate_id' => $affiliateA->id,
                'conversion_type' => 'sale',
                'rebill_number' => null,
            ],
            [
                'offer_id' => $offer->id,
                'platform_campaign_id' => $campaign?->id,
                'revenue' => 19.99,
                'commission_amount' => 5.00,
                'currency' => 'USD',
                'converted_at' => now()->subDay(),
            ],
        );

        AffiliateCommission::query()->updateOrCreate(
            [
                'affiliate_id' => $affiliateA->id,
                'rule_source' => 'campaign_override',
                'rebill_number' => null,
            ],
            [
                'status' => CommissionStatus::Pending,
                'commission_type' => CommissionType::Percentage->value,
                'commission_rate' => 25,
                'order_amount' => 19.99,
                'commission_amount' => 5.00,
                'currency' => 'USD',
                'earned_at' => now()->subDay(),
            ],
        );

        AffiliatePromoMaterial::query()->updateOrCreate(
            ['name' => '728x90 Banner'],
            [
                'type' => PromoMaterialType::Banner,
                'description' => 'Standard leaderboard banner for affiliates.',
                'file_url' => 'https://via.placeholder.com/728x90?text=Affiliate+Banner',
                'offer_id' => $offer->id,
                'is_active' => true,
            ],
        );

        Affiliate::query()->updateOrCreate(
            ['email' => 'partner.gamma@checkoutplatform.test'],
            [
                'name' => 'Gamma Network',
                'username' => 'gamma_net',
                'password' => Hash::make('affiliate123'),
                'status' => AffiliateStatus::Disabled,
                'default_commission_type' => CommissionType::Percentage,
                'default_commission_value' => 10,
            ],
        );
    }
}
