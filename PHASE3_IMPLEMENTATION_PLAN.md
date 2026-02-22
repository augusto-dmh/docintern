# Phase 3 Completion — Implementation Plan

## Context

Phase 2.5 tenancy hardening and Phase 2.6 frontend final polish are complete. PR #17 (`Finalize phase 2 polish and regression coverage`) was merged on February 22, 2026.

Phase 3 starts from `main`. The current application still lacks RabbitMQ queue-driver wiring, dedicated pipeline consumers, processing state/idempotency schemas, and queue health observability UI.
This phase keeps local simulation-capable provider adapters as the default runtime mode. Production provider cutover is handled in Phase 3.5.
An intermediary Docker infrastructure stabilization PR (`fix/docker-node-wayfinder-php`) was implemented after PR #18 to keep the `node` service healthy for Wayfinder + Vite workflows.

---

## Target Outcome After Phase 3

- Document upload dispatches fan-out processing events through RabbitMQ.
- Documents transition through processing statuses using a guarded state machine.
- Consumer failures retry with backoff and then dead-letter correctly.
- OCR and classification outputs are persisted for downstream review flows.
- Super-admin users can inspect queue depth and DLQ status from an admin queue-health surface.
- Local Docker development is stable: `node` supports Wayfinder type generation and can run Vite without restart loops.

---

## Public APIs, Interfaces, and Types Locked in This Phase

- **Message contract fields:** `message_id`, `trace_id`, `tenant_id`, `document_id`, `event`, `timestamp`, `metadata`, `retry_count`
- **Artisan command interfaces:**
  - `docintern:setup-rabbitmq`
  - `docintern:consume {pipeline}`
- **Persistence interfaces:**
  - `processing_events` (idempotency + trace log)
  - `extracted_data` (OCR output)
  - `document_classifications` (document type + confidence)
- **Operational interfaces:**
  - worker/scheduler containers
  - RabbitMQ `definitions.json` bootstrap
  - queue-health admin endpoint + Inertia page
- **Configuration interfaces:**
  - RabbitMQ connectivity (`RABBITMQ_*`)
  - retry/backoff tuning
  - provider mode flags for simulated vs AWS-backed OCR/classification

---

## Decisions

1. **PR structure:** Phase 3 execution now includes 1 intermediary infrastructure PR plus 4 sequential feature PRs.
2. **OCR/classification integration:** adapter pattern with local simulation as default; production AWS providers are supported through configuration.
3. **RabbitMQ provisioning:** combine mounted `definitions.json` with an idempotent `docintern:setup-rabbitmq` command.
4. **Status persistence format:** normalized snake_case values to match existing model conventions.
5. **Docker reliability gate:** feature PR work proceeds only after the `node` container can run Wayfinder generation in Docker.

---

## Step 0: Intermediary Docker Infrastructure Stabilization (Implemented)

1. Update `docker/node/Dockerfile` so the `node` service can execute Wayfinder generation:
   - install `php83` + required extensions
   - provide `/usr/bin/php` symlink
2. Update Node container startup command to bootstrap npm dependencies when missing, then run Vite.
3. Update Docker documentation to keep implementation and specs aligned.
4. Verify:
   - `make npm install` succeeds
   - `docker compose logs node` shows Wayfinder generation success
   - `docker compose ps node` remains healthy/up

---

## Step 1: RabbitMQ Foundation

1. Install and configure `vyuldashev/laravel-queue-rabbitmq`.
2. Add `rabbitmq` connection in `config/queue.php` with exchange and failure routing defaults.
3. Add Docker assets:
   - `docker/workers/Dockerfile`
   - `docker/workers/supervisord.conf`
   - `docker/scheduler/Dockerfile`
   - `docker/rabbitmq/definitions.json`
4. Update `docker-compose.yaml`:
   - mount RabbitMQ definitions
   - add `worker` service
   - add `scheduler` service
   - propagate required RabbitMQ/AWS environment variables
5. Update `Makefile` with worker/scheduler/queue ops commands (`worker-logs`, `worker-restart`, `worker-shell`, `rabbitmq-queues`, `scheduler-logs`).
6. Add command entry points:
   - `docintern:setup-rabbitmq` (idempotent declarations/bootstrap)
   - `docintern:consume {pipeline}` skeleton for supported consumers

---

## Step 2: Processing Domain and Schema

1. Add `processing_trace_id` column to `documents`.
2. Create `processing_events`, `extracted_data`, and `document_classifications` migrations.
3. Add corresponding Eloquent models and factories.
4. Implement status transition service:
   - enforces allowed transitions
   - blocks invalid out-of-order transitions
   - records transition events in `processing_events`
5. Emit domain events on transition boundaries for pipeline dispatch and observability.

---

## Step 3: Publish and Idempotency

1. Emit upload fanout message containing `message_id`, `trace_id`, tenant/document identifiers, and metadata.
2. Apply idempotency guard before consumer side effects using key:
   - `(tenant_id, message_id, consumer_name)`
3. Record consume attempts and outcomes in `processing_events` for replay/debug visibility.
4. Ensure idempotency behavior is deterministic for retries and broker redelivery.

---

## Step 4: Consumers and Routing

1. Implement `docintern:consume {pipeline}` variants for:
   - `virus-scan`
   - `audit-log`
   - `ocr-extraction`
   - `classification`
   - `dead-letters`
2. Configure retry behavior (3 attempts, exponential backoff) and dead-letter routing.
3. Implement failure handling to set terminal statuses:
   - `scan_failed`
   - `extraction_failed`
   - `classification_failed`
4. Implement classification topic routing for type-specific queues with fallback to general classification.
5. Enforce tenant-safe processing in all consumer entry points.

---

## Step 5: Queue Health Observability

1. Add RabbitMQ management integration service for queue depth, consumer count, and DLQ totals.
2. Add super-admin-only queue health route/controller.
3. Add `QueueHealth` Inertia page to show queue and DLQ state.
4. Keep queue health data read-only and operationally focused.

---

## Step 6: Tests and Rollout Checks

1. Add feature/integration tests for:
   - status transitions
   - event publication payload contracts
   - idempotency on duplicate redelivery
   - retry and DLQ behavior
   - queue health authorization and visibility
2. Add tenant-safety assertions in all processing tests to prevent cross-tenant leakage.
3. Run focused suites first, then full regression and frontend build.

---

## Test Matrix

| Scenario | Expected Result |
| --- | --- |
| Upload fanout dispatch contains trace + tenant context | Message contract includes `message_id`, `trace_id`, `tenant_id`, `document_id`, and metadata |
| Duplicate message redelivery | Consumer work is ignored idempotently after first successful processing |
| Simulated virus failure | Document transitions to `scan_failed` and processing stops for success path |
| OCR exception after retries | Document transitions to `extraction_failed` and message appears in DLQ path |
| Classification success | Classification record stores type/confidence and document status advances correctly |
| Cross-tenant processing attempt | Attempt is denied/logged; no cross-tenant state mutation occurs |
| Queue health access control | Super-admin allowed; non-admin denied |

---

## Final Verification

```bash
# Backend formatting
docker compose exec app vendor/bin/pint --dirty --format agent

# Focused processing and observability tests
docker compose exec app php artisan test --compact tests/Feature/Processing
docker compose exec app php artisan test --compact tests/Feature/Admin/QueueHealthTest.php

# Full backend regression
docker compose exec app php artisan test --compact

# Frontend build
npm run build
```

---

## Phase 3.5 Continuation Rule

Phase 3.5 starts only after PR #18, the intermediary Docker stabilization PR (`fix/docker-node-wayfinder-php`), and all remaining Phase 3 feature PRs are merged and queue-health + DLQ regression checks are green.
Phase 4 starts only after Phase 3.5 infrastructure cutover and live-provider regression checks are green.

---

## Assumptions and Defaults

1. File names are `PHASE3_BRANCHING_STRATEGY.md` and `PHASE3_IMPLEMENTATION_PLAN.md`.
2. PR numbering started at #18 for Phase 3 foundation; an intermediary Docker stabilization PR is inserted before the remaining planned feature PRs.
3. Local development does not require live Textract/Comprehend credentials; adapters provide simulated behavior locally with production providers wired through configuration.
4. Queue health is an administrative capability and defaults to super-admin-only access.
