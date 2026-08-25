<?php

namespace Tests\Unit\Admin;

use App\Models\Transaction;
use App\Modules\Admin\Services\TransactionLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_counts_group_transactions(): void
    {
        Transaction::factory()->create(['status' => 'captured', 'type' => 'capture']);
        Transaction::factory()->create(['status' => 'failed', 'type' => 'capture']);
        Transaction::factory()->create(['status' => 'refunded', 'type' => 'refund']);

        $counts = app(TransactionLedgerService::class)->tabCounts();

        $this->assertSame(3, $counts['all']);
        $this->assertSame(1, $counts['successful']);
        $this->assertSame(1, $counts['failed']);
        $this->assertSame(1, $counts['refunds']);
    }
}
