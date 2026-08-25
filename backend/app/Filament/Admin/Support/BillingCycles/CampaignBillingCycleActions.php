<?php



namespace App\Filament\Admin\Support\BillingCycles;



use App\Models\Campaign;

use App\Models\CampaignProduct;

use App\Models\Product;

use App\Modules\Campaign\Services\CampaignProductBillingCycleService;

use App\Modules\Campaign\Services\SubscriptionPlanCampaignBillingService;

use App\Modules\Product\BillingCycles\BillingCycleAttributeMapper;

use Filament\Actions\Action;

use Filament\Forms\Components\Repeater;

use Filament\Notifications\Notification;

use Filament\Schemas\Components\Component;



final class CampaignBillingCycleActions

{

    public static function editBillingCyclesAction(

        Campaign $campaign,

        CampaignProductBillingCycleService $cycleService,

        SubscriptionPlanCampaignBillingService $planBilling,

    ): Action {

        $planManaged = $planBilling->campaignUsesPlan($campaign);



        return Action::make('edit_billing_cycles')

            ->label($planManaged ? 'Bill products (SKU)' : 'Billing cycles')

            ->icon('heroicon-o-arrow-path')

            ->slideOver()

            ->modalHeading($planManaged

                ? 'Bill products per cycle (plan-managed)'

                : 'Edit billing cycles')

            ->modalDescription($planManaged

                ? 'Rebill schedule, pricing, retry rules, and gateway settings come from the campaign subscription plan. Set which product/SKU bills on each cycle.'

                : null)

            ->fillForm(fn (CampaignProduct $record): array => [

                'cycles' => $record->billingCycles()

                    ->orderBy('cycle_number')

                    ->get()

                    ->map(fn ($cycle): array => BillingCycleAttributeMapper::fromCampaignCycle($cycle))

                    ->values()

                    ->all(),

            ])

            ->form(fn (): array => [

                Repeater::make('cycles')

                    ->label($planManaged ? 'Bill product per cycle' : 'Billing cycles')

                    ->schema(self::cycleSchema($planManaged))

                    ->orderColumn('cycle_number')

                    ->addable(! $planManaged)

                    ->deletable(! $planManaged)

                    ->reorderable(! $planManaged)

                    ->addActionLabel('Add cycle')

                    ->defaultItems(0)

                    ->columnSpanFull(),

            ])

            ->action(function (CampaignProduct $record, array $data) use ($cycleService, $planBilling, $planManaged): void {

                $rows = collect($data['cycles'] ?? [])

                    ->values()

                    ->map(function (array $row, int $index): array {

                        $row['cycle_number'] ??= $index + 1;



                        return $row;

                    })

                    ->all();



                if ($planManaged) {

                    $planBilling->syncSkuOverrides($record, $rows);

                } else {

                    $cycleService->syncCycles($record, $rows);

                }



                Notification::make()

                    ->title($planManaged ? 'Bill products saved' : 'Billing cycles saved')

                    ->success()

                    ->send();

            });

    }



    public static function resetBillingCyclesAction(

        Campaign $campaign,

        CampaignProductBillingCycleService $cycleService,

        SubscriptionPlanCampaignBillingService $planBilling,

    ): Action {

        $planManaged = $planBilling->campaignUsesPlan($campaign);



        return Action::make('reset_cycles')

            ->label($planManaged ? 'Reset from subscription plan' : 'Reset from product defaults')

            ->icon('heroicon-o-arrow-uturn-left')

            ->requiresConfirmation()

            ->action(function (CampaignProduct $record) use ($cycleService, $planManaged): void {

                $cycleService->inheritForCampaignProduct($record);



                Notification::make()

                    ->title($planManaged

                        ? 'Billing cycles reset from subscription plan'

                        : 'Billing cycles reset from catalog defaults')

                    ->success()

                    ->send();

            });

    }



    /**

     * @return list<Component>

     */

    private static function cycleSchema(bool $planManaged): array

    {

        if (! $planManaged) {

            return BillingCycleFormSchema::cycleFields('campaign');

        }



        return BillingCycleFormSchema::planManagedSkuFields();

    }

}

