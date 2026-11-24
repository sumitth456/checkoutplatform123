# ShipStation Phase 1 — G1 Deployment Package (Prepared — Not Executed)

**Recorded (UTC):** 2026-11-24T15:10:00Z  
**Gate:** G1 deployment package only — **no production operations**  
**G0 verdict:** **NOT READY** (see `shipstation-phase1-g0-production-preflight-report.md` §25) — **local isolated restore COMPLETE**; humans + **fresh pre-G1 backup** + CI green on release branch **PENDING**  
**G1 authorization:** **NOT GRANTED** — explicit operator approval required before any command on production

---

## 1. Purpose

This document is the **controlled rollout package** for ShipStation Phase 1 global tracking sync. It consolidates deploy scope, migrations, commands, smoke tests, rollback, and failure criteria from the runbook and G0 evidence. **Executing this package is forbidden** until G0 is **READY** and a separate G1 authorization is recorded.

---

## 2. Target release

| Item | Value |
|------|-------|
| Current production commit | `53abb6c17962703433fce760d04d9d48bb437820` |
| Phase 1 code on production today | **No** (`tracking_sync` absent from deployed `shipstation.php`) |
| **Proposed deploy commit (PR #2)** | `75169db3e63390825edfef8bc596200f1fd903ed` (`feat/shipstation-phase1-tracking-sync`) — supersede if a newer reviewed commit is merged |
| PR | https://github.com/sumitth456/checkoutplatform123/pull/2 |
| `origin/main` @ prepare time | `84367d8` |
| Pre-deploy backup artifact (known) | `.backups/shipstation-display-status-20261120/db-pre-deploy.sql.gz` (SHA-256 verified on host **2026-11-24**) |
| **Fresh pre-G1 backup** | **PENDING** — required at G1 window |

---

## 3. File-level change summary (Phase 1 scope)

See runbook §3. Deploy **only** the approved artifact; verify with `git diff` against `53abb6c`.

**Migrations (G2 — separate gate after G1 code deploy):**

1. `2026_11_19_100000_create_shipstation_webhook_receipts_table.php`
2. `2026_11_19_120100_add_tracking_sync_meta_to_shipstation_integrations_table.php`

**Core application (representative — full list in runbook):**

- `app/Modules/ShipStation/**` (ingress, dedup, jobs, read-only client, shadow applier, recovery)
- `app/Models/ShipStationWebhookReceipt.php`
- `app/Console/Commands/RecoverShipStationWebhookReceiptsCommand.php`
- `config/shipstation.php` (`tracking_sync` block)
- Tests: `tests/Feature/ShipStation/ShipStationPhase1*`

**Out of scope for this rollout:** PayPal modules, rebill logic, pilot allowlist changes, shipment create/label purchase paths.

---

## 4. Migration impact

| Migration | Risk | Mitigation |
|-----------|------|------------|
| Webhook receipts table | New table only; no column drops | Run during approved window; verify `migrate:status` before/after |
| `tracking_sync_meta` JSON column | Additive on `shipstation_integrations` | Backward compatible; pilot rows unchanged |

**Production migrations:** **NOT AUTHORIZED** in this document.

---

## 5. Docker image plan

1. Build `checkoutplatform-app` and `checkoutplatform-worker` from **approved commit** on build host.
2. Record image digests before deploy (compare to rollback tags below).
3. **Targeted** `docker compose` recreate **only** `checkout_app` and `checkout_worker` (not `mysql`, not unrelated stacks).

**Rollback images (known good on host):**

- `checkoutplatform-app:backup-stage1-pre-8427-20261022-182336`
- `checkoutplatform-worker:backup-stage1-pre-8427-20261022-182336`

---

## 6. Deployment commands (template — G1 authorized operator only)

```bash
# PRE: Verify fresh backup + checksum + rollback image (read-only checks)

cd /var/www/CheckoutPlatform
# Deploy approved artifact per existing process (no broad git reset)

docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml build app worker
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml up -d app worker

# POST code deploy, BEFORE migrate (G2 gate):
docker exec checkout_app php artisan migrate:status
# G2 only:
# docker exec checkout_app php artisan migrate --force

docker exec checkout_app php artisan config:cache
docker exec checkout_worker supervisorctl status
```

**Env posture after G1 (Stage 1 — no activation):**

- `SHIPSTATION_TRACKING_SYNC_ENABLED=false`
- `SHIPSTATION_TRACKING_SYNC_SHADOW=true`
- `SHIPSTATION_TRACKING_SYNC_UPDATE_FULFILLMENT=false`
- `SHIPSTATION_TRACKING_DISPATCH_RECOVERY=false`
- Pilot polling **unchanged** (`SHIPSTATION_PILOT_STATUS_POLL=false`)

---

## 7. Rollback commands (template)

```bash
docker tag checkoutplatform-app:backup-stage1-pre-8427-20261022-182336 checkoutplatform-app:latest
docker tag checkoutplatform-worker:backup-stage1-pre-8427-20261022-182336 checkoutplatform-worker:latest
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.prod.yml up -d app worker
docker exec checkout_app php artisan config:cache
```

DB rollback from `db-pre-deploy.sql.gz` is a **DBA-only** operation with separate approval.

---

## 8. Smoke tests (post-G1, read-only / safe)

| Check | Command / action |
|-------|------------------|
| App health | `docker ps`; `checkout_app` healthy |
| Routes | HTTP health / admin login (no payment) |
| Config | `config('shipstation.tracking_sync.enabled')` === false |
| Pilot | Single UUID `6591d63b-…`; polling false |
| Webhook auth | Invalid secret → 401 (staging or controlled test) |
| Shadow | No fulfillment column mutations when sync disabled |
| Queue | No unexpected `SyncShipStationTrackingJob` backlog |
| PayPal | No config/code drift in PayPal modules |

---

## 9. Failure criteria (stop deploy / stop G2)

- Deployed commit ≠ approved commit
- `tracking_sync.enabled` true without approval
- Worker not consuming queues
- Migration error / partial migration
- Unexpected pilot UUID or polling enabled
- PayPal or unrelated container restarted

---

## 10. Monitoring plan

- `failed_jobs` count trend (read-only; **no purge**)
- Worker logs for `SyncShipStationTracking`
- Webhook receipt table growth (after G2)
- `shipstation_integration_logs` for tracking sync op types

---

## 11. Post-deployment report

When G1 is authorized and completed, fill:

- Actual deploy commit and image digests
- Migration timestamps
- Config snapshot (keys only, no secrets)
- Smoke test results
- Operator sign-off reference

Use this file as the shell; archive under `.backups/` on host if required by ops policy.

---

## 12. Blockers before execution

| Blocker | Status |
|---------|--------|
| G0 READY | **No** |
| Local isolated restore | **COMPLETE** — 114 tables, SHA-256 `ee60da74…cff`, `G0_SKIP_IMPORT=1` verify exit 0 (2026-11-24) |
| Phase 1 tests on clean `origin/main` parity tree | **IN PROGRESS** — 48-test suite; CI + follow-up commits fixing `main` parity gaps |
| Fresh production backup | **PENDING** |
| Human owners / window / ticket | **PENDING** |
| G1 explicit authorization | **NOT GRANTED** |

**PayPal / pilot / unrelated apps:** Untouched by preparation of this package.