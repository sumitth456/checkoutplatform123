<?php

return [
    'mode' => env('SHIPSTATION_MODE', 'mock'),

    'base_url' => env('SHIPSTATION_BASE_URL', 'https://api.shipstation.com/v2'),

    'mock_base_url' => env('SHIPSTATION_MOCK_BASE_URL', 'https://docs.shipstation.com/_mock/apis/openapi'),

    'mock_api_key' => env('SHIPSTATION_MOCK_API_KEY', 'SSAT_MOCK_KEY'),

    'mock_warehouse_id' => env('SHIPSTATION_MOCK_WAREHOUSE_ID', 'se-28529731'),

    /** Warehouse ID for real V2 shipment creation (fallback when store is not in store_warehouse_map). */
    'warehouse_id' => env('SHIPSTATION_WAREHOUSE_ID'),

    /**
     * Per-store Ship From warehouse IDs (V2 warehouse_id on shipment create).
     * Keys: legacy numeric store id and/or se-{id} form. Values: se-… warehouse ids.
     * FamTeesWorld.com store 533159 → warehouse se-6134637 (not se-385726 FamTeeWorld typo warehouse).
     */
    'store_warehouse_map' => [
        '533159' => env('SHIPSTATION_WAREHOUSE_ID_FAMTEESWORLD', 'se-6134637'),
        'se-533159' => env('SHIPSTATION_WAREHOUSE_ID_FAMTEESWORLD', 'se-6134637'),
    ],

    /**
     * When true, unmapped store_id values use SHIPSTATION_WAREHOUSE_ID instead of failing.
     * When false, shipment create fails if store_id is not listed in store_warehouse_map.
     */
    'store_warehouse_fallback_to_global' => env('SHIPSTATION_STORE_WAREHOUSE_FALLBACK', true),

    /**
     * Store ID sent on V2 shipment create (store_id field).
     * FamTeesWorld.com legacy store identity: 533159 — set SHIPSTATION_STORE_ID to the exact value
     * your ShipStation V2 create endpoint accepts (numeric or se-…); no runtime conversion.
     */
    'store_id' => env('SHIPSTATION_STORE_ID'),

    /** Human reference only (Filament/docs); not sent to the API. */
    'store_legacy_id' => env('SHIPSTATION_STORE_LEGACY_ID', '533159'),

    'api_key' => env('SHIPSTATION_API_KEY'),

    /** @deprecated V2 uses API-Key header only. Optional legacy V1 fallback. */
    'api_secret' => env('SHIPSTATION_API_SECRET'),

    'timeout_seconds' => (int) env('SHIPSTATION_TIMEOUT', 30),

    'queue' => env('SHIPSTATION_QUEUE', env('CHECKOUT_FULFILLMENT_QUEUE', 'default')),

    'webhook_secret' => env('SHIPSTATION_WEBHOOK_SECRET'),

    /**
     * Phase 1 pilot automatic CRM status sync (ShipStation → Virello).
     * Server-side restricted to listed order UUIDs only.
     */
    /**
     * CRM order UUIDs excluded from all ShipStation sync/submit (automatic, queued, manual).
     * Does not void or delete existing remote shipments.
     */
    'sync_excluded_order_uuids' => array_values(array_filter([
        '04191080-7815-4e24-8f99-800d64f69d2f', // ORD-20261118-942D33
    ])),

    'pilot_auto_status_sync' => [
        'enabled' => filter_var(env('SHIPSTATION_PILOT_AUTO_STATUS_SYNC', false), FILTER_VALIDATE_BOOL),
        'order_uuids' => array_values(array_filter([
            '6591d63b-f76a-4786-95ef-46fa27c62956',
        ])),
        'polling_enabled' => filter_var(env('SHIPSTATION_PILOT_STATUS_POLL', false), FILTER_VALIDATE_BOOL),
        'polling_minutes' => max(5, (int) env('SHIPSTATION_PILOT_STATUS_POLL_MINUTES', 15)),
    ],

    'order_key_prefix' => env('SHIPSTATION_ORDER_KEY_PREFIX', 'cp-order-'),

    /**
     * UTC timestamp from which NEW orders become eligible for automatic V2 label + tracking.
     * Leave empty to disable automatic label processing entirely.
     * Example: SHIPSTATION_AUTO_LABEL_ENABLED_AT=2026-09-04 22:00:00
     */
    'auto_label_enabled_at' => env('SHIPSTATION_AUTO_LABEL_ENABLED_AT'),

    /**
     * UTC timestamp from which NEW FamTeesWorld t-shirt funnel orders skip automatic
     * V2 label purchase and CRM shipped/in-transit completion. Order sync (V2 shipment
     * create) is unchanged. Leave empty to disable. Manual admin fulfillment is unaffected.
     * Example: SHIPSTATION_FAMTEES_DEFER_AUTO_SHIPMENT_AT=2026-10-27 02:10:00
     */
    'famtees_defer_auto_shipment_at' => env('SHIPSTATION_FAMTEES_DEFER_AUTO_SHIPMENT_AT'),

    /**
     * CRM order_number values excluded from automatic ShipStation sync, warehouse jobs,
     * automatic label purchase, and manual admin Send/Shipment/Label actions.
     */
    'automatic_processing_hold_order_numbers' => [
        'ORD-20261026-84C5D6',
        'ORD-20261027-74E770',
        'ORD-20261027-1ABDC3',
        'ORD-20261027-93145C',
        'ORD-20261027-89F790',
        'ORD-20261028-28F958',
        'ORD-20261028-721C96',
        'ORD-20261028-73C464',
        'ORD-20261028-C4B64F',
        'ORD-20261028-5D5B78',
        'ORD-20261028-735449',
        'ORD-20261028-0F144B',
        'ORD-20261028-FB8459',
        'ORD-20261028-DAE373',
        'ORD-20261028-EB14D6',
    ],

    /**
     * Paid-order ShipStation sync mode (real/mock V2).
     *
     * - crm_preflight: GET /carriers + CRM integration row only (no POST /shipments).
     *   ShipStation dashboard will NOT show the order until manual Create Shipment.
     *
     * - pending_shipment_import: POST /v2/shipments (pending, no label). This is the
     *   documented V2 replacement for legacy createOrder; SS UI shows Awaiting Shipment.
     *   No automatic label or warehouse submission occurs on the paid-order path.
     *
     * ShipStation V2 does not document a separate order-only API independent of shipments.
     */
    'paid_order_sync_mode' => env('SHIPSTATION_PAID_ORDER_SYNC_MODE', 'crm_preflight'),

    /**
     * When false, scheduled/queued automatic label purchase and warehouse submission
     * for ShipStation are skipped. Manual admin Create Label / Force Submit are unaffected.
     */
    'auto_fulfillment_enabled' => filter_var(
        env('SHIPSTATION_AUTO_FULFILLMENT_ENABLED', false),
        FILTER_VALIDATE_BOOLEAN,
    ),

    /**
     * Global tracking-only synchronization (webhook → queue → V2 GET). Independent of pilot_auto_status_sync.
     */
    'tracking_sync' => [
        'enabled' => filter_var(env('SHIPSTATION_TRACKING_SYNC_ENABLED', false), FILTER_VALIDATE_BOOL),
        'shadow_mode' => filter_var(env('SHIPSTATION_TRACKING_SYNC_SHADOW', true), FILTER_VALIDATE_BOOL),
        'queue' => env('SHIPSTATION_TRACKING_SYNC_QUEUE', env('SHIPSTATION_QUEUE', 'default')),
        'fulfillment_updates' => filter_var(env('SHIPSTATION_TRACKING_SYNC_UPDATE_FULFILLMENT', false), FILTER_VALIDATE_BOOL),
        'delivery_dedup_ttl_seconds' => max(1, (int) env('SHIPSTATION_TRACKING_WEBHOOK_DEDUP_TTL', 120)),
        'include_refunded_orders' => filter_var(env('SHIPSTATION_TRACKING_SYNC_INCLUDE_REFUNDED', true), FILTER_VALIDATE_BOOL),
        'dispatch_recovery_enabled' => filter_var(env('SHIPSTATION_TRACKING_DISPATCH_RECOVERY', false), FILTER_VALIDATE_BOOL),
        'allowed_resource_url_hosts' => ['api.shipstation.com', 'ssapi.shipstation.com'],
        'lock_seconds' => max(30, (int) env('SHIPSTATION_TRACKING_SYNC_LOCK_SECONDS', 120)),
    ],
];
