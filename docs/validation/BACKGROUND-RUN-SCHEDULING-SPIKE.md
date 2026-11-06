# Phase 6 — Background run scheduling spike

**Status:** Queue lifecycle and delayed-run behavior specified from official references; runtime/concurrency behavior not tested.  
**Date:** 2026-11-06  
**Task:** SPIKE-007  
**Evidence class:** Architecture proposal based on WordPress and Action Scheduler documentation. No job was enqueued or executed.

## Decision proposal

Use Action Scheduler as the WooCommerce-backed job queue for supported run work, after WooCommerce and Action Scheduler have initialized. Queue one bounded action per scenario/run step, carrying only stable record IDs and a run correlation key; keep scenario data and observations in the plugin's protected storage, not serialized into scheduler arguments.

This is a proposal pending implementation and staging verification. Do not call the runner synchronously from plugin update/settings hooks.

## Queue and state ownership

- SYS-04 Run Orchestrator owns run lifecycle, idempotency, retry count, cancellation and status.
- Action Scheduler owns dispatch state only: pending/in-progress/complete/failed and queue timing.
- A unique scheduled action is queue-level duplicate reduction, not proof of exactly-once processing. The run record's atomic transition/idempotency key is the authoritative guard.
- One action processes one bounded scenario step. It must respect a hard timeout/resource budget, update progress, and yield or reschedule rather than loop through an unbounded suite.
- Queue hooks receive opaque IDs only. No raw cart token, address, coupon secret, gateway data or full scenario payload is placed in action arguments.
- A fresh worker rechecks run state, scenario/baseline versions, environment/capability fingerprint, permission policy and safety gate before doing any work.
- Failed or timed-out actions do not automatically mean the checkout scenario failed; distinguish queue failure from observation result.
- Retry policy is product-owned, capped, delayed and idempotent. Do not assume Action Scheduler will retry a failed action automatically.
- On deactivation, no new runs are scheduled and pending runs become paused/cancelled according to a documented recovery policy. On uninstall, pending actions in the product group are removed only when the merchant-selected data/uninstall policy allows it.

## Scheduling and operational limits

Action Scheduler schedules recurring queue checks via WordPress's scheduler/loopback model. WP-Cron itself is page-load triggered and may run late on low-traffic sites or when loopback/cron is disabled. Therefore:

- A queued run may be delayed; show queued/delayed with last-attempt and next-eligible time, not “running” or “complete”.
- Measure queue delay separately from scenario execution duration.
- Check whether Action Scheduler functions and schema are available before enqueueing. If unavailable, fail visibly and do not silently run checkout work synchronously.
- Do not add a CLI command or CLI-based setup/operation to the product. Existing administrator diagnostics may link to WooCommerce/Action Scheduler's built-in screens but must not require them.
- Manual recovery/retry in the product UI must pass capabilities/nonces and preserve the run idempotency contract.
- Scheduling uniqueness must be scoped by product group and run/scenario key. Do not use one global unique hook that blocks distinct saved scenarios.
- Limit concurrent checkout sessions by a conservative configurable cap. Concurrency must be proven not to cross-contaminate Cart Tokens or overload provider extensions.

## Proposed state transitions

| From | Trigger | To | Required guard |
|---|---|---|---|
| pending | Worker claims the action | running | Atomic run claim succeeds; stale/duplicate worker exits without side effects. |
| running | Step returns supported observations | complete or partial | Store results first; transition only if run/version/context still matches. |
| running | Unsupported mode or safety gate denied | blocked | No checkout mutation occurs. |
| running | Transient infrastructure failure | retry_wait | Retry budget remains; delay/backoff persisted in run record. |
| running | Retry cap exceeded or permanent failure | failed | Error category is safe/redacted and visible; no silent green result. |
| any nonterminal | User cancellation/deactivation policy | cancelled or paused | Worker checks cancellation before each stateful operation. |

A run may be queued successfully but not execute promptly. A job can also execute at least once under queue recovery conditions; every step must therefore be safe to resume and idempotent.

## Runtime acceptance cases

- **QUEUE-01:** enqueue one action and verify it is dispatched after the source hook/request has completed, not inside an update/settings callback.
- **QUEUE-02:** enqueue duplicate run IDs concurrently; exactly one run record owns execution and no duplicate cart mutation occurs.
- **QUEUE-03:** enqueue two distinct scenario IDs; both remain schedulable despite queue-level uniqueness.
- **QUEUE-04:** force a worker timeout/crash after state transition; recovery resumes or marks inconclusive without replaying a committed mutation.
- **QUEUE-05:** simulate Action Scheduler unavailable, WP-Cron disabled, loopback failure and low traffic; user sees delayed/blocked state and no synchronous checkout fallback occurs.
- **QUEUE-06:** exceed retry budget; run becomes failed/inconclusive with redacted reason and no false regression/pass.
- **QUEUE-07:** deactivate the plugin with pending jobs; no new checkout operation starts and documented pause/cancel policy is honored.
- **QUEUE-08:** inspect stored job arguments; only opaque IDs/correlation data appear, never scenario PII, secrets or raw Cart Tokens.
- **QUEUE-09:** verify product has no CLI command, CLI setup flow or CLI-only recovery dependency.

## Sources reviewed 2026-11-06

- [Action Scheduler API](https://actionscheduler.org/api/) — enqueue/schedule APIs, unique flag and action IDs.
- [Action Scheduler usage](https://actionscheduler.org/usage/) — initialization and usage patterns.
- [Action Scheduler overview](https://actionscheduler.org/) — runner checks and batch claim behavior.
- [WordPress Cron handbook](https://developer.wordpress.org/plugins/cron/) — WP-Cron is triggered by page loads and can be delayed.
- [WordPress loopback requests](https://developer.wordpress.org/advanced-administration/wordpress/loopback/) — loopback role in starting scheduled work.
- [Action Scheduler admin screen](https://actionscheduler.org/admin/) — administrator monitoring/debugging interface.

## Completion boundary

SPIKE-007 is complete as a queue lifecycle proposal. Dispatch latency, concurrency, deactivation recovery, retry idempotency and checkout-session isolation remain untested; queue documentation does not prove the product's runner is safe.
