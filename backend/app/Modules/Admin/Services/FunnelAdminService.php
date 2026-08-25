<?php

namespace App\Modules\Admin\Services;

use App\Models\Funnel;
use App\Modules\Order\Enums\FunnelStatus;
use Illuminate\Support\Facades\DB;

class FunnelAdminService
{
    /**
     * @return array{all: int, active: int, draft: int, archived: int}
     */
    public function tabCounts(): array
    {
        return [
            'all' => Funnel::query()->count(),
            'active' => Funnel::query()->where('status', FunnelStatus::Active->value)->count(),
            'draft' => Funnel::query()->where('status', FunnelStatus::Draft->value)->count(),
            'archived' => Funnel::query()->where('status', FunnelStatus::Archived->value)->count(),
        ];
    }

    /**
     * @return array{orders: int, revenue: float}
     */
    public function performance(Funnel $funnel): array
    {
        $row = DB::table('orders')
            ->where('funnel_id', $funnel->id)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(grand_total), 0) as revenue')
            ->first();

        return [
            'orders' => (int) ($row->orders_count ?? 0),
            'revenue' => (float) ($row->revenue ?? 0),
        ];
    }
}
