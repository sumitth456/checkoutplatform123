<?php



namespace Tests\Unit\Analytics;



use App\Filament\Admin\Pages\RebillRecoveryAnalytics;

use PHPUnit\Framework\Attributes\Test;

use Tests\TestCase;



class RebillRecoveryReportPaginationTest extends TestCase

{

    #[Test]

    public function paginates_report_rows_without_dropping_totals(): void

    {

        $page = app(RebillRecoveryAnalytics::class);

        $rows = array_map(fn (int $i) => ['customer_name' => 'Customer '.$i], range(1, 230));



        $page->reportPage = 1;

        $page->reportPerPage = 25;

        $first = $page->paginateReportRows($rows);



        $this->assertCount(25, $first['rows']);

        $this->assertSame(230, $first['total']);

        $this->assertSame(1, $first['from']);

        $this->assertSame(25, $first['to']);

        $this->assertSame(10, $first['last_page']);



        $page->reportPage = 10;

        $last = $page->paginateReportRows($rows);



        $this->assertCount(5, $last['rows']);

        $this->assertSame(226, $last['from']);

        $this->assertSame(230, $last['to']);

    }

}
