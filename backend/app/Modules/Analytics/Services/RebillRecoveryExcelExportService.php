<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Admin\Data\DashboardFilter;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\Common\Creator\WriterFactory;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class RebillRecoveryExcelExportService
{
    public function __construct(
        private readonly RebillRecoveryAnalyticsService $analytics,
    ) {}

    /**
     * @return array{payload: array<string, mixed>, path: string, filename: string}
     */
    public function buildWorkbookFile(DashboardFilter $filter, ?string $reportFilter): array
    {
        $payload = $this->analytics->exportPayload($filter, $reportFilter);
        $filename = 'rebill-recovery-report-'.now()->format('Y-m-d-His').'.xlsx';
        $path = storage_path('app/exports/'.$filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        /** @var Writer $writer */
        $writer = WriterFactory::createFromFile($path);
        $writer->setCreator('CheckoutPlatform CRM');
        $writer->openToFile($path);

        $this->writeExecutiveSummary($writer, $payload);
        $this->writeRecoveryByAttempt($writer, $payload);
        $this->writeRebillCycles($writer, $payload);
        $this->writeAttemptHistory($writer, $payload);
        $this->writeCampaignPerformance($writer, $payload);
        $this->writeGatewayPerformance($writer, $payload);
        $this->writeFailureReasons($writer, $payload);
        $this->writeInProgress($writer, $payload);
        $this->writeUnrecovered($writer, $payload);

        $writer->close();

        return [
            'payload' => $payload,
            'path' => $path,
            'filename' => $filename,
        ];
    }

    public function downloadResponse(DashboardFilter $filter, ?string $reportFilter): BinaryFileResponse
    {
        $built = $this->buildWorkbookFile($filter, $reportFilter);

        return response()->download($built['path'], $built['filename'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeExecutiveSummary(Writer $writer, array $payload): void
    {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Executive Summary');

        $headerStyle = $this->headerStyle();
        $labelStyle = (new Style)->setFontBold();
        $kpis = $payload['kpis'] ?? [];
        $rates = $payload['rates'] ?? [];
        $amounts = $payload['amounts'] ?? [];
        $filters = $payload['filters'] ?? [];

        $writer->addRow(Row::fromValues(['Rebill Recovery & Retry Analytics — Executive Summary'], $headerStyle));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Reporting period (from)', $payload['period']['from'] ?? '']));
        $writer->addRow(Row::fromValues(['Reporting period (to)', $payload['period']['to'] ?? '']));
        $writer->addRow(Row::fromValues(['Currency', $payload['period']['currency'] ?? '']));
        $writer->addRow(Row::fromValues(['Report generated at', $payload['generated_at'] ?? '']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Applied filters'], $labelStyle));
        $writer->addRow(Row::fromValues(['Campaign', $filters['campaign'] ?? '']));
        $writer->addRow(Row::fromValues(['Payment gateway', $filters['gateway'] ?? '']));
        $writer->addRow(Row::fromValues(['Rebill status (detail sheets)', $filters['rebill_status_filter'] ?? 'All']));
        $writer->addRow(Row::fromValues([]));

        $metricRows = [
            ['Total rebills due', $kpis['total_due'] ?? 0],
            ['Total rebills attempted', $kpis['total_attempted'] ?? 0],
            ['Successful on first attempt', $kpis['successful_first_attempt'] ?? 0],
            ['Initially failed rebills', $kpis['failed_first_attempt'] ?? 0],
            ['Recovered after retries', $kpis['recovered_after_retry'] ?? 0],
            ['Permanently unrecovered rebills', $kpis['permanently_unrecovered'] ?? 0],
            ['Recovery in progress', $kpis['recovery_in_progress'] ?? 0],
            ['First-attempt success rate', ($rates['first_attempt_success_rate'] ?? 0).'%'],
            ['Retry recovery rate', ($kpis['retry_recovery_rate'] ?? 0).'%'],
            ['Overall collection rate', ($rates['overall_collection_rate'] ?? 0).'%'],
            ['Total initially failed amount', $amounts['initially_failed_amount'] ?? 0],
            ['Revenue recovered through retries', $amounts['revenue_recovered_retries'] ?? 0],
            ['Revenue still unrecovered', $amounts['revenue_still_unrecovered'] ?? 0],
        ];

        $writer->addRow(Row::fromValues(['Key metrics', 'Value'], $headerStyle));
        foreach ($metricRows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Management summary'], $headerStyle));
        foreach ($this->managementSummaryParagraphs($kpis, $rates, $amounts, $payload) as $paragraph) {
            $writer->addRow(Row::fromValues([$paragraph]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Outcome distribution (use for charting)'], $headerStyle));
        $writer->addRow(Row::fromValues(['Outcome', 'Cycles'], $headerStyle));
        $writer->addRow(Row::fromValues([
            'Collected (first attempt or retry)',
            ($kpis['successful_first_attempt'] ?? 0) + ($kpis['recovered_after_retry'] ?? 0),
        ]));
        $writer->addRow(Row::fromValues(['Recovery in progress', $kpis['recovery_in_progress'] ?? 0]));
        $writer->addRow(Row::fromValues(['Permanently unrecovered', $kpis['permanently_unrecovered'] ?? 0]));

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Data limitations'], $headerStyle));
        foreach ($payload['limitations'] ?? [] as $limitation) {
            $writer->addRow(Row::fromValues(['• '.$limitation]));
        }

        $sheet->setColumnWidth(45, 1);
        $sheet->setColumnWidth(28, 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeRecoveryByAttempt(Writer $writer, array $payload): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Recovery by Attempt');

        $headerStyle = $this->headerStyle();
        $pctStyle = (new Style)->setFormat('0.00%');
        $moneyStyle = (new Style)->setFormat('$#,##0.00');

        $headers = [
            'Attempt',
            'Description',
            'Total Attempts',
            'Successful',
            'Failed',
            'Success Rate',
            'Revenue Collected',
        ];
        $headerRowIndex = 3;
        $writer->addRow(Row::fromValues(['Payment attempts across all in-scope rebill cycles (not cycle counts).']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues($headers, $headerStyle));

        foreach ($payload['recovery_by_payment_attempt'] ?? [] as $row) {
            $rate = ((float) ($row['success_rate'] ?? 0)) / 100;
            $writer->addRow(Row::fromValuesWithStyles([
                $row['attempt'] ?? '',
                $row['description'] ?? '',
                $row['total_attempts'] ?? 0,
                $row['successful'] ?? 0,
                $row['failed'] ?? 0,
                $rate,
                (float) ($row['revenue_collected'] ?? 0),
            ], null, [
                5 => $pctStyle,
                6 => $moneyStyle,
            ]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Cycle-level recovery (for dashboard alignment)'], $headerStyle));
        $writer->addRow(Row::fromValues(['Label', 'Cycles', 'Percent', 'Recovered revenue'], $headerStyle));
        foreach ($payload['recovery_by_attempt_cycles'] ?? [] as $row) {
            $writer->addRow(Row::fromValues([
                $row['label'] ?? '',
                $row['count'] ?? 0,
                ((float) ($row['percent'] ?? 0)).'%',
                $row['recovered_revenue'] ?? 0,
            ]));
        }

        $this->applyDataTableChrome($sheet, $headerRowIndex, \count($headers), $headerRowIndex + \count($payload['recovery_by_payment_attempt'] ?? []));
        $sheet->setColumnWidthForRange(16, 1, 7);
        $sheet->setColumnWidth(38, 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeRebillCycles(Writer $writer, array $payload): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Rebill Cycles');

        $headers = [
            'Customer',
            'Customer ID',
            'Email',
            'Subscription ID',
            'Order ID',
            'Campaign',
            'Plan',
            'Cycle step',
            'Scheduled rebill',
            'Original amount',
            'Initial result',
            'Total attempts',
            'Final status',
            'Recovery attempt #',
            'Total collected',
            'Retry revenue',
            'Unrecovered',
            'Retry status',
            'Gateway',
        ];

        $headerStyle = $this->headerStyle();
        $writer->addRow(Row::fromValues($headers, $headerStyle));
        $dataStart = 1;
        $statusStyles = $this->statusStyles();

        foreach ($payload['cycle_rows'] ?? [] as $row) {
            $statusKey = $row['final_status_key'] ?? '';
            $writer->addRow(Row::fromValuesWithStyles([
                $row['customer_name'] ?? '',
                $row['customer_id'] ?? '',
                $row['customer_email'] ?? '',
                $row['subscription_id'] ?? '',
                $row['order_id'] ?? '',
                $row['campaign'] ?? '',
                $row['plan_name'] ?? '',
                $row['cycle_step'] ?? '',
                $row['scheduled_rebill_at'] ?? '',
                (float) ($row['original_amount'] ?? 0),
                $row['initial_result'] ?? '',
                $row['total_attempts'] ?? 0,
                $row['final_status'] ?? '',
                $row['recovery_attempt_number'] ?? '',
                (float) ($row['total_collected'] ?? 0),
                (float) ($row['recovered_revenue'] ?? 0),
                (float) ($row['unrecovered_amount'] ?? 0),
                $row['retry_status'] ?? '',
                $row['payment_gateway'] ?? '',
            ], $statusStyles[$statusKey] ?? null));
        }

        $rowCount = \count($payload['cycle_rows'] ?? []);
        $this->applyDataTableChrome($sheet, $dataStart, \count($headers), $dataStart + $rowCount);
        $sheet->setColumnWidthForRange(14, 1, \count($headers));
        $sheet->setColumnWidth(22, 1);
        $sheet->setColumnWidth(24, 6);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeAttemptHistory(Writer $writer, array $payload): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Payment Attempts');

        $headers = [
            'Customer',
            'Customer ID',
            'Subscription ID',
            'Order ID',
            'Campaign',
            'Cycle step',
            'Attempt #',
            'Ledger retry_attempt',
            'Attempted at',
            'Amount',
            'Currency',
            'Outcome',
            'Gateway',
            'Failure code',
            'Failure message',
            'Idempotency key',
        ];

        $headerStyle = $this->headerStyle();
        $writer->addRow(Row::fromValues($headers, $headerStyle));
        $failStyle = (new Style)->setBackgroundColor(Color::rgb(255, 230, 230));
        $okStyle = (new Style)->setBackgroundColor(Color::rgb(230, 245, 230));

        foreach ($payload['attempt_rows'] ?? [] as $row) {
            $isSuccess = ($row['outcome'] ?? '') === BillingLedgerOutcome::Success->value;
            $writer->addRow(Row::fromValuesWithStyles([
                $row['customer_name'] ?? '',
                $row['customer_id'] ?? '',
                $row['subscription_id'] ?? '',
                $row['order_id'] ?? '',
                $row['campaign'] ?? '',
                $row['cycle_step'] ?? '',
                $row['attempt_sequence'] ?? '',
                $row['ledger_retry_attempt'] ?? '',
                $row['attempted_at'] ?? '',
                (float) ($row['amount'] ?? 0),
                $row['currency'] ?? '',
                $row['outcome'] ?? '',
                $row['gateway'] ?? '',
                $row['failure_code'] ?? '',
                $row['failure_message'] ?? '',
                $row['idempotency_key'] ?? '',
            ], $isSuccess ? $okStyle : $failStyle));
        }

        $rowCount = \count($payload['attempt_rows'] ?? []);
        $this->applyDataTableChrome($sheet, 1, \count($headers), 1 + $rowCount);
        $sheet->setColumnWidthForRange(16, 1, \count($headers));
        $sheet->setColumnWidth(36, 15);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeCampaignPerformance(Writer $writer, array $payload): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Campaign Performance');

        $headers = [
            'Campaign',
            'Total cycles',
            'Success first',
            'Failed first',
            'Recovered',
            'In progress',
            'Unrecovered',
            'Retry recovery %',
            'Collection %',
            'Recovered revenue',
            'Unrecovered revenue',
        ];
        $headerStyle = $this->headerStyle();
        $writer->addRow(Row::fromValues($headers, $headerStyle));

        foreach ($payload['campaign_performance'] ?? [] as $row) {
            $writer->addRow(Row::fromValues([
                $row['label'] ?? '',
                $row['total_cycles'] ?? 0,
                $row['success_first'] ?? 0,
                $row['failed_first'] ?? 0,
                $row['recovered'] ?? 0,
                $row['in_progress'] ?? 0,
                $row['unrecovered'] ?? 0,
                ((float) ($row['retry_recovery_rate'] ?? 0)).'%',
                ((float) ($row['collection_rate'] ?? 0)).'%',
                $row['recovered_revenue'] ?? 0,
                $row['unrecovered_revenue'] ?? 0,
            ]));
        }

        $rowCount = \count($payload['campaign_performance'] ?? []);
        $this->applyDataTableChrome($sheet, 1, \count($headers), 1 + $rowCount);
        $sheet->setColumnWidth(28, 1);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeGatewayPerformance(Writer $writer, array $payload): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Gateway Performance');

        $writer->addRow(Row::fromValues([
            'Gateway attribution is subscription-level; individual ledger attempts do not store gateway per attempt.',
        ]));
        $writer->addRow(Row::fromValues([]));

        $headers = [
            'Gateway',
            'Provider',
            'Total cycles',
            'Success first',
            'Failed first',
            'Recovered',
            'In progress',
            'Unrecovered',
            'Retry recovery %',
            'Collection %',
            'Recovered revenue',
            'Unrecovered revenue',
        ];
        $headerStyle = $this->headerStyle();
        $writer->addRow(Row::fromValues($headers, $headerStyle));
        $dataStart = 3;

        foreach ($payload['gateway_performance'] ?? [] as $row) {
            $writer->addRow(Row::fromValues([
                $row['label'] ?? '',
                $row['provider'] ?? '',
                $row['total_cycles'] ?? 0,
                $row['success_first'] ?? 0,
                $row['failed_first'] ?? 0,
                $row['recovered'] ?? 0,
                $row['in_progress'] ?? 0,
                $row['unrecovered'] ?? 0,
                ((float) ($row['retry_recovery_rate'] ?? 0)).'%',
                ((float) ($row['collection_rate'] ?? 0)).'%',
                $row['recovered_revenue'] ?? 0,
                $row['unrecovered_revenue'] ?? 0,
            ]));
        }

        $rowCount = \count($payload['gateway_performance'] ?? []);
        $this->applyDataTableChrome($sheet, $dataStart, \count($headers), $dataStart + $rowCount);
        $sheet->setColumnWidth(30, 1);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeFailureReasons(Writer $writer, array $payload): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Failure Reasons');

        $headers = [
            'Failure code',
            'Failure message',
            'Gateway',
            'Occurrences',
            'Affected cycles',
            'Subsequent recoveries',
            'Recovery %',
        ];
        $headerStyle = $this->headerStyle();
        $writer->addRow(Row::fromValues($headers, $headerStyle));

        foreach ($payload['failure_reasons'] ?? [] as $row) {
            $writer->addRow(Row::fromValues([
                $row['failure_code'] ?? '',
                $row['failure_message'] ?? '',
                $row['gateway'] ?? '',
                $row['occurrences'] ?? 0,
                $row['affected_cycles'] ?? 0,
                $row['subsequent_recovery_count'] ?? 0,
                ((float) ($row['recovery_percentage'] ?? 0)).'%',
            ]));
        }

        $rowCount = \count($payload['failure_reasons'] ?? []);
        $this->applyDataTableChrome($sheet, 1, \count($headers), 1 + $rowCount);
        $sheet->setColumnWidth(40, 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeInProgress(Writer $writer, array $payload): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Recovery In Progress');

        $headers = [
            'Customer',
            'Customer ID',
            'Subscription ID',
            'Campaign',
            'Initial failure',
            'Scheduled amount',
            'Currency',
            'Attempts made',
            'Last failure code',
            'Last failure message',
            'Next scheduled retry',
            'Retry status',
        ];
        $headerStyle = $this->headerStyle();
        $inProgressStyle = (new Style)->setBackgroundColor(Color::rgb(255, 248, 220));
        $writer->addRow(Row::fromValues($headers, $headerStyle));

        foreach ($payload['in_progress_rows'] ?? [] as $row) {
            $writer->addRow(Row::fromValuesWithStyles([
                $row['customer_name'] ?? '',
                $row['customer_id'] ?? '',
                $row['subscription_id'] ?? '',
                $row['campaign'] ?? '',
                $row['initial_failure_at'] ?? '',
                (float) ($row['scheduled_amount'] ?? 0),
                $row['currency'] ?? '',
                $row['attempts_made'] ?? 0,
                $row['last_failure_code'] ?? '',
                $row['last_failure_message'] ?? '',
                $row['next_scheduled_retry'] ?? '',
                $row['retry_status'] ?? '',
            ], $inProgressStyle));
        }

        $rowCount = \count($payload['in_progress_rows'] ?? []);
        $this->applyDataTableChrome($sheet, 1, \count($headers), 1 + $rowCount);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeUnrecovered(Writer $writer, array $payload): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Unrecovered Rebills');

        $headers = [
            'Customer',
            'Customer ID',
            'Subscription ID',
            'Campaign',
            'Initial failure',
            'Total attempts',
            'Last failure code',
            'Last failure message',
            'Scheduled amount',
            'Unrecovered amount',
            'Currency',
            'Final status',
        ];
        $headerStyle = $this->headerStyle();
        $failStyle = (new Style)->setBackgroundColor(Color::rgb(255, 230, 230));
        $writer->addRow(Row::fromValues($headers, $headerStyle));

        foreach ($payload['unrecovered_rows'] ?? [] as $row) {
            $writer->addRow(Row::fromValuesWithStyles([
                $row['customer_name'] ?? '',
                $row['customer_id'] ?? '',
                $row['subscription_id'] ?? '',
                $row['campaign'] ?? '',
                $row['initial_failure_at'] ?? '',
                $row['total_attempts'] ?? 0,
                $row['last_failure_code'] ?? '',
                $row['last_failure_message'] ?? '',
                (float) ($row['scheduled_amount'] ?? 0),
                (float) ($row['unrecovered_amount'] ?? 0),
                $row['currency'] ?? '',
                $row['final_status'] ?? '',
            ], $failStyle));
        }

        $rowCount = \count($payload['unrecovered_rows'] ?? []);
        $this->applyDataTableChrome($sheet, 1, \count($headers), 1 + $rowCount);
    }

    private function applyDataTableChrome(Sheet $sheet, int $headerRow, int $columnCount, int $lastDataRow): void
    {
        if ($lastDataRow < $headerRow) {
            return;
        }

        $sheet->setSheetView((new SheetView)->setFreezeRow($headerRow + 1));
        $sheet->setAutoFilter(new AutoFilter(
            1,
            $headerRow,
            $columnCount,
            max($headerRow + 1, $lastDataRow),
        ));
    }

    private function headerStyle(): Style
    {
        return (new Style)
            ->setFontBold()
            ->setBackgroundColor(Color::rgb(31, 56, 100))
            ->setFontColor(Color::WHITE);
    }

    /**
     * @return array<string, Style>
     */
    private function statusStyles(): array
    {
        return [
            RebillRecoveryAnalyticsService::STATUS_SUCCESS_FIRST => (new Style)->setBackgroundColor(Color::rgb(230, 245, 230)),
            RebillRecoveryAnalyticsService::STATUS_RECOVERED_RETRY => (new Style)->setBackgroundColor(Color::rgb(220, 240, 255)),
            RebillRecoveryAnalyticsService::STATUS_IN_PROGRESS => (new Style)->setBackgroundColor(Color::rgb(255, 248, 220)),
            RebillRecoveryAnalyticsService::STATUS_UNRECOVERED => (new Style)->setBackgroundColor(Color::rgb(255, 230, 230)),
        ];
    }

    /**
     * @param  array<string, mixed>  $kpis
     * @param  array<string, mixed>  $rates
     * @param  array<string, mixed>  $amounts
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function managementSummaryParagraphs(array $kpis, array $rates, array $amounts, array $payload): array
    {
        $due = (int) ($kpis['total_due'] ?? 0);
        $failedFirst = (int) ($kpis['failed_first_attempt'] ?? 0);
        $recovered = (int) ($kpis['recovered_after_retry'] ?? 0);
        $inProgress = (int) ($kpis['recovery_in_progress'] ?? 0);
        $unrecovered = (int) ($kpis['permanently_unrecovered'] ?? 0);
        $retryRate = (float) ($kpis['retry_recovery_rate'] ?? 0);
        $collection = (float) ($rates['overall_collection_rate'] ?? 0);
        $recoveredRevenue = (float) ($amounts['revenue_recovered_retries'] ?? 0);
        $lostRevenue = (float) ($amounts['revenue_still_unrecovered'] ?? 0);

        $statusFilter = $payload['filters']['rebill_status_filter'] ?? 'All rebill statuses';

        return [
            sprintf(
                'During the selected period, %d rebill cycles were in scope. Overall collection rate was %.1f%% (%d cycles collected on first attempt or after retry).',
                $due,
                $collection,
                (int) ($kpis['successful_first_attempt'] ?? 0) + $recovered,
            ),
            sprintf(
                '%d cycles failed the initial rebill attempt; %d (%.1f%%) were later recovered through retries, adding $%s in retry revenue.',
                $failedFirst,
                $recovered,
                $retryRate,
                number_format($recoveredRevenue, 2),
            ),
            sprintf(
                '%d cycles remain in an open retry window (not counted as permanently failed). %d cycles are permanently unrecovered, representing $%s at risk.',
                $inProgress,
                $unrecovered,
                number_format($lostRevenue, 2),
            ),
            sprintf(
                'Detailed cycle and attempt sheets respect the status filter "%s"; summary KPIs always reflect the full in-period population (aligned with the CRM dashboard).',
                $statusFilter,
            ),
        ];
    }
}
