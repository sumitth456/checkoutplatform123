<?php

namespace App\Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Modules\Admin\Services\PlatformConfigResolver;
use App\Modules\Order\Actions\CaptureRequestContextAction;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use App\Modules\Order\Http\Requests\CheckoutOrderRequest;
use App\Modules\Order\Http\Requests\ListOrdersRequest;
use App\Modules\Order\Http\Requests\StoreFunnelOrderRequest;
use App\Modules\Order\Http\Resources\OrderResource;
use App\Modules\Order\Services\FunnelOrderService;
use App\Modules\Order\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly FunnelOrderService $funnelOrders,
        private readonly OrderService $orders,
        private readonly CaptureRequestContextAction $captureRequestContext,
        private readonly PlatformConfigResolver $platformConfig,
    ) {}

    public function index(ListOrdersRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->orders->list(
            filters: $request->validated(),
            perPage: (int) $request->integer('per_page', 15),
        );

        return OrderResource::collection($paginator);
    }

    public function store(StoreFunnelOrderRequest $request): JsonResponse
    {
        if ($this->platformConfig->checkoutMaintenanceMode()) {
            return response()->json([
                'message' => 'Checkout is temporarily unavailable.',
                'error' => ['code' => 'checkout_maintenance'],
            ], 503);
        }

        $context = $this->captureRequestContext->execute($request);
        $dto = CreateFunnelOrderDTO::fromValidated($request->validated(), $context);
        $order = $this->funnelOrders->place($dto);

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['items.product', 'items.variant', 'customer', 'transactions', 'fulfillments']));
    }

    public function checkout(CheckoutOrderRequest $request, Order $order): OrderResource
    {
        $order = $this->orders->checkout($order, $request->validated());

        return new OrderResource($order->load(['items.product', 'items.variant', 'customer', 'transactions']));
    }

    public function cancel(Request $request, Order $order): OrderResource
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $this->orders->cancel($order, $validated['reason'] ?? null);

        return new OrderResource($order->load(['items.product', 'items.variant', 'customer']));
    }
}
