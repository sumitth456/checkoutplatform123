<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Modules\Admin\Services\DashboardFilterApplicator;
use App\Modules\Admin\Services\GlobalDashboardFilterService;
use App\Modules\Order\Enums\OrderType;
use Illuminate\Http\Request;

$service = app(GlobalDashboardFilterService::class);
$applicator = app(DashboardFilterApplicator::class);

$total = Order::query()->count();
[$from, $to] = $service->resolveQuickRange('today');

$todayCount = $applicator->applyToOrders(Order::query(), Request::create('/', 'GET', [
    'df_range' => 'today',
    'df_from' => $from->toIso8601String(),
    'df_to' => $to->toIso8601String(),
]))->count();

$todayInitial = $applicator->applyToOrders(
    Order::query()->where('order_type', OrderType::Initial),
    Request::create('/', 'GET', [
        'df_range' => 'today',
        'df_from' => $from->toIso8601String(),
        'df_to' => $to->toIso8601String(),
    ]),
)->count();

$todayRebill = $applicator->applyToOrders(
    Order::query()->where('order_type', OrderType::Rebill),
    Request::create('/', 'GET', [
        'df_range' => 'today',
        'df_from' => $from->toIso8601String(),
        'df_to' => $to->toIso8601String(),
        'tab' => 'rebill',
    ]),
)->count();

$allTimeCount = $applicator->applyToOrders(Order::query(), Request::create('/', 'GET', [
    'df_range' => 'all_time',
]))->count();

echo "Total orders: {$total}\n";
echo "All time (filter): {$allTimeCount}\n";
echo "Today all: {$todayCount}\n";
echo "Today initial: {$todayInitial}\n";
echo "Today rebill: {$todayRebill}\n";
echo ($allTimeCount === $total ? 'PASS' : 'FAIL').": all_time matches total\n";
