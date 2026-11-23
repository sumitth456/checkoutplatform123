<?php

namespace Tests\Unit\Analytics;

use App\Models\BillingLedgerEntry;
use App\Models\Subscription;
use App\Modules\Admin\Data\DashboardFilter;
use App\Modules\Analytics\Services\RebillRecoveryAnalyticsService;
use App\Modules\Analytics\Services\RebillRecoveryExcelExportService;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RebillRecoveryExcelExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]

    public function export_payload_reconciles_with_dashboard_kpis(): void
    {

        $subscription = Subscription::factory()->create([

            'current_cycle_step' => 2,

            'retry_count' => 0,

        ]);

        $failed = Subscription::factory()->create([

            'current_cycle_step' => 3,

            'status' => SubscriptionStatus::PastDue,

            'retry_count' => 1,

        ]);

        $at = Carbon::now()->subDay();

        BillingLedgerEntry::query()->create([

            'subscription_id' => $subscription->id,

            'cycle_step' => 2,

            'retry_attempt' => 0,

            'outcome' => BillingLedgerOutcome::Success,

            'amount' => 30,

            'currency' => 'USD',

            'idempotency_key' => 'excel-kpi-success',

            'attempted_at' => $at,

        ]);

        BillingLedgerEntry::query()->create([

            'subscription_id' => $failed->id,

            'cycle_step' => 3,

            'retry_attempt' => 1,

            'outcome' => BillingLedgerOutcome::Failure,

            'amount' => 20,

            'currency' => 'USD',

            'idempotency_key' => 'excel-kpi-fail',

            'failure_code' => 'insufficient_funds',

            'attempted_at' => $at,

        ]);

        $filter = new DashboardFilter(

            from: now()->subDays(3),

            to: now()->addDay(),

            previousFrom: now()->subDays(6),

            previousTo: now()->subDays(3),

            currency: 'USD',

        );

        $service = app(RebillRecoveryAnalyticsService::class);

        $snapshot = $service->snapshot($filter);

        $export = $service->exportPayload($filter);

        $this->assertSame($snapshot['kpis'], $export['kpis']);

        $this->assertCount(2, $export['cycle_rows']);

        $this->assertCount(2, $export['attempt_rows']);

        $this->assertSame(0, $export['kpis']['permanently_unrecovered']);

        $this->assertSame(1, $export['kpis']['recovery_in_progress']);

        $this->assertCount(1, $export['in_progress_rows']);

        $this->assertCount(0, $export['unrecovered_rows']);

    }

    #[Test]

    public function excel_workbook_contains_nine_worksheets_and_opens(): void
    {

        $subscription = Subscription::factory()->create(['current_cycle_step' => 1]);

        BillingLedgerEntry::query()->create([

            'subscription_id' => $subscription->id,

            'cycle_step' => 1,

            'retry_attempt' => 0,

            'outcome' => BillingLedgerOutcome::Success,

            'amount' => 15,

            'currency' => 'USD',

            'idempotency_key' => 'excel-file-success',

            'attempted_at' => now()->subHours(2),

        ]);

        $filter = new DashboardFilter(

            from: now()->subDay(),

            to: now()->addDay(),

            previousFrom: now()->subDays(2),

            previousTo: now()->subDay(),

            currency: 'USD',

        );

        $built = app(RebillRecoveryExcelExportService::class)->buildWorkbookFile($filter, null);

        $this->assertFileExists($built['path']);

        $reader = ReaderFactory::createFromFile($built['path']);

        $reader->open($built['path']);

        $sheetNames = [];

        foreach ($reader->getSheetIterator() as $sheet) {

            $sheetNames[] = $sheet->getName();

        }

        $reader->close();

        @unlink($built['path']);

        $this->assertSame([

            'Executive Summary',

            'Recovery by Attempt',

            'Rebill Cycles',

            'Payment Attempts',

            'Campaign Performance',

            'Gateway Performance',

            'Failure Reasons',

            'Recovery In Progress',

            'Unrecovered Rebills',

        ], $sheetNames);

    }

    #[Test]

    public function status_filter_limits_detail_rows_without_changing_kpis(): void
    {

        $success = Subscription::factory()->create(['current_cycle_step' => 1]);

        $failure = Subscription::factory()->create([

            'current_cycle_step' => 2,

            'status' => SubscriptionStatus::Cancelled,

            'retry_count' => 5,

        ]);

        $at = now()->subHours(3);

        BillingLedgerEntry::query()->create([

            'subscription_id' => $success->id,

            'cycle_step' => 1,

            'retry_attempt' => 0,

            'outcome' => BillingLedgerOutcome::Success,

            'amount' => 11,

            'currency' => 'USD',

            'idempotency_key' => 'filter-success',

            'attempted_at' => $at,

        ]);

        BillingLedgerEntry::query()->create([

            'subscription_id' => $failure->id,

            'cycle_step' => 2,

            'retry_attempt' => 2,

            'outcome' => BillingLedgerOutcome::Failure,

            'amount' => 12,

            'currency' => 'USD',

            'idempotency_key' => 'filter-fail',

            'attempted_at' => $at,

        ]);

        $filter = new DashboardFilter(

            from: now()->subDay(),

            to: now()->addDay(),

            previousFrom: now()->subDays(2),

            previousTo: now()->subDay(),

            currency: 'USD',

        );

        $service = app(RebillRecoveryAnalyticsService::class);

        $all = $service->exportPayload($filter, null);

        $filtered = $service->exportPayload($filter, 'success_first');

        $this->assertSame($all['kpis'], $filtered['kpis']);

        $this->assertCount(2, $all['cycle_rows']);

        $this->assertCount(1, $filtered['cycle_rows']);

    }

}
