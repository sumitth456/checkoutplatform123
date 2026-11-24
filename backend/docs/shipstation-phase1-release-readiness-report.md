# ShipStation Phase 1 — Release Readiness (Engineering Pass)

**Recorded (UTC):** 2026-11-24T15:10:00Z  
**Release candidate:** `eefcfd14d0398084caf73b24e9639e4d1271e874` on `feat/shipstation-phase1-tracking-sync`  
**PR:** https://github.com/sumitth456/checkoutplatform123/pull/2  

---

## CI (GitHub Actions)

| Workflow | Trigger | Required for merge | Notes |
|----------|---------|-------------------|--------|
| `deploy.yml` → job `test` | PR to `main` | **de facto** (sole workflow) | `composer install` → `key:generate` → `migrate --force` → `php artisan test` (full suite, MySQL 8.4 + Redis) |
| `deploy.yml` → job `deploy` | push `main` only | N/A for PR | VPS SSH deploy — **not** run on PR |

**CI runs (this branch):**

| Run | Commit | Result | URL |
|-----|--------|--------|-----|
| 45419341632 | `d124f7a` | failure | https://github.com/sumitth456/checkoutplatform123/actions/runs/45419341632 |
| 45420917448 | `fcb28b2` | failure | https://github.com/sumitth456/checkoutplatform123/actions/runs/45420917448 |
| 45422040680 | `e023718` | **failure** — 47 failed, 314 passed (71s) | https://github.com/sumitth456/checkoutplatform123/actions/runs/45422040680 |

**Pre-existing baseline:** `origin/main` CI has reported ~40 failing tests (same count as rebill PR investigation vs `84367d8`). Phase 1 PR must not **increase** failure count; isolate ShipStation failures via `php artisan test --filter=ShipStationPhase1` on a clean checkout.

---

## Local test evidence (release branch)

| Context | ShipStationPhase1 (48 tests) | Notes |
|---------|-------------------------------|--------|
| Dirty workspace + full untracked prod code (2026-11-23) | **48 passed** (documented baseline) | Not reproducible on `origin/main` parity alone |
| Clean worktree @ `e023718` (2026-11-24) | **32 passed**, 14 errors, 2 failures | Fixed PayPal resolver + missing models; residual webhook 500s under investigation |

**Not claimed:** full `php artisan test` green on PR until CI run completes.

---

## PayPal / fulfillment safety (staged diff audit)

- **No** staged changes under `backend/app/Modules/PayPal/` in Phase 1 commits.
- **No** changes to rebill commands, billing ledger, or payment webhooks in Phase 1 commits.
- `ShipStationOrderPaymentEnvironmentReader` delegates to `PayPalConfigResolver` only when that class exists on the host (production); no PayPal API calls added.
- Config defaults: `tracking_sync.enabled=false`, `shadow_mode=true`, `fulfillment_updates=false`, `dispatch_recovery=false`.

---

## G0 / G1 gates

| Gate | Status |
|------|--------|
| G0 READY | **NOT READY** |
| G1 authorized | **NOT AUTHORIZED** |
| G2 migrations | **NOT AUTHORIZED** (separate from G1 code deploy) |

---

## Engineering blockers remaining

1. Fresh **pre-G1** production backup + checksum + human owners/ticket/window.
2. CI confirmation on `e023718` (or successor commit).
3. Disk headroom on VPS (~189 GiB / 200 GiB documented) before full `production-backup.sh` DR package — prefer **DB-only gzip** to approved alternate destination if full stack backup does not fit.
4. SSH least-privilege users (`checkout-verify`, `checkout-deploy`, `checkout-dba`) — implementation package in SSH plan §10; **not applied** on host.