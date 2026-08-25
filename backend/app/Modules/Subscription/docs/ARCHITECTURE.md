# CheckoutChamp-Style Subscription Engine

## Overview

The subscription engine manages recurring revenue lifecycles: plan definition, enrollment, scheduled rebills, retries, dunning, lifecycle controls (pause / resume / cancel), and payment-provider webhooks. It follows a **service + action + repository** pattern inside `app/Modules/Subscription/`.

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         Admin (Filament)                                 │
│  Plans · Steps · Retry Policies · Dunning Policies · Subscriptions      │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────────┐
│                    SubscriptionEngineService                             │
│  enroll · pause · resume · cancel · retryPayment · processDue            │
└───┬─────────────┬──────────────┬──────────────┬─────────────────────────┘
    │             │              │              │
    ▼             ▼              ▼              ▼
Lifecycle    RebillService   DunningService   FailedPaymentRetryService
Service          │              │              │
    │             ▼              ▼              │
    │      PaymentOrchestrator  Events/Jobs    │
    │             │              │              │
    └─────────────┴──────────────┴──────────────┴──► BillingLedger
```

## Billing cycle types

| Type | Meaning | Schedule source |
|------|---------|-----------------|
| `trial` | Free or paid trial window | `days_from_start` from subscription start |
| `day_7_rebill` | Rebill 7 days after previous success | +7 days |
| `day_30_rebill` | Rebill 30 days after previous success | +30 days |
| `weekly` | Weekly recurring | +1 week |
| `monthly` | Monthly recurring | +1 calendar month |
| `custom` | Arbitrary interval | `interval_days` or `days_from_start` |

Plans are **multi-step** (`subscription_plan_steps`): e.g. trial (step 1) → day-7 one-time (step 2) → monthly recurring (step 3).

### Rebill gateway load balancing

Rebill charges (`RebillService`) resolve gateways through `RebillGatewayLoadBalancer`:

- Active gateways ordered by **priority** (`sort_order`) then **weight** (load balancing within the same priority tier).
- Gateways over daily/monthly volume caps are skipped (`PaymentGatewayLoadService`).
- Each charge attempt walks the ordered list until authorize/capture succeeds (failover).
- After a failed rebill cycle, `RetryPolicyResolver` schedules the next attempt (default **7 days** for the first rebill retry via `SUBSCRIPTION_REBILL_FIRST_RETRY_DAYS`).
- Subsequent retries rotate the starting gateway (`retry_count`) so the next priority path is used.

## Core flows

### Enrollment (checkout / API)

1. `SubscriptionLifecycleService::createFromPlan()` creates subscription in `trial` or `active`.
2. `BillingScheduleCalculator` sets `next_billing_at` from first step.
3. Payment token + provider stored for rebills.

### Scheduled rebill (Laravel Scheduler)

1. `subscriptions:process-due` (hourly / configurable) loads due subscriptions.
2. Dispatches `ChargeSubscriptionJob` per subscription (Redis lock).
3. `RebillService::charge()` resolves amount → authorize (+ capture) → ledger → advance cycle or retry.

### Failed payment & retry

1. Gateway failure → `SubscriptionRebillFailed`.
2. `RetryPolicyResolver` increments `retry_count`, sets `past_due`, schedules `next_billing_at` via `retry_offsets`.
3. Exceeded `max_attempts` → cancel with `retries_exhausted` → `SubscriptionExpired`.
4. Admin / API `retry` → `FailedPaymentRetryService` → `RetryFailedSubscriptionPaymentJob`.

### Dunning

1. On rebill failure, `DunningService::schedule()` sets `dunning_stage` and `next_dunning_at` from `dunning_policies.stages`.
2. `subscriptions:process-dunning` dispatches `ProcessDunningStageJob`.
3. Records `subscription_dunning_events`; fires `SubscriptionDunningTriggered` (email / webhooks plug in here).

### Pause / resume / cancel

- **Pause**: freeze billing; store remaining days until `next_billing_at`.
- **Resume**: restore `next_billing_at` from paused remainder.
- **Cancel**: terminal state; no further jobs.

### Webhooks

- `ReconcileSubscriptionPaymentWebhookListener` listens to `PaymentEventReceived`.
- Matches `metadata.subscription_id`; syncs failures / success with subscription state and optional immediate retry.

## Data model

| Table | Purpose |
|-------|---------|
| `subscription_plans` | Productized billing templates |
| `subscription_plan_steps` | Ordered steps + `cycle_type` |
| `retry_policies` | Max attempts + day offsets between retries |
| `dunning_policies` | JSON stages (day offset, channel) |
| `subscriptions` | Runtime state, tokens, retry/dunning fields |
| `billing_ledger_entries` | Immutable charge attempts |
| `subscription_dunning_events` | Dunning communications log |

## Queues & scheduler

| Command | Job | Queue |
|---------|-----|-------|
| `subscriptions:process-due` | `ChargeSubscriptionJob` | `subscription` |
| `subscriptions:process-dunning` | `ProcessDunningStageJob` | `subscription` |
| Manual retry | `RetryFailedSubscriptionPaymentJob` | `subscription` |

Configured in `config/subscription.php` and registered in `SubscriptionServiceProvider`.

## API (`/api/v1/subscriptions`)

| Method | Path | Action |
|--------|------|--------|
| GET | `{uuid}` | Show |
| POST | `{uuid}/pause` | Pause |
| POST | `{uuid}/resume` | Resume |
| POST | `{uuid}/cancel` | Cancel |
| POST | `{uuid}/retry` | Retry failed payment |

## Events

- `SubscriptionRebilled`, `SubscriptionRebillFailed`, `SubscriptionExpired`
- `SubscriptionPaused`, `SubscriptionResumed`, `SubscriptionCancelled`
- `SubscriptionDunningTriggered`, `SubscriptionRetryScheduled`

## Extension points

- Notification listeners on dunning / rebill events
- Custom `BillingCycleType` values via plan step metadata
- Provider-specific webhook normalizers under `StripeWebhook` module
