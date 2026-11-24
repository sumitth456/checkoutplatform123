# ShipStation Phase 1 — Gate G0 Production Preflight Report

**Recorded (UTC):** 2026-11-23  
**Gate:** G0 — Backup and readiness **assessment** (documentation only)  
**Production mutations performed preparing this report:** **NONE**

**Related documents:**  
`shipstation-phase1-production-rollout-runbook.md` · `shipstation-phase1-production-operator-checklist.md` · `phase1-tracking-sync-implementation-report.md` · `phase1-tracking-sync-baseline-tests.json`

---

## 1. Executive summary

This report records **read-only** production observations where available, repository-defined infrastructure expectations, and **G0 prerequisites** from the rollout runbook. It does **not** authorize Gate G1 (deploy), G2 (migrate), or G3 (shadow activation).

**Summary finding:** Production **platform health appears stable** (checkout containers reported healthy, workers running, disk not critically full). Phase 1 **database objects are not yet applied** on production (migrations `2026_11_19_*` absent from migration history tail). **G0 cannot be fully signed off** until an authorized operator completes **verified database/application backups**, **feature-flag baseline capture**, and **release/rollback identification** — none of which were verified as complete in this pass.

**G0 verdict (§25):** **G0 NOT READY** — technical restore proof **COMPLETE** (§23); **fresh pre-G1 backup**, **approved release commit**, and **human sign-off** still **PENDING**. **G1 NOT AUTHORIZED.**

**Closure pass (§16–§17, 2026-11-23):** Additional **read-only** production inspection captured feature/pilot baseline, release identifiers, queue/cache/redis evidence, **backup file metadata + SHA-256**, failed-jobs risk summary, and confirmed Phase 1 code **not yet deployed**.

**Final evidence verification (§18, 2026-11-23):** Re-checked repository docs, operator checklist, and production host **read-only**; **no** new backup, restore proof, owners, ticket, or sign-off supplied or locatable. **Authoritative verdict: §18.5.**

---

## 2. Scope and safety restrictions

| Constraint | This task |
|------------|-----------|
| Production deploy / migrate / rollback | **Not performed** |
| Env / flag changes | **Not performed** |
| Container restarts | **Not performed** |
| Queue dispatch / recovery commands | **Not performed** |
| ShipStation write APIs | **Not performed** |
| PayPal changes | **None** |
| Database writes | **None** |

**Closure pass (2026-11-23):** Additional read-only inspection via host probe script (removed after execution; not left on production). **No production mutations.** Earlier policy-blocked attempts superseded where evidence now exists in §16.

---

## 3. Production environment verification

| Item | Status | Evidence | Verification method | Operator action? |
|------|--------|----------|---------------------|------------------|
| Target is production | **VERIFIED** (partial) | `php artisan about`: Environment **production**, URL **virello.cloud**, Debug **OFF** | Read-only `docker exec checkout_app php artisan about` | No |
| Laravel / PHP version | **VERIFIED** | Laravel **13.21.1**, PHP **8.4.27**, Composer **2.10.3** | Same | No |
| Maintenance mode | **VERIFIED** | Maintenance **OFF** | Same | No |
| Deployed Git commit / release tag | **VERIFIED** (host) | Host repo `/var/www/CheckoutPlatform` → **`53abb6c`** (commit date **2026-07-28T19:54:42Z**); container still has **NO_GIT** | Read-only `git -C /var/www/CheckoutPlatform rev-parse` | **Yes** — record **post-G1** commit after Phase 1 deploy |
| Application path | **VERIFIED** (by reference) | Docker mount `/var/www/html` in `checkout_app` | `docker-compose.yml` + inspect | No |
| Config/route/view cache | **VERIFIED** | Config/Events/Routes/Views **CACHED** | `artisan about` | Note for deploy: `config:clear` / `config:cache` per standard process at G1 |
| Disk space | **VERIFIED** (partial) | Root FS **49%** used, **~100G** available on host `/` | Host `df -h` (read-only) | Monitor before G2 |
| PayPal module untouched | **VERIFIED** | No PayPal files modified in this task | Process constraint | No |

---

## 4. Docker and container health

**Expected stack** (runbook): `checkout_app`, `checkout_worker`, `checkout_scheduler`, `checkout_nginx`, `checkout_mysql`, `checkout_redis`.

| Container | Status | Evidence | Verification method | Operator action? |
|-----------|--------|----------|---------------------|------------------|
| `checkout_app` | **VERIFIED** | **Up (healthy)**, restart count **0** | `docker ps` / `docker inspect` (read-only) | No |
| `checkout_worker` | **VERIFIED** | **Up (healthy)**, restart **0**; 2 workers **RUNNING** | `docker ps`, `supervisorctl status` | No |
| `checkout_scheduler` | **VERIFIED** | **Up (healthy)**, restart **0** | `docker ps` | No |
| `checkout_nginx` | **VERIFIED** | **Up (healthy)** (observed ~5 weeks uptime class) | `docker ps` | No |
| `checkout_mysql` | **VERIFIED** | **Up (healthy)**, MySQL **8.4** image | `docker ps` | No |
| `checkout_redis` | **VERIFIED** | **Up (healthy)**, Redis **7-alpine** | `docker ps` | No |
| Unrelated containers | **NOT VERIFIED** | Not inspected | Operator confirms isolation | Optional |

**Note:** `checkout_worker` and `checkout_app` reported **~3 days** uptime vs **~4–5 weeks** for other services — recent worker/app recycle may be relevant for G1 planning (cause not investigated; read-only).

---

## 5. Queue readiness

**Repository definition** (`docker/supervisor/conf.d/laravel-worker.conf`):

- `queue:work **redis**` (no `--queue=` filter → **default** queue on Redis connection)
- Supervisor: `--tries=3`, `--timeout=120`, `--max-time=3600`, `WORKER_PROCESSES` default **2**

**Phase 1 job** (`SyncShipStationTrackingJob`): `tries=5`, backoff `[30,60,120,300,600]`, queue name from `config('shipstation.tracking_sync.queue')` (falls back to `SHIPSTATION_QUEUE` / `default`).

| Item | Status | Evidence | Verification method | Operator action? |
|------|--------|----------|---------------------|------------------|
| Queue connection (prod) | **NOT VERIFIED** | `.env.example` suggests `database`; Docker worker uses **redis** | Operator: read-only `config('queue.default')` | **Yes** |
| Workers consume Redis | **VERIFIED** (config-as-deployed) | Supervisor command uses **redis** | Inspect worker conf in image/mount | No |
| Dedicated tracking queue worker | **NOT VERIFIED** | Single `queue:work` without queue list | After G1, confirm `SHIPSTATION_TRACKING_SYNC_QUEUE` and whether worker command must add `--queue=` | **Yes** (before G3) |
| Failed jobs table / Horizon | **NOT VERIFIED** | Not queried | `SELECT COUNT(*) FROM failed_jobs` read-only | **Yes** |
| Current backlog depth | **NOT VERIFIED** | Not queried | Redis `LLEN` / Laravel metrics read-only | **Yes** |
| Recovery command scheduled | **VERIFIED** (repo) | Not in `routes/console.php` | Code review | No |
| Dispatching jobs during G0 | **N/A** | Prohibited | — | No |

---

## 6. Redis and distributed lock readiness

**Repository definition** (`docker-compose.yml`): Redis **7** with **AOF** (`appendonly yes`), `maxmemory 256mb`, `allkeys-lru`.

| Item | Status | Evidence | Verification method | Operator action? |
|------|--------|----------|---------------------|------------------|
| Redis container healthy | **VERIFIED** | `checkout_redis` healthy | `docker ps` | No |
| Production `CACHE_STORE` | **NOT VERIFIED** | Phase 1 locks require **shared** cache (`Cache::lock`) | Read-only `config('cache.default')` on prod | **Yes** |
| Locks suitable for multi-worker | **VERIFIED** (if Redis cache store) | Redis supports distributed locks; **array/file unsuitable** | Confirm cache store + worker count | **Yes** |
| `lock_seconds` (tracking) | **VERIFIED** (repo default) | Config default **120** (`SHIPSTATION_TRACKING_SYNC_LOCK_SECONDS`) | Config review; prod cached config may differ | Confirm at G3 |
| Multi-host app servers | **NOT VERIFIED** | Single Docker compose pattern in repo | Operator architecture diagram | **Yes** |
| Redis persistence adequacy | **PARTIAL** | AOF enabled in compose; production volume durability not audited | Ops backup/DR policy | **Yes** |

---

## 7. Database and migration status

**Phase 1 migrations:**

| Migration | Purpose |
|-----------|---------|
| `2026_11_19_100000_create_shipstation_webhook_receipts_table` | Receipt/outbox table |
| `2026_11_19_120100_add_tracking_sync_meta_to_shipstation_integrations_table` | JSON `tracking_sync_meta` |

| Item | Status | Evidence | Verification method | Operator action? |
|------|--------|----------|---------------------|------------------|
| `2026_11_19_*` applied | **VERIFIED** (not applied) | Production `migrate:status` tail shows last ShipStation-related migrations **`2026_11_18_*` only**; **no** `2026_11_19_*` rows observed | Read-only `php artisan migrate:status` | No |
| `shipstation_webhook_receipts` exists | **VERIFIED** (absent) | Implied **Pending** — table created by missing migration | `Schema::hasTable` read-only | **Yes** (confirm) |
| `tracking_sync_meta` column | **VERIFIED** (absent expected) | Same | `Schema::hasColumn` read-only | **Yes** |
| Dependency `auto_sync_meta` | **VERIFIED** | `2026_11_18_100000_add_auto_sync_fields_to_shipstation_integrations_table` **Ran** [22] | `migrate:status` | No |
| MySQL engine / version | **VERIFIED** (partial) | MySQL **8.4** container | `docker ps` | No |
| Migration lock / downtime risk | **VERIFIED** (planning) | New table + nullable column ALTER — low–moderate | Runbook §4 | Plan G2 window |
| `migrate` / `rollback` executed | **N/A** | **None** | — | No |

**Operator command (read-only, for sign-off):**

```bash
docker exec checkout_app php artisan migrate:status | grep 2026_11_19
docker exec checkout_app php artisan tinker --execute="echo Schema::hasTable('shipstation_webhook_receipts') ? 'yes' : 'no';"
```

---

## 8. Feature flag baseline

**Expected safe defaults** (runbook / `.env.example`):

| Setting | Expected | Production actual | Status |
|---------|----------|-------------------|--------|
| `SHIPSTATION_TRACKING_SYNC_ENABLED` | `false` | **Unknown** | **NOT VERIFIED** |
| Shadow (`SHIPSTATION_TRACKING_SYNC_SHADOW`) | `true` | **Unknown** | **NOT VERIFIED** |
| `SHIPSTATION_TRACKING_SYNC_UPDATE_FULFILLMENT` | `false` | **Unknown** | **NOT VERIFIED** |
| `SHIPSTATION_TRACKING_DISPATCH_RECOVERY` | `false` | **Unknown** | **NOT VERIFIED** |
| Pilot polling (`SHIPSTATION_PILOT_STATUS_POLL`) | `false` | **Unknown** | **NOT VERIFIED** |
| Pilot allowlist | `6591d63b-f76a-4786-95ef-46fa27c62956` only | **VERIFIED** (repo config) | Code `config/shipstation.php` — prod uses **cached config** | **Yes** (confirm prod cache) |
| `SHIPSTATION_PILOT_AUTO_STATUS_SYNC` | Document only | **Unknown** | **NOT VERIFIED** |

**Operator command (read-only, no secrets):**

```bash
docker exec checkout_app php artisan tinker --execute="echo json_encode([
  'tracking_enabled' => config('shipstation.tracking_sync.enabled'),
  'shadow' => config('shipstation.tracking_sync.shadow_mode'),
  'fulfillment_updates' => config('shipstation.tracking_sync.fulfillment_updates'),
  'recovery' => config('shipstation.tracking_sync.dispatch_recovery_enabled'),
  'pilot_enabled' => config('shipstation.pilot_auto_status_sync.enabled'),
  'pilot_uuids' => config('shipstation.pilot_auto_status_sync.order_uuids'),
  'pilot_poll' => config('shipstation.pilot_auto_status_sync.polling_enabled'),
]);"
```

**Do not** print `.env` or webhook/API secrets in tickets.

**Note:** Phase 1 tracking code may **not** be deployed on production yet; flags for undefined config keys may fall to repo defaults only **after** G1 deploy.

---

## 9. Backup readiness

| Item | Status | Evidence | Verification method | Operator action? |
|------|--------|----------|---------------------|------------------|
| DB backup exists for G0 window | **NOT VERIFIED** | No backup artifact inspected | Ops backup catalog | **Yes** |
| Backup timestamp recorded | **NOT VERIFIED** | — | Backup system | **Yes** |
| Restore test / integrity check | **NOT VERIFIED** | — | DBA procedure | **Yes** |
| Application/release backup | **NOT VERIFIED** | NO_GIT in container | Tag previous image / host checkout | **Yes** |
| Config backup (keys only) | **NOT VERIFIED** | — | Secure export of ShipStation-related env **keys** | **Yes** |

**Suggested operator commands (execute separately — mutating only on backup systems):**

```bash
# Example — adapt to org backup tooling (NOT executed in G0 doc task)
docker exec checkout_mysql mysqldump -u… -p… checkout_platform > backup_YYYYMMDD.sql
# Verify: ls -lh backup_YYYYMMDD.sql ; test restore on staging
```

**Do not claim backups exist until verified.**

---

## 10. Existing ShipStation baseline

| Item | Status | Evidence | Verification method | Operator action? |
|------|--------|----------|---------------------|------------------|
| ShipStation mode (`real`/`mock`) | **NOT VERIFIED** on prod | Repo supports both | Read-only `config('shipstation.mode')` | **Yes** |
| Webhook endpoint | **VERIFIED** (repo) | `POST /api/v1/webhooks/shipstation` | `routes.php` | Confirm WAF/TLS to virello.cloud |
| Webhook auth method | **VERIFIED** (repo) | Query `token` vs `SHIPSTATION_WEBHOOK_SECRET` | Code | Confirm secret **set** (not logged) |
| Global tracking receipts | **VERIFIED** | Table **not** present pre-migration | Migration status | N/A until G2 |
| Pending/failed receipt counts | **N/A** | Table absent | SQL after G2 only | — |
| Pilot synchronous behavior | **VERIFIED** (repo) | Ingress processes pilot before global queue | Code review | Confirm prod pilot env |
| Manual shipment/label/Filament | **NOT VERIFIED** | Local tests have pre-existing failures | Operator smoke + see implementation report | **Yes** |
| ShipStation GET smoke | **NOT VERIFIED** | Not authorized in G0 | Optional read-only connection test | Optional |
| ShipStation write APIs | **NONE** | Task prohibition | — | No |

---

## 11. Rollback readiness

| Item | Status | Evidence | Operator action? |
|------|--------|----------|------------------|
| Previous release identified | **NOT VERIFIED** | NO_GIT in container | **Yes** — record image digest / host commit |
| Backup location known | **NOT VERIFIED** | — | **Yes** |
| DB rollback safe pre-G2 | **VERIFIED** | No Phase 1 tables on prod yet | Forward-only deploy typical |
| DB rollback post-G2 | **REQUIRES OPERATOR ACTION** | Receipt data loss if table dropped | Export policy before rollback |
| Retain receipts if code rolled back | **VERIFIED** (planning) | Old code ignores new table | Harmless orphan table until drop approved |
| In-flight `SyncShipStationTrackingJob` | **REQUIRES OPERATOR ACTION** | Disable flag; drain queue plan in runbook §13 | Document at G1/G3 |
| Pilot unaffected on flag off | **VERIFIED** (repo) | `TRACKING_SYNC_ENABLED=false` stops global path only | No |

---

## 12. Outstanding risks

1. **Phase 1 code not on production** — migrations `2026_11_19_*` not in prod migration list; deploy artifact must be validated at G1.
2. **Feature flags unknown** on production cached config — must confirm before/after G1.
3. **Backups not verified** — **blocks G0 sign-off**.
4. **Worker queue naming** — supervisor listens to default Redis queue only; tracking queue env must align before G3.
5. **Worker supervisor `--tries=3`** vs job `tries=5` — Laravel job metadata governs job retries; understand combined behavior.
6. **Recent `checkout_app` / `checkout_worker` restarts** — investigate cause before G1 (ops).
7. **Multi-host recovery / locks** — not proven in production topology.
8. **Manual label/fulfillment test debt** — pre-existing failures in implementation report.
9. **Cached config** on prod — env changes at G3 require `config:cache` refresh per normal deploy practice.

---

## 13. G0 approval decision

| Criterion | Met? |
|-----------|------|
| G0 assessment documented | **Yes** (this file) |
| DB backup verified | **No** |
| App/config backup verified | **No** |
| Feature baseline captured on prod | **No** |
| Rollback release ID recorded | **No** |
| Infrastructure health acceptable | **Partial** (containers healthy) |
| PayPal untouched | **Yes** |
| No production mutations in G0 task | **Yes** |

### G0 verdict: **G0 NOT READY** (operator sign-off)

**Meaning (updated 2026-11-23):** Evidence collection **advanced** (§16). **Database backup verification**, **restore test**, **config backup**, and **named rollback/change owners** remain **unverified**. Operator sign-off requires completed `shipstation-phase1-g0-operator-evidence-checklist.md`.

**Blocked items:** None absolute — **REQUIRES OPERATOR ACTION** for backups and human assignments.

### G0 closure eligibility

| Outcome | Description |
|---------|-------------|
| **READY FOR OPERATOR SIGN-OFF** | Only after operator completes checklist §2–3, §9, restore test evidence |
| **Current status** | **NOT READY** |

---

## 14. G0 runbook prerequisite checklist (from Stage 0)

| Step | Runbook ref | Status |
|------|-------------|--------|
| 0.1 Full database backup | §7 | **REQUIRES OPERATOR ACTION** |
| 0.2 Application release snapshot | §7 | **REQUIRES OPERATOR ACTION** |
| 0.3 Env keys export (no secrets in docs) | §7 | **REQUIRES OPERATOR ACTION** |
| 0.4 Queue/logs baseline | §7 | **REQUIRES OPERATOR ACTION** |
| 0.5 Pilot UUID confirmed | §7 | **VERIFIED** (prod resolved config — §16) |
| 0.6 Rollback owner assigned | §7 | **REQUIRES OPERATOR ACTION** |

---

## 15. Prerequisites before G1 (summary)

1. Verified **database backup** + restore path.  
2. Recorded **release/rollback** identifier (host git or image digest).  
3. Read-only capture of **ShipStation tracking/pilot flags** on production.  
4. Confirm **Phase 1 code** in release bundle (no PayPal drift).  
5. Plan **worker queue** for `SyncShipStationTrackingJob` before G3 (not required for G1 deploy-with-flags-off).  
6. Assign **rollback owner** and change window.

**G1, G2, G3 remain separately approved and are not authorized by this document.**

---

## 16. G0 closure pass — 2026-11-23T17:45:00Z (read-only)

**Method:** Read-only SSH to production host; `docker inspect`, `docker exec checkout_app php artisan tinker`, `migrate:status`, host `git`, Redis `INFO`/`LLEN`. **No** backup creation, **no** writes, **no** restarts.

### 16.1 Prerequisite closure matrix

| Requirement | Previous | New status | Evidence (facts) | Operator action? |
|-------------|----------|------------|------------------|------------------|
| Database backup | NOT VERIFIED | **NOT VERIFIED** | `/var/backups` contains only **dpkg** archives — **no** application MySQL dump catalog found | **Yes** — create & record backup |
| Restore test | NOT VERIFIED | **NOT VERIFIED** | None observed | **Yes** |
| Application backup | NOT VERIFIED | **PARTIAL** | Docker image digests captured (§16.2); **no** separate app tarball verified | **Yes** |
| Config backup | NOT VERIFIED | **NOT VERIFIED** | Not inspected | **Yes** (keys only) |
| Current release ID | NOT VERIFIED | **VERIFIED** | Host git **`53abb6c`** @ `/var/www/CheckoutPlatform` | Confirm equals intended deploy |
| Image digest (app) | — | **VERIFIED** | `sha256:ce344fc6453b30ef9576958753a5b11ba949172d926f85a565d09df4f0097bb7` | Record for rollback |
| Image digest (worker) | — | **VERIFIED** | `sha256:c71e3af0a82a2d9efd6f02e9fc518945e5555d32f9362c0c625c8dd31144c6e5` | Record for rollback |
| Previous release ID | NOT VERIFIED | **NOT VERIFIED** | Not identified in this pass | **Yes** |
| Feature flags (tracking) | NOT VERIFIED | **NOT APPLICABLE** | `has_tracking_sync_config: **false**` — Phase 1 keys absent until G1 code deploy | Re-capture after G1 |
| Pilot allowlist | Partial | **VERIFIED** | `pilot_uuids`: **`["6591d63b-f76a-4786-95ef-46fa27c62956"]`** only | No |
| Pilot enabled | Unknown | **VERIFIED** | `pilot_enabled: **true**`, `pilot_poll: **false**` | Document; do not change in G0 |
| ShipStation mode | Unknown | **VERIFIED** | **`real`** | No |
| Queue connection | NOT VERIFIED | **VERIFIED** | `queue.default: **redis**` | No |
| Cache / locks | NOT VERIFIED | **VERIFIED** | `cache.default: **redis**`, `redis_client: **phpredis**` — suitable for `Cache::lock` | No |
| Queue backlog | NOT VERIFIED | **PARTIAL** | `jobs` SQL table **0**; Redis `queues:default` LLEN **0** on probed keys; db0 **363** keys (mixed use) | Optional LLEN audit per queue name |
| Failed jobs | NOT VERIFIED | **VERIFIED** | `failed_jobs` count **15264** (read-only COUNT) | Ops review before G1 |
| Migrations 2026_11_19 | Pending (inferred) | **VERIFIED** | **NO** lines in `migrate:status`; tables/columns **absent** | G2 after G1 |
| Rollback owner | NOT VERIFIED | **NOT VERIFIED** | — | **Yes** |
| Change window | NOT VERIFIED | **NOT VERIFIED** | — | **Yes** |

### 16.2 Container images (rollback reference)

| Service | Image digest (read-only) |
|---------|---------------------------|
| `checkout_app` | `sha256:ce344fc6453b30ef9576958753a5b11ba949172d926f85a565d09df4f0097bb7` |
| `checkout_worker` | `sha256:c71e3af0a82a2d9efd6f02e9fc518945e5555d32f9362c0c625c8dd31144c6e5` |

### 16.3 Phase 1 tracking configuration on production

Resolved `config()` at probe time:

```json
{
  "has_tracking_sync_config": false,
  "tracking_enabled": null,
  "shadow": null,
  "fulfillment_updates": null,
  "recovery": null,
  "tracking_queue": null,
  "lock_seconds": null,
  "receipts_table": false,
  "tracking_meta_col": false
}
```

**Interpretation:** Production artifact **predates** Phase 1 `tracking_sync` block in `config/shipstation.php`. G1 must deploy code before tracking env vars take effect. Expected safe env at G1: tracking **disabled**, shadow **true** (per `.env.example`).

### 16.4 Queue worker and retry behavior

**Observed supervisor command:**

`php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --timeout=120 --memory=256`

**No `--queue=` filter** → consumes **default** Redis queue only.

**Retry governance (repository — Laravel behavior, not load-tested on prod):**

| Layer | Setting | Applies to |
|-------|---------|------------|
| Supervisor worker | `--tries=3` | Default for jobs **without** own `$tries` |
| `SyncShipStationTrackingJob` | `public int $tries = 5` | Job class once deployed |
| Same job | `$backoff = [30, 60, 120, 300, 600]` | Between attempts |
| Same job | `lock_busy` path | `$this->release(30)` retryable |

Laravel resolves max attempts from the **job instance** (`$tries` on the class) when present; tracking sync should use **5** attempts after deploy, not supervisor default **3**.

**G3 note:** If `SHIPSTATION_TRACKING_SYNC_QUEUE` names a non-default queue, supervisor must be updated to include `--queue=` (separate approved change).

### 16.5 Database backup operator procedure (not executed)

Because no MySQL backup catalog was verified:

1. Execute org-approved **full logical backup** of production schema (e.g. approved `mysqldump` or snapshot tool).  
2. Record **filename/id**, **UTC timestamp**, **size**, **storage path**.  
3. Verify **checksum** or vendor integrity flag.  
4. **Restore test** to isolated staging; record staging DB name and test timestamp.  
5. Attach references to change ticket — **no** credentials in docs.

### 16.6 Outstanding risks after closure pass

1. **`failed_jobs` = 15264** — investigate separately; not introduced by Phase 1 planning.  
2. **Production code at `53abb6c` (2026-07-28)** — Phase 1 not in production binary until G1.  
3. **No verified MySQL backup** — **blocks G0 sign-off**.  
4. **Pilot auto sync enabled** in production — independent of Phase 1; **do not change** without separate approval.

### 16.7 Related artifact

Operator evidence form: `shipstation-phase1-g0-operator-evidence-checklist.md`

---

## 17. G0 evidence closure pass — 2026-11-23T18:15:00Z (read-only)

**Objective:** Close G0 evidence gaps where safe. **No** production mutations, **no** restores, **no** backup creation, **no** failed-job cleanup.

### 17.1 Mandatory readiness matrix (reassessment)

| Requirement | Status | Evidence | Provider |
|-------------|--------|----------|----------|
| Database backup (recent, recoverable) | **PARTIAL** | File exists (§17.2); **not** post–Nov 23; **restore unproven** | DBA/Ops |
| Restore test (isolated staging) | **PENDING** | Not performed | DBA/Ops |
| Application backup | **PARTIAL** | Docker image tags + `.backups/*/files` (§17.3) | Ops |
| Configuration backup | **PARTIAL** | `backend.env.snapshot` / `shipstation.php.snapshot` paths (§17.3); contents **not** opened | Ops |
| Rollback release (previous KGI) | **PARTIAL** | Image `checkoutplatform-app:backup-stage1-pre-8427-20261022-182336` (`db571708dbdb`) | Ops |
| Rollback procedure documented | **VERIFIED** | Runbook §13 + §17.4 | Eng |
| Deployment / rollback / DBA owners | **PENDING** | — | Management |
| Change window | **PENDING** | — | Management |
| Operator sign-off | **PENDING** | — | Operator |
| Failed jobs risk assessment | **VERIFIED** | §17.5 | Eng (read-only) |
| Phase 1 tracking flags | **NOT APPLICABLE** | Code not deployed (§16.3) | — |
| Pilot config preserved | **VERIFIED** | Unchanged: enabled, poll off, single UUID | — |

### 17.2 Database backup evidence (read-only)

**Search path:** `/var/www/CheckoutPlatform/.backups/*/db-pre-deploy.sql.gz`

| Artifact | Timestamp (host) | Size (bytes) | SHA-256 (file) |
|----------|------------------|--------------|----------------|
| **Latest located:** `shipstation-display-status-20261120/db-pre-deploy.sql.gz` | **2026-11-20 15:51** | **40,185,284** | `ee60da74242400e2cc4b530d355cdee922bb58f614beeba6e2a6811c2cb55cff` |
| Also present | 2026-11-19 | ~39.8M | (not hashed in this pass) |
| Also present | 2026-11-18 (pilot/reshipment) | ~37.4M | (not hashed in this pass) |

**Pre-deploy commit recorded in same folder:** `53abb6c17962703433fce760d04d9d48bb437820` (matches current production host HEAD).

**Gaps:**

- No backup dated **after** 2026-11-20 was found in the standard `.backups` tree.
- **Restore test not executed** (prohibited / not authorized in this task).
- **Cannot mark** “recoverable backup” **VERIFIED** without staging restore evidence.

**Operator action:** Create **fresh** pre–Phase 1-G1 backup, record checksum, run restore test to staging, attach ticket reference.

### 17.3 Application and configuration backup evidence

| Item | Status | Location (host) | Notes |
|------|--------|-----------------|-------|
| App Docker snapshot | **PARTIAL** | `checkoutplatform-app:backup-stage1-pre-8427-20261022-182336` | Oct 2026 tag on host registry |
| Current running app image | **VERIFIED** | `checkoutplatform-app:latest` → `ce344fc6453b` | |
| Deploy backup bundle | **PARTIAL** | `.backups/shipstation-display-status-20261120/files/` | Directory exists; not enumerated |
| Env snapshot | **PARTIAL** | Multiple `backend.env.snapshot` under `.backups/*` | **Not read** (secrets) |
| ShipStation config snapshot | **PARTIAL** | `.backups/.../shipstation.php.snapshot` | **Not read** |
| ShipStation env keys snapshot | **PARTIAL** | `backend.env.shipstation-keys.snapshot` | Keys-only naming; **not read** |

### 17.4 Rollback procedure (documentation only — not executed)

1. **Fast flag rollback (post–G3 only):** `SHIPSTATION_TRACKING_SYNC_ENABLED=false`; refresh config cache per deploy practice.  
2. **Code rollback:** Repoint `checkout_app` / `checkout_worker` / `checkout_scheduler` to image digest `sha256:db571708dbdb…` (tag `backup-stage1-pre-8427-20261022-182336`) **or** newer approved tag — **requires** compose/image change approval (not done here).  
3. **Pilot:** Leave env unchanged (`pilot_enabled=true`, single UUID).  
4. **Database:** If `2026_11_19_*` not yet migrated, code rollback needs **no** schema rollback. If migrated, see runbook §13 (export receipts before drop).  
5. **PayPal:** No PayPal env or image changes in rollback.

**Scope constraint:** Rollback of **only** checkout stack containers — do not touch `leadcrm_*`, `rot_*`, `mockup_*`.

### 17.5 Failed jobs risk assessment (read-only)

**Captured:** 2026-11-23 via `failed_jobs` SQL aggregates (no payload/customer data exported).

| Metric | Value |
|--------|-------|
| Total rows | **15,264** |
| Oldest `failed_at` | **2026-08-05 18:00:06** |
| Newest `failed_at` | **2026-11-23 16:00:04** |
| Queue | **default** only (15,264) |
| Connection | **redis** (15,264) |
| Exception text contains `PayPal` | **10,370** |
| Exception text contains `ShipStation` | **2,230** |
| Exception text contains `Fulfillment` | **2,857** |

**Recent sample (sanitized, IDs 15262–15264, 2026-11-23 16:00:04 UTC):**

- Class pattern: `InvalidArgumentException`
- Message pattern: `Subscription source order is not eligible for rebill (refunded or invalid payment state).`
- Location cited: `RebillService.php` (rebill path — **not** Phase 1 tracking sync)

**Relation to Phase 1 G1:** Failures are **pre-existing operational debt** on `default` queue. **Not** authorization to truncate or retry. Before G1, ops should decide whether backlog review is required independent of ShipStation Phase 1.

**Phase 1 tracking job:** Not present in production (`SyncShipStationTrackingJob` not deployed).

### 17.6 Human approvals (no fabrication)

| Field | Status |
|-------|--------|
| Deployment owner | **PENDING** |
| Rollback owner | **PENDING** |
| Database / restore owner | **PENDING** |
| Approved change window (UTC) | **PENDING** |
| Operator sign-off | **PENDING** |
| Approval ticket / reference | **PENDING** |

### 17.7 Final G0 verdict (this pass)

**G0 NOT READY**

Mandatory blockers:

1. **No verified staging restore** of a current production backup.  
2. **No fresh backup** timestamp covering planned G1 window (latest found: **2026-11-20**).  
3. **Human owners** and **change window** not recorded.

**G1 is NOT AUTHORIZED.**

**Next action:** Operator completes `shipstation-phase1-g0-operator-evidence-checklist.md` §2–3, §9; DBA runs restore test; management assigns owners and change window.

---

## 18. G0 final evidence verification — 2026-11-23T17:30:00Z

**Objective:** Independent review of **supplied** DBA/Ops/Management evidence against mandatory G0 requirements. **No** production mutations.

### 18.1 Evidence sources searched

| Source | Method | Result |
|--------|--------|--------|
| `shipstation-phase1-g0-operator-evidence-checklist.md` | Document review | §2, §3, §9 still **PENDING** (no names, tickets, restore timestamps) |
| `shipstation-phase1-g0-production-preflight-report.md` | Document review | §17.6 human fields **PENDING**; no §supplement with attachments |
| Repository (`backend/docs`, attachments) | Glob / grep | **No** separate DBA restore report, management approval memo, or ticket export |
| Production host (read-only SSH) | `find` on `.backups` | Newest `db-pre-deploy.sql.gz` still **2026-11-20 15:51 UTC**; **no** `*restore*` / `*staging*` proof files under `.backups` |
| Secrets scan | Review edited sections | **No** secrets added to docs |

**Conclusion:** The task referenced “newly supplied” evidence, but **no verifiable artifacts** (checksum-matched fresh backup, staging restore log, signed checklist fields, ticket ID) appear in the G0 documents or standard production backup paths. Statements of completion **without** recorded evidence are **not** accepted per verification rules.

### 18.2 Mandatory requirement classification (final)

| # | Requirement | Status | Verification basis |
|---|-------------|--------|-------------------|
| 1 | Fresh pre-G1 database backup | **PENDING** | Host newest dump **2026-11-20** (`§17.2`); **no** post–2026-11-23 artifact |
| 2 | Isolated staging restore + checksum proof | **PENDING** | **No** restore timestamp, staging DB name, or verification log in repo or `.backups` |
| 3 | Application & configuration recovery procedure | **PENDING** | Snapshot **paths** partial (`§17.3`); **“Config recovery procedure reviewed”** not completed |
| 4 | Named deployment / rollback / DBA owners | **PENDING** | Checklist §9; **not invented** |
| 5 | Approved UTC change window + ticket/reference | **PENDING** | Checklist §9; **not invented** |
| 6 | Operator sign-off | **PENDING** | Checklist §9, §10 unchecked |

**Supporting (non-blocking but documented):**

| Requirement | Status | Notes |
|-------------|--------|-------|
| Rollback procedure documented | **VERIFIED** | Runbook §13 + `§17.4` |
| Rollback release (KGI image) | **VERIFIED** | Tag `backup-stage1-pre-8427-20261022-182336` on host (`checklist §3`) |
| Current release ID / digests | **VERIFIED** | `§16.2`, checklist §4 |
| Failed jobs risk assessment | **VERIFIED** | `§17.5` |
| Pilot preserved | **VERIFIED** | Prior read-only `§16.1`; **not** reconfigured in this pass |
| Phase 1 tracking on prod | **NOT APPLICABLE** | Code/flags absent until G1 |
| Infrastructure health | **VERIFIED** | `§3–§4` (historical read-only) |

**Note on “PARTIAL” backups:** Existence of `db-pre-deploy.sql.gz` (**2026-11-20**) and Docker snapshots does **not** satisfy mandatory items **1** (fresh pre-G1) or **2** (proven restore). Those remain **PENDING**.

### 18.3 Document inconsistencies (reconciled)

| Topic | Location A | Location B | Resolution |
|-------|------------|------------|------------|
| DB backup | §9: **NOT VERIFIED** (no artifact) | §17.2: file **located** | §16.1 initially missed `.backups/` tree; §17.2 corrects **existence** only. **Recoverable + fresh** remain **PENDING** (§18.2). |
| DB backup | §16.1 matrix: **NOT VERIFIED** | §17.1: **PARTIAL** | **PARTIAL** = metadata only; G0 mandatory = **PENDING**. |
| Previous release | §16.1: **NOT VERIFIED** | Checklist §3: KGI image **Verified** | §16.1 predates §17.3; KGI tag **VERIFIED** for rollback reference. |
| Feature flags §8 | **NOT VERIFIED** | §16.3: tracking **N/A** | §16.3 supersedes for tracking keys; pilot/mode **VERIFIED** in §16.1. |
| §13 decision table | DB backup **No** | §17 **PARTIAL** | §13 unchanged (sign-off **No**); aligns with **G0 NOT READY**. |

### 18.4 Staging restore vs production

- **No** restore operation was performed or evidenced in this verification pass.
- **No** evidence that any restore targeted **production** MySQL (none observed).
- Until item **2** is **VERIFIED**, recoverability remains unproven.

### 18.5 Final G0 verdict

**G0 NOT READY**

**Exact missing evidence:**

1. Pre-G1 MySQL backup file (timestamp, size, SHA-256) **after** planned G0 closure date.  
2. Staging restore record (environment name, UTC time, checksum match, DBA attestation).  
3. Signed-off config/application recovery procedure (reference to runbook step, not secret contents).  
4. Deployment owner, rollback owner, DBA owner (**named**).  
5. Approved change window (UTC) and approval ticket/reference.  
6. Operator sign-off on checklist §10–§11.

**Who must provide:** DBA/Ops (1–3), Management (4–5), authorized operator (6).

**G1 remains NOT AUTHORIZED** — even if G0 later becomes READY, G1 requires **separate** explicit authorization (runbook §7+).

### 18.6 Production actions (this verification pass)

**NONE** — read-only SSH backup `find` only; documentation updates in repository only.

---

## 19. Cursor-managed G0 automation pass — 2026-11-23T17:15:00Z (local + policy)

**Policy:** **No** SCP/upload of bundled scripts to production. **No** production SSH in this pass (automation bundle declined; follow individually reviewed commands in §19.3). **No** PayPal, pilot, migration, queue, or container changes.

### 19.1 `g0-readonly-automation.sh` — command inventory and classification

Reference file: `backend/scripts/g0-readonly-automation.sh` (marked **REFERENCE ONLY** — decompose before any host use).

| Lines | Command / action | Category | Impact if run on production host |
|-------|------------------|----------|-----------------------------------|
| 5–9 | `set -euo pipefail`, paths, UTC timestamp echo | **1 Local** (meta) | None |
| 12 | `ls -la` on `db-pre-deploy.sql.gz` | **2 Prod read-only** | Reads file metadata only |
| 13–15 | `sha256sum`, compare to expected SHA | **2** | Reads entire backup file (I/O; **no** writes) |
| 16 | `gzip -t` | **2** | Decompresses stream to verify integrity (**no** extract to disk) |
| 19–22 | `zcat \| head`, `grep CREATE TABLE` | **2** | Reads compressed dump (**no** DB connection) |
| 25 | `find … db-pre-deploy.sql.gz` | **2** | Lists backup files |
| 28 | `ls /var/backups/CheckoutPlatform` | **2** | Lists DR backup dirs |
| 31–33 | `git rev-parse`, `git log`, `cat pre-deploy-commit.txt` | **2** | Reads git / text files |
| 36–38 | `docker inspect` checkout_* containers | **2** | Reads container metadata |
| 41–43 | `docker image inspect` rollback/latest tags | **2** | Reads local image metadata |
| 46–50 | `find` env/config snapshots; count files in bundle | **2** | Lists paths; **does not** read `.env` contents |
| 53–59 | `docker ps -a`, `ls /var/www`, `docker compose ls` | **2** | Lists containers/projects (all apps on host visible) |
| 62–76 | `docker exec checkout_app php artisan tinker` → `config()` JSON | **2** | **Read-only** PHP config resolution; **no** env/flag writes |
| 79 | `migrate:status \| grep 2026_11_19` | **2** | Reads migration table via Laravel (**no** migrate) |
| 82–104 | `tinker` → `failed_jobs` SELECT aggregates | **2** | **Read-only** SQL via Eloquent; **no** retry/delete |
| 107–109 | Test `-f` backup script paths on host | **2** | File existence only |

**Category 3 — not in script (separate approval):**

| Operation | Repo entry point | Why approval required |
|-----------|------------------|----------------------|
| Fresh production DB backup | `scripts/production-backup.sh`, `backend/scripts/backup-production-db.sh`, deploy `deploy-shipstation-display-status-backup-prod.sh` | `mysqldump` / compose exec against **production** MySQL (load + I/O) |
| Staging restore | `scripts/production-backup.sh` manifest `RESTORE.md` | Overwrites **staging** DB/data if run |
| Container image rollback | Runbook §13.2 | Changes running checkout containers |
| Queue recovery / failed_job cleanup | — | **Prohibited** for G0 |

### 19.2 Local repository checks executed (Category 1)

| Check | Method | Result (UTC 2026-11-23) | Status |
|-------|--------|-------------------------|--------|
| Approved backup scripts present | File inventory on workstation | `scripts/production-backup.sh` (12,573 B), `backend/scripts/backup-production-db.sh` (881 B), `backend/scripts/deploy-shipstation-display-status-backup-prod.sh` (1,824 B) | **VERIFIED** |
| Full DR backup + restore doc | Read `scripts/production-backup.sh` | Embeds `RESTORE.md` (DB gunzip→mysql, app tar, volumes, compose up); warns against prod restore | **VERIFIED** (documented procedure; **not** executed) |
| Deploy-time backup pattern | Read `deploy-shipstation-display-status-backup-prod.sh` | Writes `/.backups/<tag>/db-pre-deploy.sql.gz`, file snapshots, `shipstation.php.snapshot`; uses `docker exec checkout_mysql mysqldump` | **VERIFIED** (pattern; **not** run) |
| Isolated staging compose/project | Glob repo | **No** `docker-compose*staging*`; staging references are env **staging** temp files in deploy scripts only | **BLOCKED** (staging not defined in repo) |
| Phase 1 safe env defaults (repo) | `config/shipstation.php` | `tracking_sync.enabled` default **false**; `shadow_mode` default **true**; `fulfillment_updates` / `dispatch_recovery` default **false**; pilot allowlist includes `6591d63b-f76a-4786-95ef-46fa27c62956` | **VERIFIED** (repo; prod re-confirm via §19.3 when authorized) |
| Rollback scope (Virello checkout) | Runbook §13 + compose service names | Rollback targets `checkout_*` stack only; unrelated containers called out in §17.4 | **VERIFIED** (documented) |
| Human owners / ticket / window | Grep repo docs | **No** named owners or ticket IDs outside **PENDING** placeholders | **PENDING** |
| Automation script marked reference-only | Edit `g0-readonly-automation.sh` header | Banner: do not SCP/run as bundle on production | **VERIFIED** |
| Prior recorded backup SHA | Checklist §2 vs script constant | Expected `ee60da74…cff` — **not re-hashed on host in this pass** | **PENDING** (re-verify with §19.3.1 when SSH approved) |

**Evidence location:** This section; local paths under `c:\CheckoutPlatform\`.

### 19.3 Production read-only command menu (Category 2 — not executed in §19 pass)

Run **one command at a time** after impact review. None mutate data if used as written.

| ID | Purpose | Command (on production host) | Impact |
|----|---------|------------------------------|--------|
| **19.3.1** | Recheck backup SHA | `sha256sum /var/www/CheckoutPlatform/.backups/shipstation-display-status-20261120/db-pre-deploy.sql.gz` | Read file |
| **19.3.2** | Gzip integrity | `gzip -t /var/www/CheckoutPlatform/.backups/shipstation-display-status-20261120/db-pre-deploy.sql.gz && echo OK` | Read/decompress verify only |
| **19.3.3** | SQL header sample | `zcat …/db-pre-deploy.sql.gz \| head -n 25` | Read dump |
| **19.3.4** | Newest deploy backups | `find /var/www/CheckoutPlatform/.backups -name db-pre-deploy.sql.gz -printf '%Tc %p %s\n' \| sort -r \| head -5` | List files |
| **19.3.5** | Pilot/tracking flags | `docker exec checkout_app php artisan tinker --execute='echo json_encode([...]);'` (keys per §16.3 — **no** secrets) | Read `config()` only |
| **19.3.6** | Failed jobs aggregates | Same pattern as §17.5 / script L82–104 | Read-only `failed_jobs` |
| **19.3.7** | Staging discovery | `docker ps -a --format '{{.Names}}'` then grep stage/staging | List containers |
| **19.3.8** | Rollback image present | `docker image inspect checkoutplatform-app:backup-stage1-pre-8427-20261022-182336 --format '{{.Id}}'` | Read image metadata |

**Do not** upload or execute `g0-readonly-automation.sh` as a single bundle on production.

### 19.4 Operations requiring explicit approval (Category 3)

| Operation | Owner role | Approval needed |
|-----------|------------|-----------------|
| Fresh pre-G1 `mysqldump` / `production-backup.sh` | DBA/Ops | Written OK for prod DB read + disk write |
| Restore backup into **isolated** staging DB | DBA/Ops | Staging target named; proof not prod; overwrite policy |
| G1 code deploy / worker restart | Eng + Ops | G1 gate (separate from G0) |
| G2 migrations | DBA/Ops | G2 gate |
| Any failed_job purge/retry | Ops | Out of G0 scope |

**Staging restore status:** **BLOCKED** until (a) an isolated staging stack is **proven** (§19.3.7 + separate compose/DB name), and (b) restore approval granted.

### 19.5 Mandatory G0 matrix (after local pass)

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Fresh pre-G1 DB backup | **PENDING** | Latest known **2026-11-20** (§17.2); not refreshed in §19 |
| Staging restore verified | **BLOCKED** | No staging definition in repo; restore not run |
| Recoverable backup (proven) | **PENDING** | Requires successful staging restore |
| Config/app recovery procedure | **PARTIAL** | Repo `RESTORE.md` pattern **VERIFIED**; operator sign-off **PENDING** |
| Rollback image/docs | **VERIFIED** | §17.3–§17.4, runbook §13 (prior host evidence) |
| Human owners / window / ticket / sign-off | **PENDING** | §18.2 |
| Pilot/tracking unchanged on prod | **VERIFIED** (stale) | §16.1 — **re-confirm** with 19.3.5 when SSH allowed |
| Failed jobs assessed | **VERIFIED** (stale) | §17.5 — optional refresh via 19.3.6 |

### 19.6 Final G0 verdict (this pass)

**G0 NOT READY**

**Completed by Cursor:** Category **1** local script/runbook/staging discovery; automation script decomposed and restricted; documentation updated.

**Not completed (by policy or blocker):** Production Category **2** commands; Category **3** backup/restore.

**G1 NOT AUTHORIZED.**

**Next safe actions:** (1) Operator runs §19.3.1–19.3.2 individually; (2) Management fills checklist §9; (3) Request approval for Category **3** fresh backup + staging restore.

---

## 20. G0 read-only verification phase — §19.3 execution (2026-11-23)

**Policy:** Individual commands only; **no** bundled `g0-readonly-automation.sh`; **no** backups/restores/flags/container changes.

### 20.1 §19.3.1 — Backup SHA-256 (VERIFIED)

| Field | Value |
|-------|-------|
| Executed (UTC) | **2026-11-23T18:11:48Z** |
| Command | `sha256sum /var/www/CheckoutPlatform/.backups/shipstation-display-status-20261120/db-pre-deploy.sql.gz` |
| Side effects | Reads backup file bytes for hashing only |
| SHA-256 | `ee60da74242400e2cc4b530d355cdee922bb58f614beeba6e2a6811c2cb55cff` |
| Match to §17.2 / checklist §2 | **Yes** |

### 20.2 §19.3.2 — Gzip integrity (VERIFIED)

| Field | Value |
|-------|-------|
| Executed (UTC) | **2026-11-23T18:11:56Z** |
| Command | `gzip -t …/db-pre-deploy.sql.gz && echo OK` |
| Side effects | Decompresses stream in memory for CRC check; **no** extract |
| Result | **gzip_integrity=OK** |

**Note:** Integrity confirmed for the **2026-11-20** artifact; does **not** satisfy “fresh pre-G1 backup” or “recoverable via restore test.”

### 20.3 §19.3.5 — Pilot / ShipStation configuration (VERIFIED)

| Field | Value |
|-------|-------|
| Executed (UTC) | **2026-11-23T18:18:58Z** (approx., piped `docker exec … tinker`) |
| Method | `docker exec -w /var/www/html checkout_app php artisan tinker` → `config()` JSON (**read-only**) |
| Side effects | Laravel bootstrap + config read; **no** writes |

| Key | Resolved value | G0 expectation |
|-----|----------------|----------------|
| `pilot_auto_status_sync.enabled` | **true** | Unchanged (document only) |
| `pilot_auto_status_sync.polling_enabled` | **false** | Must stay false |
| `pilot_auto_status_sync.order_uuids` | **`["6591d63b-f76a-4786-95ef-46fa27c62956"]`** | Single UUID |
| `shipstation.mode` | **`real`** | — |
| `tracking_sync` block in deployed config | **absent** (`has_tracking_sync`: **false**) | Phase 1 not on prod; tracking env **N/A** until G1 |

**PayPal / write APIs:** Not invoked.

### 20.4 §19.3.6 — Failed jobs refresh (VERIFIED)

| Field | Value |
|-------|-------|
| Executed (UTC) | **2026-11-23T18:20:22Z** |
| Method | `tinker` → Eloquent `failed_jobs` **SELECT** aggregates only |
| Side effects | Read-only SQL |

| Metric | Value |
|--------|-------|
| Total | **15,282** (↑18 vs §17.5) |
| Oldest `failed_at` | **2026-08-05 18:00:06** |
| Newest `failed_at` | **2026-11-23 18:00:07** |
| Exception text `PayPal` | **10,388** |
| `ShipStation` | **2,230** |
| `Fulfillment` | **2,857** |
| `RebillService` | **10,388** |
| `SyncShipStationTracking` | **0** |

**Phase 1 rollout risk:** No failed `SyncShipStationTracking` jobs (job not deployed). Pre-existing PayPal/rebill debt on `default` queue remains an **ops** concern, not G0 authorization for cleanup.

### 20.5 §19.3.7 — Staging discovery (BLOCKED)

| Field | Value |
|-------|-------|
| Executed (UTC) | **2026-11-23T18:20:54Z** |
| Commands | `docker ps -a`, `docker compose ls`, `ls /var/www/*/` |
| Side effects | Lists containers and directories only |

| Finding | Detail |
|---------|--------|
| Checkout compose project | **`checkoutplatform`** — `checkout_app`, `checkout_worker`, `checkout_mysql`, `checkout_redis`, etc. |
| Other projects on host | `leadcrm`, `mockup`, `rot` (**unrelated**) |
| Containers matching `stage`/`staging`/`dev`/`test` in name | **None** for CheckoutPlatform |
| `/var/www` | Includes `CheckoutPlatform` only (no second checkout tree) |

**Conclusion:** **No proven isolated CheckoutPlatform staging stack** on this host. Staging restore test remains **BLOCKED** until a dedicated non-production DB/host is identified and approved.

### 20.6 Mandatory matrix update + verdict

| Requirement | Status after §20 |
|-------------|------------------|
| Fresh pre-G1 DB backup | **PENDING** |
| Backup checksum (existing file) | **VERIFIED** (§20.1–§20.2) |
| Staging restore proof | **BLOCKED** (§20.5) |
| Pilot/flags unchanged | **VERIFIED** (§20.3) |
| Failed jobs assessed | **VERIFIED** (§20.4) |
| Human owners / window / sign-off | **PENDING** |

### **G0 NOT READY** · **G1 NOT AUTHORIZED**

### 20.7 Production mutations in this phase

**None.**

---

## 21. G0 local Windows restore (isolated) — 2026-11-23

**Reference:** `shipstation-phase1-g0-local-restore-report.md` (full detail).

| Item | Status |
|------|--------|
| Isolated compose `virello-g0-local-restore` | **VERIFIED** (prepared locally) |
| Backup on workstation | **BLOCKED** — not present |
| Local restore executed | **PENDING** (operator copies backup + runs script) |
| Production | **NOT TOUCHED** |

**G0 staging-restore evidence:** Host VPS **BLOCKED** (§20.5). **Local isolated restore** may satisfy **recoverability proof** only after successful script run — does **not** replace fresh prod backup or human sign-off.

**Verdict unchanged:** **G0 NOT READY** · **G1 NOT AUTHORIZED**

---

## 22. Autonomous G0/G1 preparation pass — 2026-11-24

**Recorded (UTC):** 2026-11-24T14:15:00Z  
**Evidence file:** `.local/g0-restore/evidence/autonomous-pass-20261124.json`  
**Production mutations:** **NONE**

### 22.1 Phase 0 — project discovery

| Item | Result |
|------|--------|
| Repository | `c:\CheckoutPlatform` |
| Local Git HEAD | `84367d8` (main; **dirty** — large untracked Phase 1 / unrelated work preserved) |
| Production baseline | `53abb6c` (unchanged) |
| Phase 1 implementation report | `phase1-tracking-sync-implementation-report.md` (48/48 tests, local, 2026-11-23) |

### 22.2 Phase 1 — production baseline refresh (read-only)

| Item | Result | Method |
|------|--------|--------|
| `checkout_*` containers | Up healthy | SSH `docker ps` **2026-11-24** |
| Unrelated stacks | `leadcrm_*`, `rot_*`, `mockup_*` — not modified | Same |
| Host Git HEAD | `53abb6c17962703433fce760d04d9d48bb437820` | SSH |
| App image | `sha256:ce344fc6453b30ef9576958753a5b11ba949172d926f85a565d09df4f0097bb7` | SSH |
| Worker image | `sha256:c71e3af0a82a2d9efd6f02e9fc518945e5555d32f9362c0c625c8dd31144c6e5` | SSH |
| `tracking_sync` on prod | **Absent** (`grep` count **0** on deployed `shipstation.php`) | SSH |
| Backup on host | **40,185,284** bytes; SHA-256 **matches** expected | SSH `sha256sum` **2026-11-24** |
| Failed jobs aggregate | **STALE** — §20.4 (**15,282**, 2026-11-23); not re-counted this pass | Prior read-only tinker |

### 22.3 Phases 2–3 — local staging and restore

| Item | Status |
|------|--------|
| Docker Desktop daemon | **BLOCKED** — engine not running on workstation |
| Isolated compose | **VERIFIED** in repo (`docker-compose.g0-local-restore.yml`, project `virello-g0-local-restore`) |
| Local `db-pre-deploy.sql.gz` | **BLOCKED** — not on disk (searched repo, Downloads, Desktop) |
| Restore executed | **PENDING** |

**Operator:** Start Docker Desktop; copy backup to `.local/g0-restore/shipstation-display-status-20261120/db-pre-deploy.sql.gz`; run `backend/scripts/g0-local-restore-verify-and-restore.ps1`.

### 22.4 Phases 4–6 — backup prep, rollback, failed jobs

| Item | Status |
|------|--------|
| Fresh production backup | **PREPARED NOT EXECUTED** — `scripts/production-backup.sh` (requires explicit approval) |
| Rollback images | **VERIFIED** (prior host evidence; unchanged) |
| Failed jobs risk | **VERIFIED (stale)** — PayPal/rebill debt; **0** `SyncShipStationTracking` failures |

### 22.5 Phases 7–8 — implementation and PayPal boundary

| Item | Status |
|------|--------|
| Repo `tracking_sync` defaults | **VERIFIED** — disabled/shadow-only defaults in `config/shipstation.php` |
| Local test re-run **2026-11-24** | **BLOCKED** — bootstrap fails on untracked Filament Meta resources (`AdminNavigationGroup::META_FACEBOOK`); does **not** invalidate 2026-11-23 Phase 1 suite evidence |
| PayPal | **UNTOUCHED** |

### 22.6 Phases 9–11 — G0 verdict and G1 package

| Deliverable | Status |
|-------------|--------|
| G0 sign-off items (owners, ticket, window) | **PENDING** |
| Local restore proof | **BLOCKED** |
| Fresh pre-G1 backup | **PENDING** |
| G1 deployment package | **PREPARED** — `shipstation-phase1-final-deployment-report.md` |

### 22.7 Mandatory matrix (this pass)

| Requirement | Status |
|-------------|--------|
| Fresh pre-G1 DB backup | **PENDING** |
| Backup checksum (existing 2026-11-20 file) | **VERIFIED** (re-checked on host 2026-11-24) |
| Isolated local restore proof | **BLOCKED** |
| Pilot / tracking unchanged on prod | **VERIFIED** |
| Failed jobs assessed | **VERIFIED (stale)** |
| Human approvals | **PENDING** |

### **G0 NOT READY** · **G1 NOT AUTHORIZED**

**Production actions this pass:** Read-only SSH inspection only. **No** backup creation, deploy, migrate, restart, queue ops, PayPal, or ShipStation writes.

---

## 23. G0 isolated local restore executed — 2026-11-24

**Recorded (UTC):** 2026-11-24T14:38:00Z  
**Production / SSH:** **NONE** (local Windows only)  
**Evidence:** `.local/g0-restore/restore-result.json`, `restore-run.log`

| Item | Result |
|------|--------|
| Local backup SHA-256 | **VERIFIED** — expected hash |
| gzip integrity | **VERIFIED** |
| Docker project | `virello-g0-local-restore` |
| Container | `virello_g0_restore_mysql` @ **127.0.0.1:33306** |
| Volume | `virello_g0_mysql_data` (new; **no** existing volumes deleted) |
| Restore DB | `checkout_platform` (114 tables) |
| Import duration | **~67 s** |
| Schema spot-check | **PASS** |
| `shipstation_webhook_receipts` | **Absent** (pre–Phase 1 snapshot — expected) |
| Migrations / queues / APIs | **Not run** |

**Script caveat:** `g0-local-restore-verify-and-restore.ps1` stopped on PowerShell + `docker compose` stderr; import completed with explicit `CREATE DATABASE checkout_platform` + `mysql checkout_platform` (dump has no `USE` clause).

**G0 technical restore requirement (isolated recoverability):** **SATISFIED** for this artifact.

**Overall G0:** **NOT READY** — fresh pre-G1 backup, owners, ticket, window, sign-off still **PENDING**.

---

## 24. G0 closure audit — 2026-11-24

**Auditor:** Cursor (local docs + evidence files only; **no production SSH** this pass)  
**Evidence reviewed:** `.local/g0-restore/restore-result.json`, `restore-run.log`

### 24.1 Restore evidence reconciliation

| Field | Log / JSON | Checklist §2 / §17 | Match |
|-------|------------|-------------------|-------|
| SHA-256 | `ee60da74…cff` | §2 | **Yes** |
| Restore UTC end | `2026-11-24T14:37:25Z` | §17 | **Yes** |
| Tables | 114 | Local restore report §6 | **Yes** |
| Isolation | `virello_g0_restore_mysql` @ 33306 | §15–§17 | **Yes** |
| Production touched | `false` | Policy | **Yes** |

### 24.2 Mandatory G0 matrix (updated)

| # | Requirement | Status | Notes |
|---|-------------|--------|-------|
| 1 | Fresh pre-G1 DB backup | **PENDING** | Newest artifact still **2026-11-20** |
| 2 | Recoverable backup (proven) | **VERIFIED (local)** | Isolated restore **SUCCESS**; VPS staging **BLOCKED** (§20.5) |
| 3 | Backup checksum (artifact used) | **VERIFIED** | Pre/post restore unchanged |
| 4 | Rollback images documented | **VERIFIED** | Prior host evidence |
| 5 | Config recovery procedure | **PARTIAL** | Runbook + backup scripts; operator review **PENDING** |
| 6 | Failed jobs assessed | **VERIFIED (stale)** | §20.4; no cleanup |
| 7 | Pilot unchanged on prod | **VERIFIED (stale)** | §20.3 — re-confirm at G1 window via read-only SSH |
| 8 | Phase 1 safe defaults in **repo** | **VERIFIED** | `tracking_sync.enabled=false`, shadow=true, fulfillment/recovery=false |
| 9 | Human owners / ticket / window | **PENDING** | `shipstation-phase1-g0-approval-checklist.md` |
| 10 | Operator / DBA sign-off | **PENDING** | Not fabricated |
| 11 | Approved **release commit** | **PENDING** | Phase 1 not on `origin/main` as single release |

### 24.3 Technical blockers (non-human)

| Blocker | Impact |
|---------|--------|
| **No approved Git release commit** on remote containing Phase 1 + migrations | G1 cannot target immutable artifact |
| Local working tree **dirty** / large untracked scope | Release packaging and CI green required before tag |
| `g0-local-restore-verify-and-restore.ps1` | PowerShell stderr + missing `checkout_platform` DB target (warnings in JSON) |
| Host **no CheckoutPlatform staging** | VPS restore proof remains **BLOCKED** (local restore accepted as alternative evidence) |

### 24.4 Pilot and tracking (documented — production re-confirm at G1)

| Setting | Repo default | Production (last read-only 2026-11-23) | G1 deploy target |
|---------|--------------|----------------------------------------|------------------|
| `tracking_sync` block | Present; **enabled=false** | **Absent** until code deploy | Deploy with flags **off** |
| `shadow_mode` | **true** | N/A until G1 | **true** |
| `fulfillment_updates` | **false** | N/A | **false** |
| `dispatch_recovery` | **false** | N/A | **false** |
| Pilot UUID | `6591d63b-f76a-4786-95ef-46fa27c62956` | **Only** this UUID | **Unchanged** |
| Pilot polling | default **false** | **false** | **Unchanged** |
| Pilot enabled | env-driven | **true** (documented) | **Unchanged** |

### 24.5 Local restore retention

**Policy:** Keep `virello_g0_restore_mysql` and volume **available** for read-only SQL verification on `127.0.0.1:33306`. **No** auto teardown.

### 24.6 SSH for future deploy

**Proposal only:** `shipstation-phase1-g0-ssh-deployment-access-plan.md` — least-privilege users, Docker allowlist, no unrelated stacks.

---

## 25. Authoritative G0 verdict (post–local restore)

| Gate | Verdict |
|------|---------|
| **Technical isolated restore** | **COMPLETE** |
| **Full G0 (all mandatory items)** | **NOT READY** |
| **G1** | **NOT AUTHORIZED** |

**Completed since §18:** Local restore proof closes the **recoverability** gap for the **2026-11-20** artifact. **Does not** replace item **1** (fresh pre-G1 backup) or human §9 fields.