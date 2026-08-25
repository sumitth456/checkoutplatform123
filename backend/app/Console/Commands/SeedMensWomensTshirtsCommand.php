<?php

namespace App\Console\Commands;

use App\Demo\MensWomensTshirtCatalogBuilder;
use App\Models\Variant;
use App\Modules\Order\Actions\CaptureRequestContextAction;
use App\Modules\Order\DTOs\CheckoutPaymentInputDTO;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use App\Modules\Order\Services\StripeCheckoutService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeedMensWomensTshirtsCommand extends Command
{
    protected $signature = 'demo:seed-mens-womens-tshirts
                            {--campaign=TEST-CAMP-001 : Campaign public ID}
                            {--place-order : Place a sandbox test order after seeding}
                            {--gender=mens : Test order gender: mens or womens}
                            {--color=White : Test order color}
                            {--size=M : Test order size}
                            {--email=test@gmail.com : Test order email}
                            {--name=Test Order : Test order customer name}';

    protected $description = 'Seed Men\'s and Women\'s T-Shirt products with color/size variants and attach them to the test campaign.';

    public function handle(
        MensWomensTshirtCatalogBuilder $builder,
        StripeCheckoutService $checkout,
    ): int {
        $result = $builder->seed((string) $this->option('campaign'));

        $this->info("Campaign: {$result['campaign']->public_id} ({$result['campaign']->uuid})");
        $this->info("Men's product: {$result['mens_product']->sku} — {$result['mens_product']->variants()->count()} variants");
        $this->info("Women's product: {$result['womens_product']->sku} — {$result['womens_product']->variants()->count()} variants");

        if (! $this->option('place-order')) {
            return self::SUCCESS;
        }

        $gender = strtolower((string) $this->option('gender'));
        $product = $gender === 'womens' ? $result['womens_product'] : $result['mens_product'];
        $line = $gender === 'womens' ? $result['womens_campaign_line'] : $result['mens_campaign_line'];
        $color = (string) $this->option('color');
        $size = (string) $this->option('size');
        $fullName = (string) $this->option('name');
        [$firstName, $lastName] = array_pad(explode(' ', $fullName, 2), 2, 'Order');

        $variant = Variant::query()
            ->where('product_id', $product->id)
            ->where('sku', sprintf('%s-%s-%s', strtoupper($product->sku), strtoupper($color), strtoupper($size)))
            ->firstOrFail();

        $idempotencyKey = 'tshirt-test-'.Str::uuid();

        $payload = [
            'idempotency_key' => $idempotencyKey,
            'payment_token' => 'pm_card_visa',
            'provider' => 'stripe',
            'customer' => [
                'email' => (string) $this->option('email'),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => '5550100123',
            ],
            'shipping_address' => [
                'line1' => '123 Main Street',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02101',
                'country' => 'US',
            ],
            'billing_address' => [
                'line1' => '123 Main Street',
                'city' => 'Boston',
                'state' => 'MA',
                'postal_code' => '02101',
                'country' => 'US',
            ],
            'items' => [[
                'variant_uuid' => $variant->uuid,
                'product_uuid' => $product->uuid,
                'campaign_product_line_id' => $line->id,
                'color' => $color,
                'size' => $size,
                'quantity' => 1,
            ]],
            'metadata' => [
                'campaign_uuid' => $result['campaign']->uuid,
                'audience' => $gender,
            ],
        ];

        $request = Request::create('/api/v1/checkout', 'POST', $payload);
        $request->headers->set('Accept', 'application/json');
        $context = app(CaptureRequestContextAction::class)->execute($request);
        $dto = CreateFunnelOrderDTO::fromValidated($payload, $context);
        $payment = CheckoutPaymentInputDTO::fromValidated($payload);

        $order = $checkout->submit($dto, $payment)->load(['items', 'items.variant', 'items.product']);

        $this->newLine();
        $this->info('Test order placed successfully.');
        $this->table(
            ['Field', 'Value'],
            [
                ['Order UUID', $order->uuid],
                ['Order Number', $order->order_number],
                ['Status', $order->status->value],
                ['Payment Status', $order->payment_status->value],
                ['Product', $product->name],
                ['Color', $color],
                ['Size', $size],
                ['Variant SKU', $variant->sku],
                ['Variant UUID', $variant->uuid],
                ['Grand Total', $order->grand_total.' '.$order->currency],
            ],
        );

        return self::SUCCESS;
    }
}
