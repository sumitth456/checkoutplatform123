<?php



namespace App\Modules\Admin\Services;



use App\Filament\Admin\Resources\Affiliates\AffiliateResource;

use App\Filament\Admin\Resources\DelayFulfillment\DelayFulfillmentResource;

use App\Filament\Admin\Resources\Orders\OrderResource;

use App\Filament\Admin\Resources\Products\ProductResource;

use App\Filament\Admin\Resources\RebillQueue\RebillQueueResource;

use App\Filament\Admin\Resources\Subscriptions\SubscriptionResource;

use App\Filament\Admin\Resources\Transactions\TransactionResource;

use App\Filament\Admin\Pages\AnalyticsInsights;

use App\Modules\Admin\Data\DashboardFilter;



class DashboardFilterUrlBuilder

{

    public function __construct(

        private readonly GlobalDashboardFilterService $filters,

    ) {}



    public function orders(DashboardFilter $filter, array $extra = []): string

    {

        return $this->withQuery(OrderResource::getUrl('index'), $filter, $extra);

    }



    public function subscriptions(DashboardFilter $filter, array $extra = []): string

    {

        return $this->withQuery(SubscriptionResource::getUrl('index'), $filter, $extra);

    }



    public function fulfillments(DashboardFilter $filter, array $extra = []): string

    {

        return $this->withQuery(DelayFulfillmentResource::getUrl('index'), $filter, $extra);

    }



    public function rebillQueue(DashboardFilter $filter, array $extra = []): string

    {

        return $this->withQuery(RebillQueueResource::getUrl('index'), $filter, $extra);

    }



    public function transactions(DashboardFilter $filter, array $extra = []): string

    {

        return $this->withQuery(TransactionResource::getUrl('index'), $filter, $extra);

    }



    public function affiliates(DashboardFilter $filter, array $extra = []): string

    {

        return $this->withQuery(AffiliateResource::getUrl('index'), $filter, $extra);

    }



    public function analytics(DashboardFilter $filter): string

    {

        return $this->withQuery(AnalyticsInsights::getUrl(), $filter, []);

    }



    public function product(DashboardFilter $filter, int $productId): string

    {

        return $this->withQuery(

            ProductResource::getUrl('view', ['record' => $productId]),

            $filter,

            [],

        );

    }



    /** @param array<string, mixed> $extra */

    private function withQuery(string $url, DashboardFilter $filter, array $extra): string

    {

        $query = array_merge($filter->toQueryParams(), $extra);



        if ($query === []) {

            return $url;

        }



        $separator = str_contains($url, '?') ? '&' : '?';



        return $url.$separator.http_build_query($query);

    }

}

