# ShipStation Phase 1 — G0 Operator Evidence Checklist

**Purpose:** Collect evidence required for **G0 sign-off**. **Not G1 authorization.**

**Last evidence pass:** 2026-11-23T18:15:00Z (read-only production inspection)  
**Final verification pass:** 2026-11-23T17:30:00Z — §12 (evidence audit; no production mutations)

---

## 1. Inspection metadata

| Field | Value |
|-------|-------|
| Operator name | **PENDING** |
| Inspection date (UTC) | 2026-11-23 (automated read-only pass) |
| Host | production — virello.cloud |
| Read-only inspection performed? | **Yes** |

---

## 2. Database backup evidence

| Field | Status | Recorded value |
|-------|--------|----------------|
| Backup exists | **PARTIAL** — file located, restore unproven | |
| Latest located path | **Verified (read-only)** | `/var/www/CheckoutPlatform/.backups/shipstation-display-status-20261120/db-pre-deploy.sql.gz` |
| Backup timestamp (UTC) | **Verified** | **2026-11-20 15:51** (host local listing) |
| Size (bytes) | **Verified** | **40,185,284** |
| SHA-256 | **Verified** | `ee60da74242400e2cc4b530d355cdee922bb58f614beeba6e2a6811c2cb55cff` |
| SHA-256 re-check | **Verified** | **2026-11-23T18:11:48Z** — matches prior (§20.1) |
| Gzip integrity | **Verified** | **2026-11-23T18:11:56Z** — OK (§20.2) |
| Pre-deploy commit in bundle | **Verified** | `53abb6c17962703433fce760d04d9d48bb437820` |
| **Fresh backup for G1 window** | **PENDING** | Operator creates before G1 |
| Restore test performed | **VERIFIED** | Local isolated restore **2026-11-24T14:37:25Z** — `restore-result.json` |
| Restore target environment | **VERIFIED (local)** | `virello-g0-local-restore` / `checkout_platform` @ **33306**; VPS staging **BLOCKED** |
| Restore test timestamp (UTC) | **VERIFIED** | **2026-11-24T14:37:25Z** |
| DBA sign-off | **PENDING** | Name: ______ Date: ______ |

---

## 3. Application and configuration backup

| Field | Status | Recorded value |
|-------|--------|----------------|
| Current app image | **Verified** | `checkoutplatform-app:latest` → `ce344fc6453b` |
| Rollback image candidate | **Verified** | `checkoutplatform-app:backup-stage1-pre-8427-20261022-182336` → `db571708dbdb` |
| Worker rollback image | **Verified** | `checkoutplatform-worker:backup-stage1-pre-8427-20261022-182336` |
| Deploy file bundle | **PARTIAL** | `.backups/shipstation-display-status-20261120/files/` (exists) |
| Env snapshot path | **PARTIAL** | e.g. `.backups/*/backend.env.snapshot` (exists; **not opened**) |
| `shipstation.php` snapshot | **PARTIAL** | `.backups/shipstation-display-status-20261120/shipstation.php.snapshot` |
| Config recovery procedure reviewed | **PENDING** | |

**Do not paste secrets into this document.**

---

## 4. Release identifiers

| Field | Value |
|-------|-------|
| Host deploy path | `/var/www/CheckoutPlatform` |
| Current Git HEAD | `53abb6c` (2026-07-28T19:54:42Z per prior probe) |
| App image digest (running) | `sha256:ce344fc6453b30ef9576958753a5b11ba949172d926f85a565d09df4f0097bb7` |
| Worker image digest (running) | `sha256:c71e3af0a82a2d9efd6f02e9fc518945e5555d32f9362c0c625c8dd31144c6e5` |
| Previous KGI image tag | `backup-stage1-pre-8427-20261022-182336` (Oct 2026) |
| Phase 1 code on prod | **No** (`tracking_sync` config absent) |

---

## 5. Feature flags (resolved `config()`)

| Setting | Expected at G1 | Prod (refreshed 2026-11-23T18:18:58Z) |
|---------|----------------|--------------------------------------|
| Global tracking sync | `false` | **N/A** — `tracking_sync` not in deployed config |
| Shadow | `true` | **N/A** |
| Fulfillment updates | `false` | **N/A** |
| Dispatch recovery | `false` | **N/A** |
| Pilot polling | `false` | **false** ✓ |
| Pilot enabled | document only | **true** (unchanged) ✓ |
| Pilot UUIDs | single approved | **`6591d63b-f76a-4786-95ef-46fa27c62956` only** ✓ |
| `shipstation.mode` | — | **real** |

**Source:** §19.3.5 / preflight §20.3 (read-only `config()`).

**Re-capture required after G1 deploy.**

---

## 6. Queue / failed jobs

| Field | Value (refreshed 2026-11-23T18:20:22Z — §19.3.6) |
|-------|--------------------------------------------------|
| `queue.default` | **redis** (prior §16; not re-probed) |
| `cache.default` | **redis** (prior §16) |
| Worker command | `queue:work redis --tries=3` (prior §16) |
| `failed_jobs` total | **15,282** |
| Oldest failure | **2026-08-05 18:00:06** |
| Newest failure | **2026-11-23 18:00:07** |
| PayPal mentions in exceptions | **10,388** (keyword count) |
| ShipStation mentions | **2,230** |
| Fulfillment mentions | **2,857** |
| RebillService mentions | **10,388** |
| SyncShipStationTracking | **0** |

**Recent pattern:** `RebillService` ineligible rebill (`InvalidArgumentException`) — **not** Phase 1 tracking.

**Cleanup / retry:** **NOT authorized** in G0.

---

## 7. Redis / locks

| Field | Status |
|-------|--------|
| Redis healthy | **Verified** |
| Cache store = redis | **Verified** |
| Suitable for `Cache::lock` | **Verified** (single compose cluster) |

---

## 8. Migrations

| Field | Status |
|-------|--------|
| `2026_11_19_*` | **Pending** on production |
| Receipt table | **Absent** |

---

## 9. Human approvals

| Field | Status |
|-------|--------|
| Deployment owner | **PENDING** |
| Rollback owner | **PENDING** |
| DBA / restore owner | **PENDING** |
| Change window (UTC) | **PENDING** |
| Escalation contact | **PENDING** |
| Approval ticket ID | **PENDING** |
| G0 operator sign-off | **PENDING** |

---

## 10. Safety attestations

| Check | ☐ |
|-------|---|
| No G1 deploy | |
| No migrations in G0 | |
| No flag changes | |
| No container restarts | |
| No PayPal changes | |
| Pilot config unchanged | |

---

## 11. G0 verdict line

**Current:** **G0 NOT READY** (awaiting restore test, fresh backup, human sign-off)

**G1:** **NOT AUTHORIZED**

---

## 12. Final evidence verification — 2026-11-23T17:30:00Z

**Verifier action:** Cross-checked this checklist and `shipstation-phase1-g0-production-preflight-report.md` §18; read-only production host check for backups newer than **2026-11-20** and restore proof files. **No** production mutations.

### 12.1 Mandatory items (supplied evidence audit)

| # | Item | Status | Notes |
|---|------|--------|-------|
| 1 | Fresh pre-G1 DB backup | **PENDING** | No new path/checksum recorded in §2; host newest unchanged (**2026-11-20**) |
| 2 | Staging restore + verification | **VERIFIED (local)** / **BLOCKED (VPS)** | Local restore §17; host staging none (§19.3.7) |
| 3 | Config/app recovery procedure | **PENDING** | §3 “procedure reviewed” **PENDING** |
| 4 | Named owners | **PENDING** | §9 |
| 5 | Change window + ticket | **PENDING** | §9 |
| 6 | Operator sign-off | **PENDING** | §9, §10 |

**Supplied evidence:** **Not found** in repository or checklist fields. Narrative-only claims **not accepted**.

### 12.2 Consistency with preflight report

| Checklist | Preflight | Match? |
|-----------|-----------|--------|
| §2 backup SHA/size | §17.2 | **Yes** |
| §9 all PENDING | §17.6, §18.2 | **Yes** |
| §11 NOT READY | §18.5 NOT READY | **Yes** |
| §3 KGI image verified | §17.3, §18.2 supporting | **Yes** |

### 12.3 Secrets

No `.env` contents or API secrets were added to this checklist during the final pass.

### 12.4 Final verdict line

**G0 NOT READY** — provide §2–3 and §9 evidence, then re-run verification.

**G1 NOT AUTHORIZED**

---

## 13. Cursor-managed automation pass — 2026-11-23T17:15:00Z

**Production:** No SSH, no script upload, no container/DB/queue/PayPal/pilot changes.

### 13.1 Local checks completed (VERIFIED)

| Item | Result |
|------|--------|
| `scripts/production-backup.sh` exists | Yes — includes DR restore doc template |
| `backend/scripts/backup-production-db.sh` exists | Yes |
| `backend/scripts/deploy-shipstation-display-status-backup-prod.sh` exists | Yes — defines `.backups/*/db-pre-deploy.sql.gz` pattern |
| Repo staging environment | **BLOCKED** — no staging compose in repository |
| `tracking_sync` defaults in repo config | enabled=false, shadow=true, fulfillment/recovery=false |
| `g0-readonly-automation.sh` | **Reference only** — not for production bundle execution |

### 13.2 Production commands (PENDING — run individually)

See preflight **§19.3** (checksum, gzip -t, config tinker, failed_jobs, staging discovery).

### 13.3 Approval-required (not executed)

| Operation | Status |
|-----------|--------|
| Fresh production mysqldump | **PENDING approval** |
| Staging restore test | **BLOCKED** (isolation not proven) + **PENDING approval** |
| Container rollback test | **PENDING approval** |

### 13.4 Mandatory items unchanged

| # | Item | Status |
|---|------|--------|
| 1 | Fresh pre-G1 backup | **PENDING** |
| 2 | Staging restore proof | **VERIFIED (local)** — §17; **BLOCKED (VPS staging)** |
| 3 | Config recovery sign-off | **PARTIAL** (repo docs only) |
| 4–6 | Owners / window / sign-off | **PENDING** |

### 13.5 Verdict

**G0 NOT READY** · **G1 NOT AUTHORIZED**

---

## 14. §19.3 read-only execution — 2026-11-23

| ID | UTC | Status | Summary |
|----|-----|--------|---------|
| 19.3.1 | 18:11:48Z | **VERIFIED** | SHA-256 matches checklist |
| 19.3.2 | 18:11:56Z | **VERIFIED** | `gzip -t` OK |
| 19.3.5 | ~18:18:58Z | **VERIFIED** | Pilot on, poll off, single UUID; no `tracking_sync` on prod |
| 19.3.6 | 18:20:22Z | **VERIFIED** | 15,282 failed jobs; 0 Phase 1 tracking failures |
| 19.3.7 | 18:20:54Z | **BLOCKED** | No isolated checkout staging on host |

**Production mutations:** **None**

---

## 15. Local Windows restore (G0) — 2026-11-23

| Item | Status |
|------|--------|
| Isolated Docker project prepared | **VERIFIED** |
| Backup copied locally | **VERIFIED** — §17 |
| Restore script | `backend/scripts/g0-local-restore-verify-and-restore.ps1` |
| Report | `shipstation-phase1-g0-local-restore-report.md` |
| Production | **Untouched** |

**G0 NOT READY** until restore run succeeds **and** other mandatory items remain closed.

---

## 16. Autonomous preparation pass — 2026-11-24

**Evidence:** `.local/g0-restore/evidence/autonomous-pass-20261124.json` · Preflight §22

| Item | Status (2026-11-24) |
|------|---------------------|
| Production commit / images refreshed | **VERIFIED** — `53abb6c`; digests unchanged from §4 |
| On-host backup SHA-256 | **VERIFIED** (re-read on host) |
| Local backup + restore | **VERIFIED** — restore **2026-11-24** (see §17) |
| Fresh pre-G1 backup | **PENDING** — not executed (needs approval) |
| G1 package document | **PREPARED** — `shipstation-phase1-final-deployment-report.md` |
| PayPal / pilot / unrelated apps | **UNTOUCHED** |
| Operator name / ticket / window / sign-off | **PENDING** |

**G0 NOT READY** · **G1 NOT AUTHORIZED**

---

## 17. Local restore execution — 2026-11-24

| Item | Value |
|------|-------|
| Backup SHA-256 | `ee60da74242400e2cc4b530d355cdee922bb58f614beeba6e2a6811c2cb55cff` |
| Tables (`checkout_platform`) | **114** |
| Restore duration | **~67 s** |
| Isolation | `virello_g0_restore_mysql` / `virello_g0_mysql_data` / port **33306** |
| Volume deletes | **None** |
| Production | **Untouched** |

**Technical G0 restore evidence:** **COMPLETE** · **Overall G0 sign-off:** **PENDING** (fresh backup, humans).

---

## 18. G0 closure audit — 2026-11-24

**Cross-reference:** Preflight §24–§25 · Approval form: `shipstation-phase1-g0-approval-checklist.md` · SSH plan: `shipstation-phase1-g0-ssh-deployment-access-plan.md`

| Completed (technical) | Outstanding |
|----------------------|-------------|
| Backup checksum + gzip (2026-11-20 artifact) | Fresh pre-G1 backup (#1) |
| Isolated local restore (#2 local) | VPS staging (blocked) |
| Rollback image IDs | Named owners, ticket, window, sign-offs |
| Failed jobs assessment (stale) | Approved **release commit** |
| Repo `tracking_sync` safe defaults | G1 authorization |

**G0 NOT READY** · **G1 NOT AUTHORIZED**