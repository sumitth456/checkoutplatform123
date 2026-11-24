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