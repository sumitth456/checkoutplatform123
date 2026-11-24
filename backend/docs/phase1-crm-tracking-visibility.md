# ShipStation CRM tracking visibility

**Branch:** `feat/shipstation-phase1-tracking-sync`  
**Recorded:** 2026-11-24

## Data sources (read-only CRM)

| Field | Primary source | Shadow / preview |
|-------|----------------|------------------|
| Tracking number | `shipstation_integrations.tracking_number`, else `fulfillments.tracking_number` | `tracking_sync_meta.shadow_last_snapshot.tracking_number` (labeled preview) |
| Carrier / service | Integration + `ShipStationShipmentCarrierResolver` | Shadow snapshot codes |
| Status | `ShipStationDisplayStatusResolver` | Appends “(shadow preview)” when previewing |
| Shipment ID | `external_shipment_id` | — |
| Dates | `remote_shipped_at`, fulfillment `shipped_at` / `delivered_at`, payload label timestamps | Shadow `ship_date` only for display |
| Tracking URL | Fulfillment URL, payload URL, or carrier template from `shipping_carriers` | **No invented URLs** |
| History | `response_payload.events` / `tracking_events`, `tracking_sync_meta.sync_history`, optional `shipstation_integration_logs` | System sync rows only when no carrier events |

## UI location

**Admin → Orders → View order** (`ViewOrder`), in the order infolist **below “Order lifecycle”** — panel title **Shipment tracking**.

## Synchronization model

- **When `tracking_sync.enabled=false` (default):** CRM shows data already on the integration/fulfillment from label/submit flows; panel documents that global webhooks are off.
- **When `tracking_sync.enabled=true`:** **Webhook-driven** (`SHIP_NOTIFY` → receipt → `SyncShipStationTrackingJob` → GET shipment). Typical latency **seconds to a few minutes** after ShipStation delivers the webhook and the queue worker runs. **Not** described as instantaneous real-time.
- **Polling:** Only if `SHIPSTATION_PILOT_STATUS_POLL=true` (must remain **false** for Phase 1 gate). Global polling not used.

## Shadow mode

`tracking_sync.shadow_mode=true` (default): business columns (`tracking_number`, status enum) unchanged; CRM can show preview via `tracking_sync_meta` + copy button clearly marked shadow preview.

## Tests

- `OrderCrmShipmentTrackingPresenterTest` — 5 unit cases (tracking, pending, shadow, history, fulfillment fallback).
- Phase 1 feature suite re-run on clean checkout — see `phase1-test-run-2026-11-24.txt` and CI on PR #2.

## Not deployed

No production deploy, env change, or tracking activation in this work.