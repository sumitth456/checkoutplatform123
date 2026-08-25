<?php

namespace App\Modules\LandingPage\Services;

use App\Models\LandingPage;
use App\Models\LandingPageViewEvent;
use App\Models\Order;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Order\Enums\OrderPaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LandingPageAnalyticsService
{
    public function recordView(LandingPage $page, Request $request): void
    {
        DB::transaction(function () use ($page, $request): void {
            LandingPageViewEvent::query()->create([
                'landing_page_id' => $page->id,
                'session_id' => $request->session()->getId(),
                'ip_hash' => hash('sha256', (string) $request->ip()),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'referrer' => substr((string) $request->headers->get('referer'), 0, 500),
                'viewed_at' => now(),
            ]);

            $page->increment('view_count');
        });
    }

    public function recordConversion(LandingPage $page, float $revenue = 0): void
    {
        $page->increment('conversion_count');
        if ($revenue > 0) {
            $page->increment('revenue_total', $revenue);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(AnalyticsPeriod $period): array
    {
        $from = $period->from;
        $to = $period->to;

        $views = $this->viewCountBetween($from, $to);
        $orderStats = $this->paidLandingPageOrdersBetween($from, $to);
        $conversions = (int) $orderStats['conversions'];
        $revenue = (float) $orderStats['revenue'];

        return [
            'views' => $views,
            'conversions' => $conversions,
            'revenue' => $revenue,
            'conversion_rate' => $this->conversionRate($views, $conversions),
            'views_trend' => $this->dailyViewsTrend($from, $to),
            'conversions_trend' => $this->dailyConversionsTrend($from, $to),
            'top_pages' => $this->topPages($period, 10),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pageSnapshot(LandingPage $page, AnalyticsPeriod $period): array
    {
        $from = $period->from;
        $to = $period->to;

        $views = LandingPageViewEvent::query()
            ->where('landing_page_id', $page->id)
            ->whereBetween('viewed_at', [$from, $to])
            ->count();

        $orders = $this->paidOrdersForPage($page, $from, $to);
        $conversions = $orders->count();
        $revenue = (float) $orders->sum('grand_total');

        return [
            'views' => $views,
            'conversions' => $conversions,
            'revenue' => $revenue,
            'conversion_rate' => $this->conversionRate($views, $conversions),
            'lifetime_views' => $page->view_count,
            'lifetime_conversions' => $page->conversion_count,
            'lifetime_revenue' => (float) $page->revenue_total,
        ];
    }

    public function resolvePageFromOrder(Order $order): ?LandingPage
    {
        $identifier = $order->landing_page_id
            ?? ($order->metadata['landing_page_uuid'] ?? null);

        if (blank($identifier)) {
            return null;
        }

        return LandingPage::query()
            ->where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function topPages(AnalyticsPeriod $period, int $limit = 10): array
    {
        $from = $period->from;
        $to = $period->to;

        $viewsByPage = LandingPageViewEvent::query()
            ->select('landing_page_id', DB::raw('COUNT(*) as views'))
            ->whereBetween('viewed_at', [$from, $to])
            ->groupBy('landing_page_id')
            ->pluck('views', 'landing_page_id');

        $ordersByIdentifier = Order::query()
            ->select(
                'landing_page_id',
                DB::raw('COUNT(*) as conversions'),
                DB::raw('COALESCE(SUM(grand_total), 0) as revenue'),
            )
            ->where('payment_status', OrderPaymentStatus::Paid)
            ->whereNotNull('landing_page_id')
            ->whereBetween('paid_at', [$from, $to])
            ->groupBy('landing_page_id')
            ->get()
            ->keyBy('landing_page_id');

        $pageIds = $viewsByPage->keys()
            ->merge(
                LandingPage::query()
                    ->whereIn('uuid', $ordersByIdentifier->keys())
                    ->pluck('id'),
            )
            ->unique()
            ->filter()
            ->values();

        if ($pageIds->isEmpty()) {
            return [];
        }

        $pages = LandingPage::query()->whereIn('id', $pageIds)->get()->keyBy('id');

        $rows = $pageIds->map(function ($pageId) use ($viewsByPage, $ordersByIdentifier, $pages): array {
            /** @var LandingPage|null $page */
            $page = $pages->get($pageId);
            if ($page === null) {
                return [
                    'uuid' => null,
                    'name' => 'Unknown page',
                    'slug' => null,
                    'views' => (int) ($viewsByPage[$pageId] ?? 0),
                    'conversions' => 0,
                    'revenue' => 0.0,
                    'conversion_rate' => 0.0,
                ];
            }

            $views = (int) ($viewsByPage[$page->id] ?? 0);
            $orderRow = $ordersByIdentifier->get($page->uuid);
            $conversions = (int) ($orderRow->conversions ?? 0);
            $revenue = (float) ($orderRow->revenue ?? 0);

            return [
                'uuid' => $page->uuid,
                'name' => $page->name,
                'slug' => $page->slug,
                'views' => $views,
                'conversions' => $conversions,
                'revenue' => $revenue,
                'conversion_rate' => $this->conversionRate($views, $conversions),
            ];
        });

        return $rows
            ->sortByDesc('revenue')
            ->take($limit)
            ->values()
            ->all();
    }

    private function conversionRate(int $views, int $conversions): float
    {
        if ($views <= 0) {
            return 0.0;
        }

        return round(($conversions / $views) * 100, 2);
    }

    /**
     * @return array{conversions: int, revenue: float}
     */
    private function paidLandingPageOrdersBetween($from, $to): array
    {
        $query = Order::query()
            ->where('payment_status', OrderPaymentStatus::Paid)
            ->whereNotNull('landing_page_id')
            ->whereBetween('paid_at', [$from, $to]);

        return [
            'conversions' => (int) (clone $query)->count(),
            'revenue' => (float) (clone $query)->sum('grand_total'),
        ];
    }

    /**
     * @return list<array{label: string, views: int}>
     */
    private function dailyViewsTrend($from, $to): array
    {
        $counts = LandingPageViewEvent::query()
            ->select(DB::raw('DATE(viewed_at) as day'), DB::raw('COUNT(*) as total'))
            ->whereBetween('viewed_at', [$from, $to])
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        return $this->fillDailySeries($from, $to, $counts, 'views');
    }

    /**
     * @return list<array{label: string, conversions: int}>
     */
    private function dailyConversionsTrend($from, $to): array
    {
        $counts = Order::query()
            ->select(DB::raw('DATE(paid_at) as day'), DB::raw('COUNT(*) as total'))
            ->where('payment_status', OrderPaymentStatus::Paid)
            ->whereNotNull('landing_page_id')
            ->whereBetween('paid_at', [$from, $to])
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        return $this->fillDailySeries($from, $to, $counts, 'conversions');
    }

    /**
     * @param  Collection<int|string, mixed>  $counts
     * @return list<array<string, mixed>>
     */
    private function fillDailySeries($from, $to, Collection $counts, string $valueKey): array
    {
        $series = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $series[] = [
                'label' => $cursor->format('M j'),
                $valueKey => (int) ($counts[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    private function viewCountBetween($from, $to): int
    {
        return (int) LandingPageViewEvent::query()
            ->whereBetween('viewed_at', [$from, $to])
            ->count();
    }

    private function paidOrdersForPage(LandingPage $page, $from, $to): Collection
    {
        return Order::query()
            ->where('payment_status', OrderPaymentStatus::Paid)
            ->whereBetween('paid_at', [$from, $to])
            ->where(function ($query) use ($page): void {
                $query->where('landing_page_id', $page->uuid)
                    ->orWhere('landing_page_id', $page->slug);
            })
            ->get();
    }
}
