<?php

namespace App\Modules\Affiliate\Actions;

use Illuminate\Http\Request;

class MergeAffiliateCheckoutMetadataAction
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function execute(Request $request, array $validated): array
    {
        $metadata = $validated['metadata'] ?? [];

        if (! isset($metadata['affiliate_id'])) {
            $metadata = array_merge($metadata, $this->fromCookie($request));
        }

        if (! isset($metadata['affiliate_id'])) {
            $metadata = array_merge($metadata, $this->fromQuery($request));
        }

        $validated['metadata'] = array_filter(
            $metadata,
            fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function fromCookie(Request $request): array
    {
        $raw = $request->cookie('affiliate_attribution');

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return [];
        }

        return array_filter([
            'affiliate_id' => isset($data['affiliate_id']) ? (int) $data['affiliate_id'] : null,
            'affiliate_click_id' => isset($data['affiliate_click_id']) ? (int) $data['affiliate_click_id'] : null,
            'affiliate_tracking_id' => $data['affiliate_tracking_id'] ?? null,
            'affiliate_click_uuid' => $data['click_uuid'] ?? null,
            'affiliate_offer_id' => isset($data['affiliate_offer_id']) ? (int) $data['affiliate_offer_id'] : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function fromQuery(Request $request): array
    {
        $trackingId = $request->query('aff')
            ?? $request->query('affiliate')
            ?? $request->query('ref');

        if ($trackingId === null) {
            return [];
        }

        return array_filter([
            'affiliate_tracking_id' => (string) $trackingId,
            'aff' => (string) $trackingId,
            'affiliate_offer_id' => is_numeric($request->query('offer')) ? (int) $request->query('offer') : null,
            'sub1' => $request->query('sub1') ?? $request->query('sub_id_1'),
            'sub2' => $request->query('sub2') ?? $request->query('sub_id_2'),
            'sub3' => $request->query('sub3') ?? $request->query('sub_id_3'),
            'sub4' => $request->query('sub4') ?? $request->query('sub_id_4'),
            'sub5' => $request->query('sub5') ?? $request->query('sub_id_5'),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
