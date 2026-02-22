# Phase 3 Completion — Branch, Commit & PR Strategy

## Conventions (same as Phase 1 / Phase 2 / Phase 2.5 / Phase 2.6)

- **Branches**: `feat/<area>-<description>` off `main`
- **Commits**: `feat:` / `fix:` prefix, lowercase, imperative mood, concise
- **PRs**: Merge to `main`. Title under 70 chars, imperative. Description follows PR_GUIDELINES.md (Why / What changed / Notes)
- **Granularity**: Each PR is a self-contained, deployable unit. Each commit is a logical chunk within the PR.

---

## Why This Phase Exists

Phase 2.6 is complete and PR #17 (`Finalize phase 2 polish and regression coverage`) is merged into `main` on February 22, 2026.

Phase 3 starts from `main` and introduces the asynchronous document processing pipeline through RabbitMQ in deployable increments.
It intentionally ships with adapter-driven simulation defaults for virus scan/OCR/classification in local and test environments.
The production provider cutover to fully real infrastructure is handled in an explicit intermediary Phase 3.5.

---

## PR Breakdown (5 PRs total: 1 intermediary + 4 phase PRs)

Each PR depends on the previous one being merged into `main` first.

---

### PR #18: `feat/rabbitmq-phase3-foundation`

**Branch:** `feat/rabbitmq-phase3-foundation`  
**Title:** Bootstrap RabbitMQ driver and worker infrastructure  
**Scope:** package install/config, queue connection wiring, Docker phase-3 worker/scheduler/definitions, bootstrap command

**Commits:**
1. `feat: install and configure rabbitmq queue driver`
2. `feat: add phase 3 worker scheduler and rabbitmq definitions`
3. `feat: add idempotent rabbitmq setup and consume commands skeleton`
4. `test: add rabbitmq configuration and bootstrap command coverage`

**PR Description:**

```markdown
## Why

Phase 3 requires a reliable RabbitMQ-backed execution model before any processing consumers can be implemented. The current stack still uses non-RabbitMQ queue defaults and has no worker/scheduler services for pipeline consumers.

## What changed

- Installed and configured `vyuldashev/laravel-queue-rabbitmq`
- Added a `rabbitmq` queue connection and required environment configuration
- Added Phase 3 Docker assets:
  - `docker/workers/Dockerfile`
  - `docker/workers/supervisord.conf`
  - `docker/scheduler/Dockerfile`
  - `docker/rabbitmq/definitions.json`
- Updated `docker-compose.yaml` and `Makefile` with worker/scheduler/queue operations
- Added idempotent `docintern:setup-rabbitmq` and `docintern:consume {pipeline}` command skeletons
- Added tests for queue configuration and RabbitMQ setup command behavior

## Notes

- This PR provides infrastructure and command contracts only; business pipeline logic is delivered in follow-up PRs
- RabbitMQ provisioning is intentionally both definition-mounted and command-bootstrapped for repeatable environments
```

---

### Intermediary PR: `fix/docker-node-wayfinder-php` (implemented)

**Branch:** `fix/docker-node-wayfinder-php`  
**Title:** Stabilize Docker node service for Wayfinder and Vite startup  
**Scope:** fix local Docker runtime so `node` can generate Wayfinder types and stay healthy

**Commits:**
1. `fix: add php cli support to node container for wayfinder`
2. `docs: align docker specification with node runtime requirements`
3. `docs: record intermediary docker stabilization in phase 3 plans`

**PR Description:**

```markdown
## Why

Local development was blocked by an unstable `node` service. The container restarted continuously because Wayfinder runs `php artisan wayfinder:generate --with-form` during Vite startup, but the Node image did not include a `php` binary. This prevented `make npm install` and normal HMR startup.

## What changed

- Added PHP CLI and required extensions to the Node container image
- Added a `php` symlink (`/usr/bin/php83` -> `/usr/bin/php`) so Wayfinder can execute inside the Node container
- Updated Node container startup to install npm dependencies when missing before launching Vite
- Updated Docker infrastructure documentation to match the new Node runtime contract

## Notes

- This is an infrastructure stabilization PR inserted between PR #18 and the remaining Phase 3 feature PRs
- It does not change business pipeline behavior; it restores a reliable local execution baseline
```

---

### PR #19: `feat/document-processing-state-machine`

**Branch:** `feat/document-processing-state-machine`  
**Title:** Add document processing state machine and traceability  
**Scope:** status workflow, transition service, trace/idempotency schema, domain events

**Commits:**
1. `feat: add processing events extracted data and classification schemas`
2. `feat: implement document status transition service and guards`
3. `feat: publish processing domain events with trace ids`
4. `test: add document state transition and idempotency tests`

**PR Description:**

```markdown
## Why

Consumers must run against a strict, auditable status model. Without explicit transition guards and idempotency persistence, retries and redeliveries can corrupt pipeline state.

## What changed

- Added persistence layer for pipeline observability and idempotency:
  - `processing_events`
  - `extracted_data`
  - `document_classifications`
- Added `processing_trace_id` support for document-level tracing across all stages
- Implemented a document status transition service enforcing allowed transitions and failure paths
- Added domain event publishing with `message_id` and `trace_id` propagation
- Added tests covering transition validity, idempotency keys, and trace propagation

## Notes

- Status values remain normalized snake_case to match current model conventions
- This PR establishes processing contracts used by all consumers in Phase 3
```

---

### PR #20: `feat/document-pipeline-consumers`

**Branch:** `feat/document-pipeline-consumers`  
**Title:** Implement pipeline consumers with retry and DLQ handling  
**Scope:** virus scan, OCR, classification, audit, DLQ consumers; retry/backoff; tenant-safe processing

**Commits:**
1. `feat: add virus scan and audit log upload consumers`
2. `feat: add ocr extraction consumer using provider adapters`
3. `feat: add classification consumer and topic routing logic`
4. `feat: add dead letter consumer and failed status transitions`
5. `test: add end to end processing consumer coverage`

**PR Description:**

```markdown
## Why

Phase 3's core value is asynchronous pipeline execution after upload. The system needs production-shaped consumers with retry, failure isolation, and tenant-safe handling.

## What changed

- Implemented consumers for:
  - virus scan (simulated pass/fail, integration-ready)
  - audit log fanout
  - OCR extraction via adapter interface
  - classification with topic routing
  - dead-letter handling
- Implemented retries with exponential backoff and DLQ routing after retry exhaustion
- Added idempotency checks before consumer side effects
- Updated document failure transitions (`scan_failed`, `extraction_failed`, `classification_failed`) on terminal failure
- Added end-to-end processing coverage for success/failure paths and tenant boundaries

## Notes

- OCR/classification default to local simulation adapters; AWS-backed providers are wired through config for production use
- Consumer contracts use `docintern:consume {pipeline}` from PR #18
```

---

### PR #21: `feat/queue-health-observability`

**Branch:** `feat/queue-health-observability`  
**Title:** Add queue health admin surface and phase 3 regression  
**Scope:** RabbitMQ management metrics integration, QueueHealth page, authorization, final regression set

**Commits:**
1. `feat: add rabbitmq queue health service and admin endpoint`
2. `feat: add queue health inertia page with dlq visibility`
3. `test: add queue health authorization and processing regression coverage`

**PR Description:**

```markdown
## Why

Operations and tenant admins need confidence that queues are healthy and dead letters are visible. Phase 3 signoff requires an explicit observability surface and final regression checks.

## What changed

- Added RabbitMQ management integration for queue depth and DLQ metrics
- Added admin endpoint and Inertia `QueueHealth` page for operational visibility
- Enforced access control for queue health features (super-admin only)
- Added regression coverage across queue health authorization and Phase 3 pipeline behavior

## Notes

- This PR completes the Phase 3 functional and operational quality gate
- Queue health data is read-only and scoped to operational monitoring
```

---

## Dependency Rule for Phase 3.5 and Phase 4

- **Phase 3.5 starts only after PR #18, the intermediary Docker stabilization PR (`fix/docker-node-wayfinder-php`), PR #19, PR #20, and PR #21 are merged into `main`.**
- **Phase 4 starts only after Phase 3.5 is complete and merged into `main`.**

---

## Workflow for Each PR

```bash
1. git checkout main && git pull
2. git checkout -b feat/<branch-name>
3. Implement + commit in logical chunks
4. docker compose exec app vendor/bin/pint --dirty --format agent
5. docker compose exec app php artisan test --compact <target>
6. npx eslint . --fix
7. npx prettier --write resources/
8. npm run build
9. git push -u origin feat/<branch-name>
10. Create PR on GitHub (title + description from above)
11. Merge PR on GitHub
12. Repeat for next PR
```

---

## Execution Status (February 22, 2026)

- PR #18 (`Bootstrap RabbitMQ driver and worker infrastructure`) is merged into `main`.
- Intermediary Docker infrastructure stabilization PR is implemented on `fix/docker-node-wayfinder-php` and must merge before continuing Phase 3 feature PRs.
- Planned feature PRs (`feat/document-processing-state-machine`, `feat/document-pipeline-consumers`, `feat/queue-health-observability`) remain pending.
