# ShipStation Phase 1 — Production Rollout Runbook (Planning Only)

**Document type:** Deployment planning — **not an execution authorization**  
**Prepared (UTC):** 2026-11-23 (G1 package updated **2026-11-24**)  
**Status:** Planning complete; **no production operations performed** in preparation of this document  

**Local verification reference:** `phase1-tracking-sync-implementation-report.md` (48 Phase 1 tests, 148 assertions, local only)  
**G1 deployment package (not executed):** `shipstation-phase1-final-deployment-report.md`  
**G0 evidence pass:** Preflight §23–§25 · `restore-result.json` · Human items: `shipstation-phase1-g0-approval-checklist.md` · SSH: `shipstation-phase1-g0-ssh-deployment-access-plan.md`

---

## 1. Executive summary

This runbook describes how an **authorized operator** may later deploy ShipStation **Phase 1 global tracking synchronization** to production in a **controlled, flag-gated** manner.

Phase 1 adds: authenticated webhook ingress → durable **webhook receipts** (outbox) → queue job → **GET-only** ShipStation V2 reads → **shadow or gated** application via `ShipStationV2RemoteShipmentSyncApplier`. It is **independent** of the existing **synchronous pilot** (`6591d63b-f76a-4786-95ef-46fa27c62956`).

**Default production posture after code deploy (Stage 1):** Global tracking **disabled**; shadow **on** (inactive while disabled); fulfillment updates **off**; dispatch recovery **off**; pilot polling **off** unless already enabled for other reasons.

**This document does not authorize:** production deploy, migrations, env changes, container restarts, feature activation, scheduler changes, or any ShipStation write API use.

**PayPal isolation:** Global tracking does not register or call PayPal listeners. `SyncPayPalTrackingListener` remains on `FulfillmentSubmittedToWarehouse` only. **No PayPal module changes** are part of this rollout.

---

## 2. Deployment scope

### 2.1 In scope (Phase 1 tracking sync)

| Area | Behavior |
|------|----------|
| Webhook ingress | Existing `POST /api/v1/webhooks/shipstation` path; reshipment → pilot (sync) → global queue |
| Receipts / outbox | `shipstation_webhook_receipts` table; `DB::afterCommit` dispatch |
| Dedup | Stable delivery IDs; 120s body-hash window (global path only) |
| Job | `SyncShipStationTrackingJob` on configurable queue |
| API | `ShipStationV2ReadOnlyClient` — `getShipment`, `getLabel` only |
| Applier | `applyGlobalTrackingShipment` with `tracking_sync_meta` |
| Recovery | `php artisan shipstation:recover-webhook-receipts` (gated by env; **not scheduled by default** in repo) |
| Pilot | Unchanged synchronous allowlist behavior |

### 2.2 Explicitly out of scope

- PayPal tokens, Vault, billing, subscriptions, rebills, captures, refunds ( **no PayPal files** in deploy delta for Phase 1 isolation ).
- ShipStation shipment create, label purchase, fulfillment submit, warehouse mapping, routing changes.
- Pilot allowlist expansion.
- Enabling fulfillment updates via tracking sync (Stage 5 — separate approval).
- Fixing pre-existing `ShipStationLabelCreationTest` / `ShipStationFulfillmentPipelineTest` failures.

### 2.3 Production readiness caveat

Local/staging tests **do not** prove production infra (Redis lock semantics, multi-worker recovery, Filament manual workflows). Treat this runbook as **operational guidance**, not a certificate of production fitness.

---

## 3. File inventory (deploy package)

Operators should deploy **only the approved release artifact** (tag/commit). The following paths are **Phase 1–relevant** (verify against release diff; untracked local trees may differ from production Git baseline).

### 3.1 Migrations (must run in order)

| File | Depends on |
|------|------------|
| `database/migrations/2026_11_19_100000_create_shipstation_webhook_receipts_table.php` | `orders`, `fulfillments` tables exist |
| `database/migrations/2026_11_19_120100_add_tracking_sync_meta_to_shipstation_integrations_table.php` | `shipstation_integrations`, column `auto_sync_meta` (e.g. `2026_11_18_100000_*`) |

**Do not assume** these have run in production until `php artisan migrate:status` is checked on production (read-only inspection by authorized operator).

### 3.2 Application code

| Path | Role |
|------|------|
| `app/Models/ShipStationWebhookReceipt.php` | Receipt model |
| `app/Models/ShipStationIntegration.php` | `tracking_sync_meta` cast/fillable |
| `app/Console/Commands/RecoverShipStationWebhookReceiptsCommand.php` | Recovery CLI |
| `app/Modules/ShipStation/Jobs/SyncShipStationTrackingJob.php` | Queue worker |
| `app/Modules/ShipStation/Services/ShipStationWebhookIngress.php` | Ingress orchestration |
| `app/Modules/ShipStation/Services/ShipStationWebhookProcessor.php` | Webhook delegate |
| `app/Modules/ShipStation/Services/ShipStationWebhookDeliveryDedupService.php` | Dedup |
| `app/Modules/ShipStation/Services/ShipStationWebhookOrderResolver.php` | Order resolution |
| `app/Modules/ShipStation/Services/ShipStationWebhookReceiptRecoveryService.php` | Atomic recovery claim |
| `app/Modules/ShipStation/Services/ShipStationTrackingJobDispatcher.php` | Job dispatch seam |
| `app/Modules/ShipStation/Services/ShipStationTrackingSyncService.php` | Sync + lock |
| `app/Modules/ShipStation/Services/ShipStationTrackingSyncEligibilityService.php` | Eligibility |
| `app/Modules/ShipStation/Services/ShipStationSyncCoordinator.php` | Per-order cache locks |
| `app/Modules/ShipStation/Services/ShipStationV2ReadOnlyClient.php` | GET-only client |
| `app/Modules/ShipStation/Services/ShipStationV2RemoteShipmentSyncApplier.php` | Pilot + global apply |
| `app/Modules/ShipStation/Support/ShipStationTrackingSyncGate.php` | Feature gates |
| `app/Modules/ShipStation/Support/ShipStationShipmentSnapshot.php` | Shadow snapshots |
| `app/Modules/ShipStation/ShipStationServiceProvider.php` | DI registration |
| `app/Modules/ShipStation/Http/Controllers/ShipStationWebhookController.php` | HTTP entry |
| `app/Modules/ShipStation/Services/ShipStationWebhookAuthenticator.php` | Token auth |
| `app/Modules/ShipStation/routes.php` | `/webhooks/shipstation` |
| `config/shipstation.php` | `tracking_sync` block, pilot allowlist |

### 3.3 Configuration template (not production secrets)

| Reference | Variables |
|-----------|-----------|
| `backend/.env.example` | `SHIPSTATION_TRACKING_SYNC_*`, `SHIPSTATION_TRACKING_DISPATCH_RECOVERY`, `SHIPSTATION_TRACKING_WEBHOOK_DEDUP_TTL` |

Optional (config default only if unset): `SHIPSTATION_TRACKING_SYNC_LOCK_SECONDS` (config default **120**).

### 3.4 Documentation / tests (optional on server)

- `docs/phase1-*`, `tests/Feature/ShipStation/ShipStationPhase1*.php` — not required on production runtime.

---

## 4. Migration review

### 4.1 `shipstation_webhook_receipts` (new table)

**Columns (summary):** `uuid` (unique), `delivery_fingerprint` (indexed), `resource_type`, `resource_url`, `scope_type`, `scope_id`, `order_id` (FK → `orders`, null on delete), `fulfillment_id` (FK → `fulfillments`, null on delete), `status`, `outbox_status`, `dispatch_attempts`, timestamps, nullable failure/ignore fields, `payload_meta` JSON.

**Indexes:** Custom short names for MySQL identifier limits: `ss_wh_rcpt_status_created_idx`, `ss_wh_rcpt_scope_created_idx`.

**Operational impact:** `CREATE TABLE` — moderate metadata lock; estimate maintenance window or off-peak. No data backfill required.

**Rollback:** `down()` drops table. **Data loss** if receipts exist — only rollback before activation or after export.

### 4.2 `tracking_sync_meta` on `shipstation_integrations`

**Change:** Nullable JSON column after `auto_sync_meta`.

**Operational impact:** `ALTER TABLE` — brief lock on `shipstation_integrations`; no row updates required for deploy.

**Rollback:** `down()` drops column — loses global sync meta only (not pilot `auto_sync_meta`).

### 4.3 Pre-migration verification (production — read-only)

- [ ] `migrate:status` shows migrations **Pending** (not assumed).
- [ ] `orders` and `fulfillments` exist; `shipstation_integrations.auto_sync_meta` exists.
- [ ] MySQL version supports FK + index name lengths (MariaDB/MySQL 8+ typical).
- [ ] Disk space sufficient for new table growth.

---

## 5. Feature flag matrix

| Setting | Env variable | Safe default (repo) | Stage 1 deploy | Stage 3 shadow | Stage 5 (future) |
|---------|----------------|---------------------|----------------|----------------|------------------|
| Global tracking | `SHIPSTATION_TRACKING_SYNC_ENABLED` | `false` | **false** | **true** (with approval) | As approved |
| Shadow mode | `SHIPSTATION_TRACKING_SYNC_SHADOW` | `true` | **true** | **true** | false only with approval |
| Fulfillment updates | `SHIPSTATION_TRACKING_SYNC_UPDATE_FULFILLMENT` | `false` | **false** | **false** | separate gate |
| Dispatch recovery | `SHIPSTATION_TRACKING_DISPATCH_RECOVERY` | `false` | **false** | **false** | separate gate |
| Pilot sync | `SHIPSTATION_PILOT_AUTO_STATUS_SYNC` | `false` | **unchanged** | **unchanged** | unchanged |
| Pilot UUIDs | config array | single UUID | **unchanged** | **unchanged** | **never expand without approval** |
| Pilot polling | `SHIPSTATION_PILOT_STATUS_POLL` | `false` | **false** | **false** | separate approval |
| Dedup TTL | `SHIPSTATION_TRACKING_WEBHOOK_DEDUP_TTL` | `120` | 120 | 120 | tune with care |
| Lock TTL | `SHIPSTATION_TRACKING_SYNC_LOCK_SECONDS` | 120 (config) | default | default | tune with care |
| Tracking queue | `SHIPSTATION_TRACKING_SYNC_QUEUE` | fallback `SHIPSTATION_QUEUE` / `default` | explicit queue recommended | same | same |

**Cache / locks:** Per-order locks use Laravel `Cache::lock`. Production should use **Redis** (or another shared cache); array/file drivers are **unsuitable** for multi-worker consistency.

---

## 6. Queue, worker, and scheduler requirements

### 6.1 Queue

- Job class: `App\Modules\ShipStation\Jobs\SyncShipStationTrackingJob`
- Queue name: resolved from `shipstation.tracking_sync.queue`
- Retries: **5** tries, backoff **30, 60, 120, 300, 600** seconds
- Workers must consume the tracking queue (dedicated worker or multi-queue worker configuration)

### 6.2 Scheduler

- **Recovery command is not registered** in `routes/console.php` in the current repo.
- If operations later schedule `shipstation:recover-webhook-receipts`, that requires **separate approval** and must keep `SHIPSTATION_TRACKING_DISPATCH_RECOVERY=false` until explicitly enabled.

### 6.3 Webhook endpoint

- Route: `POST /api/v1/webhooks/shipstation` (module prefix `api/v1`)
- Auth: query `token` matching `SHIPSTATION_WEBHOOK_SECRET` (do not log token values)
- Throttle: `120/minute` per route middleware

---

## 7. Stage 0 — Backup and readiness

**Approval required:** Standard production change window.

| Step | Action |
|------|--------|
| 0.1 | Full database backup (point-in-time if available) |
| 0.2 | Application release snapshot / rollback artifact identified |
| 0.3 | Export current production env **keys only** (not values in shared docs) for ShipStation + pilot flags |
| 0.4 | Baseline: queue depth, failed jobs, `shipstation_integration_logs` rate |
| 0.5 | Confirm pilot order behavior documented (single UUID) |
| 0.6 | Rollback owner and communication plan assigned |

---

## 8. Stage 1 — Deploy code with global features disabled

**Approval gate:** Production deploy authorized.

| Step | Action |
|------|--------|
| 1.1 | Deploy approved commit/tag **without** changing tracking env flags |
| 1.2 | Confirm env: `SHIPSTATION_TRACKING_SYNC_ENABLED=false`, `SHIPSTATION_TRACKING_SYNC_SHADOW=true`, `SHIPSTATION_TRACKING_SYNC_UPDATE_FULFILLMENT=false`, `SHIPSTATION_TRACKING_DISPATCH_RECOVERY=false` |
| 1.3 | Confirm pilot env **unchanged** (allowlist only `6591d63b-f76a-4786-95ef-46fa27c62956` if pilot enabled) |
| 1.4 | Restart PHP-FPM / workers per standard deploy process (when authorized) |
| 1.5 | Smoke: webhook auth test (invalid token → 401), valid token → 200 (may create logs only if tracking disabled) |
| 1.6 | Verify manual shipment/label/fulfillment paths unchanged (operator checklist) |

---

## 9. Stage 2 — Migration (execute only when authorized)

**Approval gate:** Migration execution authorized (separate from code deploy).

| Step | Action |
|------|--------|
| 2.1 | Maintenance mode optional for conservative teams |
| 2.2 | `php artisan migrate --force` (only migrations `2026_11_19_*` if prior caught up) |
| 2.3 | Verify table `shipstation_webhook_receipts` exists; column `tracking_sync_meta` on `shipstation_integrations` |
| 2.4 | Verify application boots; no migration errors in logs |
| 2.5 | **Do not** enable `SHIPSTATION_TRACKING_SYNC_ENABLED` in same window unless Stage 3 approved |

**Rollback (migration):** `php artisan migrate:rollback --step=N` only if **no production receipt rows** matter; dropping `shipstation_webhook_receipts` destroys audit data. Prefer forward-fix over rollback after Stage 3.

---

## 10. Stage 3 — Shadow-mode activation

**Approval gate:** Explicit written approval for **global tracking shadow only**.

| Step | Action |
|------|--------|
| 3.1 | Set `SHIPSTATION_TRACKING_SYNC_ENABLED=true`; keep `SHIPSTATION_TRACKING_SYNC_SHADOW=true` |
| 3.2 | Keep `SHIPSTATION_TRACKING_SYNC_UPDATE_FULFILLMENT=false` |
| 3.3 | Keep `SHIPSTATION_TRACKING_DISPATCH_RECOVERY=false` |
| 3.4 | Ensure queue workers run on tracking queue |
| 3.5 | Monitor 24–72h (see §12) |
| 3.6 | Validate `tracking_sync:shadow` logs contain `would_apply`; CRM fields unchanged on sample orders |
| 3.7 | Confirm no `FulfillmentSubmittedToWarehouse` / PayPal tracking jobs from global path |

**Deactivation (fast):** Set `SHIPSTATION_TRACKING_SYNC_ENABLED=false` — ingress stops queueing global jobs; pilot/reshipment unaffected.

---

## 11. Stage 4 — Monitoring and evaluation

### 11.1 Data sources

| Source | What to watch |
|--------|----------------|
| `shipstation_webhook_receipts` | `status`, `outbox_status`, `failure_reason`, age |
| `shipstation_integration_logs` | `webhook_*`, `tracking_sync:shadow`, `tracking_sync:job` |
| Queue metrics | `SyncShipStationTrackingJob` depth, failures, latency |
| Cache locks | lock busy / retryable patterns in logs |
| Application logs | dispatch failures, API errors (no secrets) |

### 11.2 Proposed alert thresholds (not production settings)

| Signal | Proposed threshold | Severity |
|--------|-------------------|----------|
| Webhook 401 rate | > 10 / 15 min | Warning |
| Receipt `failed` count | > 5 / hour (non-pilot global) | Warning |
| Receipt `outbox_status=pending` age | > 15 min with tracking enabled | Warning |
| Receipt `outbox_status=claiming` age | > 2× recovery `--minutes` | Warning |
| Queue backlog (tracking queue) | > 100 for 10 min | Warning |
| Job permanent failure rate | > 3 same receipt_id | Critical |
| Shadow vs SS dashboard mismatch | manual sample audit | Info |

### 11.3 Evaluation queries (examples — run on production DB read-only)

```sql
-- Stuck outbox (adjust interval)
SELECT status, outbox_status, COUNT(*) 
FROM shipstation_webhook_receipts 
WHERE created_at > NOW() - INTERVAL 7 DAY 
GROUP BY status, outbox_status;

-- Recent failures
SELECT id, order_id, status, failure_reason, updated_at 
FROM shipstation_webhook_receipts 
WHERE status = 'failed' 
ORDER BY id DESC LIMIT 20;
```

---

## 12. Stage 5 — Future fulfillment updates (not authorized)

Enabling `SHIPSTATION_TRACKING_SYNC_UPDATE_FULFILLMENT=true` and/or `SHIPSTATION_TRACKING_SYNC_SHADOW=false` would allow CRM writes and may interact with fulfillment orchestration — potentially approaching `FulfillmentSubmittedToWarehouse` on **non-shadow** paths.

**Requirements before consideration:**

- Separate product/ops approval
- Extended shadow parity period
- PayPal tracking listener impact analysis (**read-only**; no PayPal code changes in Phase 1)
- Rollback tested on staging with fulfillment writes
- Pre-existing label/fulfillment test failures resolved or waived with sign-off

**This runbook does not enable Stage 5.**

---

## 13. Rollback plan

### 13.1 Feature rollback (preferred)

1. Set `SHIPSTATION_TRACKING_SYNC_ENABLED=false` (immediate stop for new global jobs).
2. Optionally drain queue: allow in-flight `SyncShipStationTrackingJob` to complete (shadow = no business writes while shadow true).
3. Leave pilot/reshipment env untouched.
4. Do **not** modify PayPal configuration.

### 13.2 Code rollback

1. Deploy previous release artifact.
2. If migrations already applied, **do not** drop tables unless receipts table is empty and ops agrees to lose audit trail.

### 13.3 Migration rollback (destructive)

- Drop `shipstation_webhook_receipts` only if empty or exported.
- Drop `tracking_sync_meta` column — loses global sync state only.

### 13.4 Queue safety

| Job state | Action |
|-----------|--------|
| Queued | Disable flag; jobs may still run but skip/w no-op when disabled |
| Processing | Completes with lock release in `finally` |
| Failed | Investigate per receipt; recovery stays off unless approved |

---

## 14. PayPal isolation statement

- Global tracking sync **does not** reference PayPal classes, jobs, or events in ingress/tracking services (verified in local code review).
- `SyncPayPalTrackingListener` is registered **only** on `FulfillmentSubmittedToWarehouse` in `PayPalServiceProvider`.
- Shadow global tracking **must not** dispatch that event (local E2E tests assert this).
- **Deploy package for Phase 1 must exclude PayPal module edits.** If release bundle includes PayPal changes, **stop** and reconcile scope.

---

## 15. Known limitations (unresolved)

1. True **multi-host** parallel recovery was **not** verified in CI (atomic DB claim implemented; OS-level concurrency not proven).
2. **OS-level worker termination** during lock hold was **not** tested.
3. **Filament manual UI** workflows were **not** manually verified.
4. `ShipStationLabelCreationTest` — **6 pre-existing failures** (invalid customer email guard).
5. `ShipStationFulfillmentPipelineTest` — **4 pre-existing failures**.
6. PHPUnit **exit code 2** with all Phase 1 assertions passing due to **risky-test** metadata (Mockery).

---

## 16. Approval gates summary

| Gate | Approver | Unlocks |
|------|----------|---------|
| G0 | Ops + Eng | Stage 0 backups |
| G1 | Eng lead | Stage 1 production deploy (flags off) |
| G2 | DBA/Ops | Stage 2 migrations |
| G3 | Product + Eng | Stage 3 `TRACKING_SYNC_ENABLED=true` shadow |
| G4 | Ops | Optional recovery scheduler + `DISPATCH_RECOVERY=true` |
| G5 | Executive/product | Non-shadow or fulfillment updates |

**No gate in this document authorizes PayPal or payment changes.**

---

## 17. Document control

| Item | Value |
|------|-------|
| Related docs | `phase1-tracking-sync-implementation-report.md`, `phase1-tracking-sync-baseline-tests.json` |
| Operator checklist | `shipstation-phase1-production-operator-checklist.md` |
| Production work performed preparing this doc | **NONE** |