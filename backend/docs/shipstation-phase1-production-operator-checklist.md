# ShipStation Phase 1 — Production Operator Checklist

**Use with:** `shipstation-phase1-production-rollout-runbook.md`  
**Planning only** — check boxes during **authorized** change windows only.

---

## A. Pre-flight (before any deploy)

**Environment**

- [ ] Confirmed target is **production** (hostname, `APP_ENV`, deployment ticket).
- [ ] Confirmed **not** using production credentials in non-prod docs.

**Git / release**

- [ ] Release commit/tag recorded: `________________`
- [ ] Diff reviewed for **unexpected PayPal** or payment file changes (must be **none** for Phase 1-only release).
- [ ] Pilot allowlist in `config/shipstation.php` is **only** `6591d63b-f76a-4786-95ef-46fa27c62956` (no expansion).

**Backups**

- [ ] Database backup completed (time: `________` UTC).
- [ ] Rollback artifact identified (previous release: `________`).

**Production baseline (read-only)**

- [ ] `php artisan migrate:status` — note if `2026_11_19_*` are **Pending** or **Ran**.
- [ ] Queue workers healthy; note queues: `________`.
- [ ] Redis/cache reachable (required for locks if multi-worker).
- [ ] Current `SHIPSTATION_TRACKING_SYNC_ENABLED` = `________` (expect `false`).
- [ ] Current `SHIPSTATION_TRACKING_SYNC_SHADOW` = `________` (expect `true`).
- [ ] Current `SHIPSTATION_TRACKING_DISPATCH_RECOVERY` = `________` (expect `false`).
- [ ] Current `SHIPSTATION_PILOT_AUTO_STATUS_SYNC` = `________` (document; do not change without approval).
- [ ] Webhook `POST /api/v1/webhooks/shipstation` reachable from ShipStation (TLS, WAF).
- [ ] Disk space adequate.

**ShipStation connectivity (read-only)**

- [ ] Admin connection test or logged GET health (no writes).
- [ ] Webhook secret configured (value **not** recorded in tickets).

**Queue / data hygiene**

- [ ] Failed jobs count: `________`.
- [ ] Backlog on fulfillment/default queues: `________`.
- [ ] `shipstation_webhook_receipts` exists? Y/N (pre-migrate = N expected).

---

## B. Stage 1 — Deploy code (flags stay safe)

- [ ] Deploy approved artifact only.
- [ ] **Do not** set `SHIPSTATION_TRACKING_SYNC_ENABLED=true` yet.
- [ ] Verify post-deploy: `SHIPSTATION_TRACKING_SYNC_ENABLED=false`.
- [ ] Verify: `SHIPSTATION_TRACKING_SYNC_UPDATE_FULFILLMENT=false`.
- [ ] Verify: `SHIPSTATION_TRACKING_DISPATCH_RECOVERY=false`.
- [ ] Restart workers per runbook (when authorized).
- [ ] Invalid webhook token → **401** (smoke).
- [ ] Valid webhook → **200** (tracking still off = no global queue or only existing pilot path).

---

## C. Stage 2 — Migrations (separate approval)

- [ ] Maintenance window communicated (if used).
- [ ] `php artisan migrate --force` executed.
- [ ] `shipstation_webhook_receipts` table present.
- [ ] `shipstation_integrations.tracking_sync_meta` column present.
- [ ] Application health check green.
- [ ] **Still** `SHIPSTATION_TRACKING_SYNC_ENABLED=false` unless Stage 3 approved same day.

---

## D. Stage 3 — Shadow activation (separate approval)

- [ ] Written approval reference: `________`.
- [ ] Set `SHIPSTATION_TRACKING_SYNC_ENABLED=true`.
- [ ] Keep `SHIPSTATION_TRACKING_SYNC_SHADOW=true`.
- [ ] Keep `SHIPSTATION_TRACKING_SYNC_UPDATE_FULFILLMENT=false`.
- [ ] Keep `SHIPSTATION_TRACKING_DISPATCH_RECOVERY=false`.
- [ ] Workers listening on tracking queue: `________`.
- [ ] Sample receipts reach `processed` in shadow.
- [ ] `shipstation_integration_logs` show `tracking_sync:shadow` / `would_apply`.
- [ ] Spot-check: order integration **tracking_number** unchanged on shadow samples.
- [ ] No unexpected PayPal tracking jobs from global webhooks.

---

## E. Monitoring (first 72h after Stage 3)

- [ ] Receipt failure rate acceptable.
- [ ] No sustained `outbox_status=pending` > 15 min.
- [ ] No growth of `claiming` without recovery.
- [ ] Queue backlog stable.
- [ ] Lock retry / `lock_busy` within expectations.
- [ ] Pilot order still syncs **synchronously** as before.

---

## F. Rollback (if needed)

- [ ] Set `SHIPSTATION_TRACKING_SYNC_ENABLED=false`.
- [ ] Document in-flight jobs count: `________`.
- [ ] Pilot env **unchanged**.
- [ ] **No** PayPal env or code changes for ShipStation rollback.
- [ ] If code rollback: previous release deployed.
- [ ] If migration rollback: only if table empty / data exported; DBA sign-off.

---

## G. Explicit prohibitions (confirm NOT done)

- [ ] No production PayPal API calls for this change.
- [ ] No PayPal file modifications in release.
- [ ] No ShipStation shipment/label/fulfillment **writes**.
- [ ] No pilot UUID changes.
- [ ] No warehouse mapping / routing changes.
- [ ] No `SHIPSTATION_TRACKING_SYNC_UPDATE_FULFILLMENT=true`.
- [ ] No recovery scheduler enabled without approval.

---

## H. Sign-off

| Role | Name | Date (UTC) | Stage completed |
|------|------|------------|-----------------|
| Operator | | | |
| Reviewer | | | |

**Notes:**

________________________________________________________________________________