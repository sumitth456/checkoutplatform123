# ShipStation Phase 1 — G0 Human Approval Checklist (Do Not Fabricate)

**Recorded (UTC):** 2026-11-24T14:45:00Z  
**Purpose:** Capture **mandatory human** G0 inputs before G1 authorization.  
**Automation may not fill names, tickets, or signatures.**

---

## Instructions

Complete each row with **verifiable evidence** (ticket link, timestamp, artifact path). Leave blank until real.

| # | Requirement | Status | Record here | Evidence artifact |
|---|-------------|--------|-------------|-------------------|
| 1 | **Fresh pre-G1 production DB backup** | ☐ PENDING | Path: _______ SHA-256: _______ UTC: _______ | e.g. `/var/backups/CheckoutPlatform/...` or `.backups/...` |
| 2 | Fresh backup gzip integrity | ☐ PENDING | `gzip -t` OK at UTC: _______ | Log snippet |
| 3 | **Deployment owner** (name + role) | ☐ PENDING | Name: _______ Role: _______ | Ticket or RACI |
| 4 | **Rollback owner** (name + role) | ☐ PENDING | Name: _______ Role: _______ | Ticket or RACI |
| 5 | **DBA / restore owner** (name + role) | ☐ PENDING | Name: _______ Role: _______ | Ticket or RACI |
| 6 | **Change ticket / reference** | ☐ PENDING | ID: _______ URL: _______ | ITSM record |
| 7 | **Approved UTC deployment window** | ☐ PENDING | Start: _______ End: _______ | Change calendar |
| 8 | **Escalation contact** | ☐ PENDING | Name: _______ Channel: _______ | |
| 9 | Config/app recovery procedure **reviewed** | ☐ PENDING | Reviewer: _______ Date: _______ | Sign-off note |
| 10 | DBA sign-off on restore test | ☐ PENDING | Name: _______ Date: _______ | Local: `restore-result.json` |
| 11 | **G0 operator sign-off** | ☐ PENDING | Name: _______ Date: _______ | This checklist + preflight §25 |

---

## Already verified (technical — no signature required)

| Item | Evidence |
|------|----------|
| Historical backup checksum (2026-11-20 artifact) | SHA-256 `ee60da74242400e2cc4b530d355cdee922bb58f614beeba6e2a6811c2cb55cff` |
| **Isolated local restore** | `restore-result.json` — SUCCESS **2026-11-24T14:37:25Z**, 114 tables |
| Rollback image tags (host) | `checkoutplatform-app:backup-stage1-pre-8427-20261022-182336` (+ worker) |
| Production baseline | `53abb6c`; Phase 1 **not** deployed |
| Pilot UUID (documented prod read-only 2026-11-23) | `6591d63b-f76a-4786-95ef-46fa27c62956` only |
| Pilot polling | **false** (prod); must remain false at G1 |

---

## G0 gate rule

**G0 READY** only when **every row in the mandatory table (1–11)** is complete **and** technical blockers in `shipstation-phase1-final-deployment-report.md` are resolved (especially **approved release commit**).

**G1 NOT AUTHORIZED** until a **separate** written G1 authorization after G0 READY.