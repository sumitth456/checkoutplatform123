# ShipStation Phase 1 — G0 Local Windows Restore Test Report

**Recorded (UTC):** 2026-11-23 (updated **2026-11-24T14:15:00Z**)  
**Scope:** **LOCAL Windows workstation only** — **no** production SSH/SCP/deploy for restore  
**Production touched:** **NO** (read-only SSH used only to refresh on-host backup metadata)

---

## 1. Executive summary

| Item | Status |
|------|--------|
| Local environment inspected | **VERIFIED** |
| Isolated Docker Compose project prepared | **VERIFIED** |
| Backup file present locally | **VERIFIED** (2026-11-24) |
| SHA-256 / gzip pre-restore | **VERIFIED** |
| MySQL restore executed | **VERIFIED** (2026-11-24T14:37:25Z) |
| Schema verification post-restore | **VERIFIED** |
| G0 overall (full gate) | **NOT READY** — human items remain (§8) |

**Operator action:** Copy backup to `.local/g0-restore/shipstation-display-status-20261120/db-pre-deploy.sql.gz`, then run `backend/scripts/g0-local-restore-verify-and-restore.ps1`.

---

## 2. Local machine environment (verified)

| Component | Result |
|-----------|--------|
| Repo path | `c:\CheckoutPlatform` |
| Git HEAD | `84367d8a406b63db69e677422cce3cf691b77b12` (dirty working tree) |
| Docker Desktop | **Client 29.6.2** — daemon **STOPPED** on **2026-11-24** (`dockerDesktopLinuxEngine` unreachable) |
| Docker Compose | **v5.3.1** (when daemon running) |
| PHP (host CLI) | **8.4.23** |
| Composer | **2.10.2** |
| Free disk (C:) | **~13.8 GiB** (2026-11-24) |
| MySQL image (main + G0) | **mysql:8.4** (matches production major) |
| Main dev stack | Not inspected while daemon stopped; G0 compose does **not** reference `checkout_*` volumes |

---

## 3. Backup file

| Field | Expected | Local actual |
|-------|----------|--------------|
| Path | `.local/g0-restore/shipstation-display-status-20261120/db-pre-deploy.sql.gz` | **Present** |
| Size | 40,185,284 bytes | **VERIFIED** |
| SHA-256 | `ee60da74242400e2cc4b530d355cdee922bb58f614beeba6e2a6811c2cb55cff` | **VERIFIED** (pre + post restore) |
| gzip integrity | `gzip -t` via alpine container | **VERIFIED** |

### Secure transfer (operator — manual)

1. On an authorized admin host with existing production artifact:  
   `/var/www/CheckoutPlatform/.backups/shipstation-display-status-20261120/db-pre-deploy.sql.gz`
2. Transfer via **encrypted channel** (SFTP/SCP to workstation, or encrypted removable media).
3. Place at:  
   `c:\CheckoutPlatform\.local\g0-restore\shipstation-display-status-20261120\db-pre-deploy.sql.gz`
4. Verify locally:  
   `Get-FileHash -Algorithm SHA256 <path>`  
   must equal expected hash above.
5. Run restore script (§6).

**Do not** commit the `.sql.gz` to git (path is gitignored).

---

## 4. Isolation design (verified in repo)

| Control | Implementation |
|---------|----------------|
| Compose project name | `virello-g0-local-restore` (`-p` flag) |
| Compose file | `docker-compose.g0-local-restore.yml` (separate from main stack) |
| Container name | `virello_g0_restore_mysql` (not `checkout_mysql`) |
| Network | `virello_g0_restore_net` |
| Volume | `virello_g0_mysql_data` |
| Published port | **127.0.0.1:33306** → 3306 (avoids default `checkout_mysql` on 3306) |
| Services started | **MySQL only** — no app, worker, redis, nginx |
| Credentials | `.env.g0-local-restore` (gitignored); example in `.env.g0-local-restore.example` |
| Production connections | **None** by design |
| Outbound integrations | **No application container** in this compose file |

Main `checkout_mysql` / `checkout_app` volumes and networks are **not** referenced.

---

## 5. Restore procedure (prepared, not executed)

**Script:** `backend/scripts/g0-local-restore-verify-and-restore.ps1`

1. Verify Docker available  
2. Require backup path; **SHA-256** and **gzip -t** (via ephemeral `alpine` container, read-only mount)  
3. `docker compose -p virello-g0-local-restore … up -d g0_mysql`  
4. Wait healthcheck  
5. `gunzip -c /backup/db-pre-deploy.sql.gz | mysql …` **inside** `virello_g0_restore_mysql`  
6. Aggregates only: table counts, presence of `orders`, `subscriptions`, `shipstation_integrations`, `failed_jobs`, `migrations`  
7. Write `.local/g0-restore/restore-run.log` and `restore-result.json`  

**No** Laravel migrations, queues, PayPal, or ShipStation API calls.

---

## 6. Restore execution

| Field | Value |
|-------|-------|
| Restore start (UTC) | **2026-11-24T14:35:48Z** (import; script started 14:35:05Z) |
| Restore end (UTC) | **2026-11-24T14:37:25Z** |
| Exit status | **0** (import) |
| Duration (import) | **~67 seconds** |
| Database name | **`checkout_platform`** (matches dump header; created in isolated container) |
| Tables in `checkout_platform` | **114** |
| Sample row counts | migrations **68**; orders **962**; failed_jobs **14,499**; shipstation_integrations **354**; subscriptions **350** |
| Errors | Script exited early on `docker compose` stderr; import completed manually per script intent |

---

## 7. Schema verification

| Check | Result |
|-------|--------|
| Key tables | `orders`, `subscriptions`, `shipstation_integrations`, `failed_jobs`, `migrations`, `customers`, `billing_ledger_entries` — **present** |
| Phase 1 table `shipstation_webhook_receipts` | **Absent** (expected — backup predates Phase 1 migrations) |
| Laravel migrations executed | **No** |
| App / queue / scheduler | **Not started** (compose is MySQL-only) |

---

## 8. G0 implication

A **successful local restore** (when run) supports **backup recoverability in isolation** only.

It does **not** close:

- Fresh pre-G1 production backup  
- Management owners / change window / ticket  
- Operator sign-off  

**Do not mark G0 READY** from local restore alone.

**Updated requirement mapping:**

| G0 item | After this report |
|---------|-------------------|
| Staging restore (host VPS) | **BLOCKED** (§20.5) |
| Isolated restore proof | **VERIFIED** — see `restore-result.json` |
| Fresh pre-G1 backup | **PENDING** |
| Human approvals | **PENDING** |

**G0 NOT READY** · **G1 NOT AUTHORIZED**

---

## 9. Cleanup

Isolated volume `virello_g0_mysql_data` and container are **not** removed automatically.

To remove later (operator approval):

```powershell
docker compose -p virello-g0-local-restore -f docker-compose.g0-local-restore.yml down
docker volume rm virello_g0_mysql_data
```

---

## 10. Related files

| File | Purpose |
|------|---------|
| `docker-compose.g0-local-restore.yml` | Isolated MySQL only |
| `.env.g0-local-restore.example` | Non-production credentials template |
| `.local/g0-restore/README.md` | Backup drop zone instructions |
| `backend/scripts/g0-local-restore-verify-and-restore.ps1` | Verify + restore automation |

---

## 11. Local restore execution — 2026-11-24

| Step | Result |
|------|--------|
| Local backup + SHA-256 + gzip | **VERIFIED** |
| Docker Desktop | **VERIFIED** |
| `virello_g0_restore_mysql` started | **VERIFIED** (new volume; no volume deletes) |
| Import into `checkout_platform` | **SUCCESS** (~67 s) |
| Schema verification | **PASS** (114 tables; key app tables present) |
| Evidence | `restore-result.json`, `restore-run.log` |

**Warnings:** Restore script needs operator fix for `docker compose` stderr + explicit DB name (documented in `restore-result.json`).

**Technical G0 restore:** **SATISFIED** · **Full G0 gate:** **NOT READY** (§8)