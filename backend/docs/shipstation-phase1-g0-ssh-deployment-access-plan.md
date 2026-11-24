# ShipStation Phase 1 — Least-Privilege SSH Plan (Proposal Only)

**Recorded (UTC):** 2026-11-24T14:45:00Z  
**Status:** Planning — **no keys created**, **no production SSH performed**, **no `authorized_keys` changes**

---

## 1. Objectives

- Allow **targeted** CheckoutPlatform deploy/verify only.
- **Deny** by default access to `leadcrm_*`, `rot_*`, `mockup_*` paths and containers.
- **Deny** destructive DB operations without a second approval step.
- Support **read-only** G0 verification and **approved** G1/G2 execution separately.

---

## 2. Recommended identity model

| Role | SSH user (proposed) | Scope |
|------|---------------------|--------|
| Read-only verifier | `checkout-verify` | Read files, `docker ps`, `docker exec … read-only artisan`, no writes |
| Deploy operator | `checkout-deploy` | `/var/www/CheckoutPlatform` only; `docker compose` for `checkout_*` project |
| DBA | `checkout-dba` | `checkout_mysql` dump/restore only; no app deploy |
| Break-glass | `root` (existing) | Emergency only; **not** default for Phase 1 rollout |

**Do not** share root keys for routine G1. Use per-user keys stored in team vault.

---

## 3. Network and authentication

1. **Ed25519** deploy key per user; passphrase required; 90-day rotation policy.
2. Optional: restrict source IPs (office/VPN CIDR) in `sshd` or cloud firewall.
3. `AllowUsers checkout-verify checkout-deploy checkout-dba` for routine work; root login disabled for deploy users.
4. **ForceCommand** or `authorized_keys` command= prefix for `checkout-verify` limiting to a **read-only wrapper script** (future ops artifact — not installed in this pass).

---

## 4. Filesystem boundaries

| Path | verify | deploy | dba |
|------|--------|--------|-----|
| `/var/www/CheckoutPlatform` | read | read/write | read (env read via compose only) |
| `/var/www/CheckoutPlatform/.backups` | read | write backups | write/read dumps |
| `/var/backups/CheckoutPlatform` | read | write (approved backup) | write |
| `/var/www/leadcrm*`, `rot`, `mockup` | **deny** | **deny** | **deny** |
| `~/.ssh/authorized_keys` | **deny** | **deny** | **deny** |

Use **ACLs** or separate UNIX groups: `checkout-deploy` group owns `CheckoutPlatform` tree.

---

## 5. Docker command allowlist (deploy user)

**Permitted** (after G1 approval only):

- `docker compose -f …/docker-compose.yml -f …/docker-compose.prod.yml ps|logs`
- `docker compose … build app worker`
- `docker compose … up -d app worker`
- `docker exec checkout_app php artisan migrate:status`
- `docker exec checkout_app php artisan migrate --force` (G2 gate only)
- `docker exec checkout_app php artisan config:cache`
- `docker exec checkout_worker supervisorctl status`

**Denied:**

- `docker compose down` on full stack without ticket
- `docker volume rm`, `docker system prune`
- `docker` against non-`checkout_*` container names
- Queue retry/purge, `failed_jobs` delete, PayPal/ShipStation write scripts

Implement via **sudoers** `Cmnd_Alias` listing exact command strings (no shell wildcards to `docker`).

---

## 6. G0 read-only command set (verify user)

Align with preflight **§19.3** (one command at a time, no bundled automation upload):

- `sha256sum` / `gzip -t` on known backup paths
- `docker ps`, `docker inspect checkout_*`
- `git -C /var/www/CheckoutPlatform rev-parse HEAD`
- `docker exec checkout_app php artisan about`
- `docker exec checkout_app php artisan tinker` → **read-only** `config()` JSON for shipstation keys only (no `DB::` writes)

**No** `mysqldump`, `git pull`, or `composer install` for verify user.

---

## 7. Audit and logging

- Enable `sshd` `LogLevel VERBOSE` or centralized auth logs for deploy windows.
- Require deploy operator to paste **command log + digests** into change ticket post-G1.
- Store image IDs and commit SHA in `manifest/git-state.txt` from `scripts/production-backup.sh` before each G1.

---

## 8. Rollout sequence (when authorized)

1. Create users/groups/sudoers on host (**change ticket**).
2. Issue keys to named owners only.
3. Run **verify** profile for G0 refresh.
4. Run **deploy** profile only during approved UTC window.
5. Revoke or rotate keys after window if not ongoing ops role.

---

## 9. Explicit non-actions (this document pass)

- No SSH connections executed
- No key generation
- No `sshd_config` or `authorized_keys` edits
- No production backup or deploy

---

## 10. Implementation package (ops — run only with change ticket)

**Prerequisites:** root break-glass session; no overlap with `leadcrm_*` / `rot_*` / `mockup_*` paths.

### 10.1 UNIX groups and users (on VPS)

```bash
# As root — adjust UIDs if policy requires
groupadd -f checkout-deploy
groupadd -f checkout-verify
groupadd -f checkout-dba

useradd -m -s /bin/bash -g checkout-deploy -G docker checkout-deploy
useradd -m -s /bin/bash -g checkout-verify -G docker checkout-verify
useradd -m -s /bin/bash -g checkout-dba checkout-dba

chown -R root:checkout-deploy /var/www/CheckoutPlatform
chmod -R g+rX /var/www/CheckoutPlatform
chmod -R g+w /var/www/CheckoutPlatform/backend/storage /var/www/CheckoutPlatform/backend/bootstrap/cache
```

Protect secrets:

```bash
chmod 640 /var/www/CheckoutPlatform/.env.docker
chown root:checkout-deploy /var/www/CheckoutPlatform/.env.docker
```

### 10.2 Ed25519 keys (generate on **operator workstation**, not on VPS)

```bash
ssh-keygen -t ed25519 -a 64 -f ~/.ssh/checkoutplatform_deploy_ed25519 -C "checkout-deploy@$(hostname)"
ssh-keygen -t ed25519 -a 64 -f ~/.ssh/checkoutplatform_verify_ed25519 -C "checkout-verify@$(hostname)"
ssh-keygen -t ed25519 -a 64 -f ~/.ssh/checkoutplatform_dba_ed25519 -C "checkout-dba@$(hostname)"
```

Install public keys (root on VPS):

```bash
install -d -m 700 /home/checkout-deploy/.ssh /home/checkout-verify/.ssh /home/checkout-dba/.ssh
# Paste one pubkey per line:
nano /home/checkout-deploy/.ssh/authorized_keys
nano /home/checkout-verify/.ssh/authorized_keys
nano /home/checkout-dba/.ssh/authorized_keys
chmod 600 /home/checkout-*/.ssh/authorized_keys
chown -R checkout-deploy:checkout-deploy /home/checkout-deploy/.ssh
chown -R checkout-verify:checkout-verify /home/checkout-verify/.ssh
chown -R checkout-dba:checkout-dba /home/checkout-dba/.ssh
```

### 10.3 Sudoers fragments (`/etc/sudoers.d/checkoutplatform-*`)

Use **exact** command paths (update if compose binary path differs):

```sudoers
# checkout-verify — read-only
Cmnd_Alias CHECKOUT_VERIFY = \
  /usr/bin/docker ps, \
  /usr/bin/docker inspect checkout_*, \
  /usr/bin/git -C /var/www/CheckoutPlatform rev-parse HEAD, \
  /usr/bin/git -C /var/www/CheckoutPlatform status, \
  /usr/bin/docker exec checkout_app php artisan about, \
  /usr/bin/docker exec checkout_app php artisan migrate:status, \
  /usr/local/bin/checkout-verify-readonly.sh *

checkout-verify ALL=(root) NOPASSWD: CHECKOUT_VERIFY
```

```sudoers
# checkout-deploy — app/worker only (no down -v, no prune)
Cmnd_Alias CHECKOUT_DEPLOY = \
  /usr/bin/docker compose --env-file /var/www/CheckoutPlatform/.env.docker -f /var/www/CheckoutPlatform/docker-compose.yml -f /var/www/CheckoutPlatform/docker-compose.prod.yml ps, \
  /usr/bin/docker compose --env-file /var/www/CheckoutPlatform/.env.docker -f /var/www/CheckoutPlatform/docker-compose.yml -f /var/www/CheckoutPlatform/docker-compose.prod.yml logs *, \
  /usr/bin/docker compose --env-file /var/www/CheckoutPlatform/.env.docker -f /var/www/CheckoutPlatform/docker-compose.yml -f /var/www/CheckoutPlatform/docker-compose.prod.yml build app worker, \
  /usr/bin/docker compose --env-file /var/www/CheckoutPlatform/.env.docker -f /var/www/CheckoutPlatform/docker-compose.yml -f /var/www/CheckoutPlatform/docker-compose.prod.yml up -d app worker, \
  /usr/bin/docker exec checkout_app php artisan config:cache, \
  /usr/bin/docker exec checkout_worker supervisorctl status

checkout-deploy ALL=(root) NOPASSWD: CHECKOUT_DEPLOY
```

```sudoers
# checkout-dba — dump/restore checkout_mysql only (G0/G2 approved windows)
Cmnd_Alias CHECKOUT_DBA = \
  /usr/local/bin/checkout-dba-backup.sh, \
  /usr/local/bin/checkout-dba-verify-dump.sh

checkout-dba ALL=(root) NOPASSWD: CHECKOUT_DBA
```

### 10.4 Wrapper scripts (install on VPS)

`/usr/local/bin/checkout-verify-readonly.sh` — allowlisted read-only checks only (no `git pull`, no migrations).

`/usr/local/bin/checkout-dba-backup.sh` — wrap:

```bash
#!/usr/bin/env bash
set -euo pipefail
PROJECT_ROOT="/var/www/CheckoutPlatform"
# shellcheck source=/dev/null
source "${PROJECT_ROOT}/.env.docker"
DEST="/var/backups/CheckoutPlatform/$(date +%Y-%m-%d_%H-%M-%S)"
mkdir -p "${DEST}"
docker compose --env-file "${PROJECT_ROOT}/.env.docker" -f "${PROJECT_ROOT}/docker-compose.yml" \
  -f "${PROJECT_ROOT}/docker-compose.prod.yml" exec -T mysql mysqldump \
  --single-transaction --quick "${MYSQL_DATABASE}" | gzip -9 > "${DEST}/db-pre-g1.sql.gz"
sha256sum "${DEST}/db-pre-g1.sql.gz" | tee "${DEST}/checksums.sha256"
gzip -t "${DEST}/db-pre-g1.sql.gz"
```

### 10.5 Connection verification (from operator laptop)

```bash
ssh -i ~/.ssh/checkoutplatform_verify_ed25519 checkout-verify@2.25.90.141 \
  'sudo /usr/bin/docker ps --filter name=checkout_'

ssh -i ~/.ssh/checkoutplatform_deploy_ed25519 checkout-deploy@2.25.90.141 \
  'sudo /usr/bin/git -C /var/www/CheckoutPlatform rev-parse HEAD'

ssh -i ~/.ssh/checkoutplatform_dba_ed25519 checkout-dba@2.25.90.141 \
  'sudo /usr/local/bin/checkout-dba-verify-dump.sh /var/backups/CheckoutPlatform/latest/db-pre-g1.sql.gz'
```

### 10.6 Key rotation and revocation

1. Issue new Ed25519 key; append to `authorized_keys`; validate login; remove old key line.
2. On compromise: remove key line immediately; rotate deploy window; audit `/var/log/auth.log`.
3. `checkout-deploy` keys: 90-day rotation recommended; store passphrases in team vault only.

### 10.7 Denied patterns (enforce in sudoers — no wildcards)

- `docker compose down`, `docker compose down -v`
- `docker system prune`, `docker volume rm`
- Any `docker` command against `leadcrm_*`, `rot_*`, `mockup_*`
- `php artisan migrate`, `queue:flush`, PayPal/ShipStation mutation scripts without G2 ticket