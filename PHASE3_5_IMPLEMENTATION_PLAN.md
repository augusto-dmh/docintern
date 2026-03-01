# Phase 3.5 Completion — Implementation Plan

## Context

Phase 3 introduces the async pipeline with simulation-capable provider adapters and queue-health observability.
This intermediary Phase 3.5 performs the live infrastructure cutover so Phase 4 can focus on real-time UX without mixing infra-risk and UI-risk in the same phase.

---

## Target Outcome After Phase 3.5

- Production and staging run with live OCR/classification providers by default.
- Simulation providers remain opt-in for local/testing only.
- Queue, DLQ, and provider integrations are operationally verified against real infrastructure.
- Tenant-safe behavior and retry/idempotency guarantees remain intact in live mode.

---

## Decisions

1. **Phase boundary:** Live provider cutover is isolated in Phase 3.5 (not bundled into Phase 3 or Phase 4).
2. **Provider mode strategy:** `simulated` allowed only for local/testing; `live` required for production-like environments.
3. **Cutover safety:** fail-fast boot validation for missing required live configuration.
4. **Observability requirement:** queue-health and DLQ checks are mandatory phase gate criteria.
5. **Selective cloud rule:** production setup uses AWS only for currently required integrations (S3 + live OCR/classification + chosen mail transport), while DB/Redis/RabbitMQ remain provider-agnostic.

---

## Step 0: Production Service Setup (Current Components Only)

1. Define the production service matrix for currently implemented features (required vs optional):
   - required for current document pipeline: S3-compatible object storage, queue broker (RabbitMQ-compatible), relational DB, Redis cache/session store;
   - required when `PROCESSING_OCR_PROVIDER=live`: OCR provider credentials/settings;
   - required when `PROCESSING_CLASSIFICATION_PROVIDER=live`: classification provider credentials/settings;
   - required for outbound email: production mail transport (`ses` or `smtp`).
2. If AWS is used for required integrations, create a dedicated production AWS account (for example `docintern-production`) and apply baseline controls:
   - enable MFA and block routine use of root credentials;
   - enable CloudTrail, AWS Config, GuardDuty, and cost/budget alarms;
   - use IAM roles for workloads and keep long-lived access keys out of runtime containers.
3. Provision production network baseline:
   - VPC with private subnets for data-plane services;
   - security groups that allow only required app-to-service traffic.
4. Provision current production service equivalents based on your matrix:
   - Docker/LocalStack object storage flow -> production object storage (`AWS S3` recommended);
   - local Mailpit -> production mail transport (`AWS SES` or approved SMTP provider);
   - Docker `mysql`, `redis`, and `rabbitmq` -> production-managed equivalents (AWS-managed or non-AWS-managed, per platform choice).
5. Configure required environment contract for current code paths:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `FILESYSTEM_DISK=s3`
   - `QUEUE_CONNECTION=rabbitmq`
   - `PROCESSING_QUEUE_CONNECTION=rabbitmq`
   - `PROCESSING_OCR_PROVIDER=live`
   - `PROCESSING_CLASSIFICATION_PROVIDER=live`
   - `AWS_DEFAULT_REGION=<region>`
   - `AWS_BUCKET=<bucket>`
   - `AWS_ENDPOINT=` (empty for native AWS S3; set only when required by your object-store provider)
   - `AWS_USE_PATH_STYLE_ENDPOINT=false` (set per object-store provider requirements)
   - `DB_CONNECTION=mysql`
   - `DB_HOST=<database-endpoint>`
   - `DB_PORT=3306`
   - `DB_DATABASE=<production-db>`
   - `DB_USERNAME=<production-user>`
   - `DB_PASSWORD=<production-secret>`
   - `REDIS_HOST=<redis-endpoint>`
   - `REDIS_PORT=6379`
   - `REDIS_PASSWORD=<redis-secret>`
   - `RABBITMQ_HOST=<rabbitmq-endpoint>`
   - `RABBITMQ_PORT=<rabbitmq-port>`
   - `RABBITMQ_USER=<rabbitmq-user>`
   - `RABBITMQ_PASSWORD=<rabbitmq-password>`
   - `RABBITMQ_VHOST=<rabbitmq-vhost>`
   - `RABBITMQ_MANAGEMENT_SCHEME=<http|https>`
   - `RABBITMQ_MANAGEMENT_HOST=<rabbitmq-management-endpoint>`
   - `RABBITMQ_MANAGEMENT_PORT=<rabbitmq-management-port>`
   - `RABBITMQ_MANAGEMENT_USER=<rabbitmq-management-user>`
   - `RABBITMQ_MANAGEMENT_PASSWORD=<rabbitmq-management-password>`
   - `RABBITMQ_MANAGEMENT_VHOST=<rabbitmq-management-vhost>`
   - `RABBITMQ_MANAGEMENT_TIMEOUT=5`
   - `OPENAI_API_KEY=<openai-api-key>`
   - `PROCESSING_OPENAI_MODEL=<openai-model>`
   - `OPENAI_BASE_URL=https://api.openai.com/v1`
   - `PROCESSING_OPENAI_TIMEOUT=15`
   - `MAIL_MAILER=<ses|smtp>`
   - `MAIL_FROM_ADDRESS=<noreply@your-domain>`
   - `MAIL_FROM_NAME=<Docintern>`
6. Store secrets in a production secret manager (AWS Secrets Manager/SSM or equivalent) and map them into runtime without committing plaintext secrets.
7. Run production cutover smoke checks:
   - `php artisan migrate --force`;
   - upload a document and confirm S3 object persistence;
   - open `/documents/{id}` and confirm classification output is visible once status is `ready_for_review`;
   - perform manual review actions in UI (`Mark Reviewed` then `Approve Document`) and verify status transitions;
   - verify queue processing and `admin/queue-health` with live queue metrics;
   - force a controlled failure and confirm DLQ visibility.

---

## Step 0.1: External Services Configuration Runbook (Beginner-Friendly)

Use this as a practical checklist to configure each external dependency for the current project scope.

### A) Object Storage (S3-Compatible; AWS S3 Recommended)

1. Create a bucket dedicated to production documents (for example `docintern-prod`).
2. Enable bucket versioning.
3. Keep public access blocked.
4. Apply encryption at rest (SSE-S3 or KMS).
5. Configure application environment:
   - `FILESYSTEM_DISK=s3`
   - `AWS_DEFAULT_REGION=<your-region>`
   - `AWS_BUCKET=<your-bucket>`
   - `AWS_ENDPOINT=` (empty for native AWS S3)
   - `AWS_USE_PATH_STYLE_ENDPOINT=false`
6. Configure credentials:
   - preferred: runtime IAM role with S3 permissions for this bucket only;
   - fallback: `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` in secret manager.
7. Verify:
   - upload a document from the app UI;
   - confirm object exists under tenant-prefixed key in the bucket.

### B) Mail Transport (SES or SMTP Provider)

1. Decide transport:
   - `ses` if using AWS SES;
   - `smtp` if using another production SMTP provider.
2. If using SES:
   - verify sender domain/address;
   - if account is in SES sandbox, request production access.
3. Configure application environment:
   - `MAIL_MAILER=<ses|smtp>`
   - `MAIL_FROM_ADDRESS=<noreply@your-domain>`
   - `MAIL_FROM_NAME=<Docintern>`
4. For SMTP mode also set:
   - `MAIL_HOST=<smtp-host>`
   - `MAIL_PORT=<smtp-port>`
   - `MAIL_USERNAME=<smtp-user>`
   - `MAIL_PASSWORD=<smtp-password>`
   - `MAIL_SCHEME=<tls|ssl|null>`
5. Verify:
   - trigger a known outbound email flow and confirm delivery.

### C) RabbitMQ Broker + Management API

1. Provision a production RabbitMQ-compatible broker.
2. Create dedicated application user and vhost.
3. Restrict network access so only app/worker/scheduler can reach broker and management endpoints.
4. Configure application environment:
   - `QUEUE_CONNECTION=rabbitmq`
   - `PROCESSING_QUEUE_CONNECTION=rabbitmq`
   - `RABBITMQ_HOST=<rabbitmq-endpoint>`
   - `RABBITMQ_PORT=<rabbitmq-port>`
   - `RABBITMQ_USER=<rabbitmq-user>`
   - `RABBITMQ_PASSWORD=<rabbitmq-password>`
   - `RABBITMQ_VHOST=<rabbitmq-vhost>`
   - `RABBITMQ_MANAGEMENT_SCHEME=<http|https>`
   - `RABBITMQ_MANAGEMENT_HOST=<rabbitmq-management-endpoint>`
   - `RABBITMQ_MANAGEMENT_PORT=<rabbitmq-management-port>`
   - `RABBITMQ_MANAGEMENT_USER=<rabbitmq-management-user>`
   - `RABBITMQ_MANAGEMENT_PASSWORD=<rabbitmq-management-password>`
   - `RABBITMQ_MANAGEMENT_VHOST=<rabbitmq-management-vhost>`
   - `RABBITMQ_MANAGEMENT_TIMEOUT=5`
5. Configure live processing mode:
   - `PROCESSING_OCR_PROVIDER=live`
   - `PROCESSING_CLASSIFICATION_PROVIDER=live`
   - `OPENAI_API_KEY=<openai-api-key>`
   - `PROCESSING_OPENAI_MODEL=<openai-model>`
6. Verify:
   - open `/admin/queue-health` as super-admin and confirm queue metrics are available.

### D) Relational Database (MySQL-Compatible)

1. Provision a production MySQL instance.
2. Create dedicated DB/user for the app.
3. Restrict DB network access to app runtime only.
4. Configure application environment:
   - `DB_CONNECTION=mysql`
   - `DB_HOST=<database-endpoint>`
   - `DB_PORT=3306`
   - `DB_DATABASE=<production-db>`
   - `DB_USERNAME=<production-user>`
   - `DB_PASSWORD=<production-secret>`
5. Verify:
   - run `php artisan migrate --force`;
   - run `php artisan migrate:status`.

### E) Redis (Cache/Session Store)

1. Provision a production Redis instance.
2. Require authentication and restrict network access.
3. Configure application environment:
   - `SESSION_DRIVER=redis`
   - `CACHE_STORE=redis`
   - `REDIS_HOST=<redis-endpoint>`
   - `REDIS_PORT=6379`
   - `REDIS_PASSWORD=<redis-secret>`
4. Verify:
   - log in/out and confirm sessions work;
   - use features that hit cache and confirm no connection errors.

### F) Secrets Mapping (Do This Before Deploy)

1. Put all secrets in a secret manager (not in git, not in image, not in compose files).
2. Keep separate secret sets for `staging` and `production`.
3. Rotate credentials after first successful production cutover.
4. Minimum secrets to store:
   - DB password;
   - Redis password;
   - RabbitMQ password(s);
   - SMTP credentials (if using SMTP);
   - AWS access keys only if IAM role is not used.

### G) Preflight Before First Production Traffic

1. `APP_ENV=production` and `APP_DEBUG=false` confirmed.
2. `PROCESSING_OCR_PROVIDER=live` and `PROCESSING_CLASSIFICATION_PROVIDER=live` confirmed.
3. Queue health page returns live metrics (not unavailable state).
4. End-to-end document processing succeeds on a real uploaded file.
5. Controlled-failure test reaches DLQ and is visible operationally.

---

## Step 0.2: `.env.production` Template (Copy and Fill)

Use this as your base production environment file and replace all `REPLACE_WITH_*` and `your-*` placeholders.

```dotenv
APP_NAME=Docintern
APP_ENV=production
APP_KEY=base64:REPLACE_WITH_PRODUCTION_APP_KEY
APP_DEBUG=false
APP_URL=https://app.your-domain.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=your-db-endpoint
DB_PORT=3306
DB_DATABASE=docintern_production
DB_USERNAME=docintern_app
DB_PASSWORD=REPLACE_WITH_DB_PASSWORD

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.your-domain.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=s3
QUEUE_CONNECTION=rabbitmq

CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_HOST=your-redis-endpoint
REDIS_PASSWORD=REPLACE_WITH_REDIS_PASSWORD
REDIS_PORT=6379

# Mail: choose one
MAIL_MAILER=ses
# MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME=Docintern

AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-production-bucket
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
# Prefer IAM role in production. Only set keys when IAM role is not available.
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=

RABBITMQ_HOST=your-rabbitmq-endpoint
RABBITMQ_PORT=5672
RABBITMQ_USER=docintern
RABBITMQ_PASSWORD=REPLACE_WITH_RABBITMQ_PASSWORD
RABBITMQ_VHOST=/docintern
RABBITMQ_QUEUE=default
RABBITMQ_CONNECTION=default
RABBITMQ_EXCHANGE=
RABBITMQ_EXCHANGE_TYPE=direct
RABBITMQ_ROUTING_KEY=
RABBITMQ_FAILED_EXCHANGE=docintern.dlx
RABBITMQ_FAILED_ROUTING_KEY=dlq.%s
RABBITMQ_WORKER=default

RABBITMQ_MANAGEMENT_SCHEME=https
RABBITMQ_MANAGEMENT_HOST=your-rabbitmq-management-endpoint
RABBITMQ_MANAGEMENT_PORT=443
RABBITMQ_MANAGEMENT_USER=docintern
RABBITMQ_MANAGEMENT_PASSWORD=REPLACE_WITH_RABBITMQ_MANAGEMENT_PASSWORD
RABBITMQ_MANAGEMENT_VHOST=/docintern
RABBITMQ_MANAGEMENT_TIMEOUT=5

# Uncomment TLS options if required by your broker
# RABBITMQ_SSL_CAFILE=/run/secrets/rabbitmq_ca.pem
# RABBITMQ_SSL_LOCALCERT=/run/secrets/rabbitmq_client_cert.pem
# RABBITMQ_SSL_LOCALKEY=/run/secrets/rabbitmq_client_key.pem
# RABBITMQ_SSL_VERIFY_PEER=true
# RABBITMQ_SSL_PASSPHRASE=

PROCESSING_OCR_PROVIDER=live
PROCESSING_CLASSIFICATION_PROVIDER=live
OPENAI_API_KEY=REPLACE_WITH_OPENAI_API_KEY
OPENAI_BASE_URL=https://api.openai.com/v1
PROCESSING_OPENAI_MODEL=gpt-4o-mini
PROCESSING_OPENAI_TIMEOUT=15
PROCESSING_QUEUE_CONNECTION=rabbitmq
PROCESSING_RETRY_ATTEMPTS=3
PROCESSING_RETRY_BACKOFF=5,15,45
PROCESSING_SCAN_WAIT_DELAY_SECONDS=5

VITE_APP_NAME="${APP_NAME}"
```

---

## Step 1: Runtime Cutover Configuration

1. Add provider mode configuration (`simulated` vs `live`) in application config.
2. Add environment contracts for live integrations:
   - OCR provider credentials/settings
   - Classification provider credentials/settings
   - RabbitMQ management API connectivity
3. Add environment guards:
   - production/staging reject `simulated` mode
   - local/testing allow both modes
4. Add boot-time validation with actionable error messages for missing cutover settings.

---

## Step 2: Live Provider Implementations

1. Implement Textract-backed OCR provider behind existing adapter interface.
2. Implement OpenAI-backed classification provider behind existing adapter interface.
3. Preserve interface-level compatibility so consumer code paths do not branch per provider.
4. Ensure extracted payload normalization stays stable across simulated and live providers.

---

## Step 3: Reliability and Failure Controls

1. Add/verify circuit-breaker behavior for external provider degradation.
2. Requeue with delay for transient provider outages.
3. Keep terminal failure transitions and DLQ behavior aligned with Phase 3 status model.
4. Ensure idempotency keys still prevent duplicate side effects during retries/redeliveries.

---

## Step 4: Docker and Runtime Profile Alignment

1. Keep LocalStack simulation workflow for local development.
2. Add documented real-infra runtime profile/override for staging/production-like runs.
3. Ensure worker and app services consume consistent provider mode and endpoint variables.
4. Add operational make targets for cutover verification workflows.

---

## Step 5: Cutover Validation and Gate

1. Run live provider integration tests.
2. Run queue-health and DLQ observability checks against live infrastructure.
3. Run tenant-isolation and idempotency regression checks in live mode.
4. Mark Phase 3.5 complete only when live pipeline and operational checks are green.

---

## Test Matrix

| Scenario | Expected Result |
| --- | --- |
| Production-like boot with `simulated` mode | Application startup is rejected with clear configuration error |
| Live OCR provider success | Extraction results are persisted with expected normalized structure |
| Live classification provider success | Classification type/confidence are persisted and status advances |
| External provider outage | Circuit-breaker/retry path executes; no duplicate side effects |
| Retry exhaustion in live mode | Document reaches terminal `*_failed` state and DLQ path is visible |
| Queue health in live mode | Admin sees real queue depth and DLQ counts |
| Cross-tenant processing in live mode | Denied/logged with no cross-tenant mutation |
| Production env missing required service keys | Application startup is rejected with actionable configuration error |

---

## Final Verification

```bash
# Backend formatting
docker compose exec app vendor/bin/pint --dirty --format agent

# Live-mode focused checks
docker compose exec app php artisan test --compact tests/Feature/Processing
docker compose exec app php artisan test --compact tests/Feature/Admin/QueueHealthTest.php

# Full backend regression
docker compose exec app php artisan test --compact

# Frontend build
npm run build
```

---

## Phase 4 Continuation Rule

Phase 4 starts only after Phase 3.5 is merged and live infrastructure cutover checks pass (providers, queue health, DLQ, tenant-safety).
