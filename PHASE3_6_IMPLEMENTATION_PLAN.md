# Phase 3.6 Completion — Implementation Plan

## Context

Phase 3 and 3.5 introduced queue-driven processing and live-provider cutover contracts. The current direction is to operate with a single development-first runtime profile, without day-to-day branching between development and production-like infrastructure modes.

Phase 3.6 standardizes the runtime baseline around OpenAI and real S3 while keeping Docker-hosted local infrastructure for stateful services.

---

## Target Outcome After Phase 3.6

- One runtime profile for daily development:
  - `PROCESSING_OCR_PROVIDER=openai`
  - `PROCESSING_CLASSIFICATION_PROVIDER=openai`
  - `FILESYSTEM_DISK=s3`
  - `QUEUE_CONNECTION=rabbitmq`
  - `PROCESSING_QUEUE_CONNECTION=rabbitmq`
- OpenAI API key is mandatory for non-testing runtime boot.
- S3 credentials and bucket are mandatory for non-testing runtime boot.
- Simulated providers remain available for targeted tests/mocks, not as default runtime behavior.

---

## Decisions

1. Provider mode branching is removed from runtime decisions (`DOCINTERN_PROVIDER_MODE` and `PROCESSING_PROVIDER_MODE` are not used to determine behavior).
2. OpenAI is primary for both OCR and classification in development runtime.
3. Textract OCR adapter is removed from runtime wiring.
4. Runtime validation is bypassed in `testing` environment only.
5. Existing `docintern:cutover-check` command is retained and repurposed to validate unified development runtime contracts.

---

## Step 1: Runtime Contract Simplification

1. Remove provider-mode derivation logic from `config/processing.php`.
2. Define explicit supported provider values:
   - OCR: `openai|simulated`
   - Classification: `openai|simulated`
3. Define unified runtime contracts:
   - exact: OCR provider, classification provider, filesystem disk, processing queue connection
   - non-empty: AWS credentials, AWS region, AWS bucket, OpenAI API key
4. Set queue/filesystem defaults to unified runtime assumptions.

---

## Step 2: OpenAI OCR Integration

1. Add `OpenAiOcrProvider` implementing the existing `OcrProvider` contract.
2. Call OpenAI API with strict JSON schema response contract.
3. Normalize OCR result to existing extraction shape:
   - `provider`
   - `extracted_text`
   - `payload.lines`
   - `metadata`
   - `classification_hint`
4. Keep degraded service handling via existing provider circuit breaker.

---

## Step 3: Runtime Validation and Command Behavior

1. Update runtime validator messaging to development-runtime language.
2. Validate contracts unconditionally in non-testing runtime.
3. Keep skip behavior for the validation command itself so it can return formatted command output.
4. Update command output from cutover semantics to environment contract semantics.

---

## Step 4: Docker and Developer Workflow Alignment

1. Update `.env.example` defaults to the unified profile.
2. Update `docker-compose.yaml` app/worker env defaults to unified profile.
3. Keep LocalStack optional and non-default.
4. Update `Makefile` target naming for environment checks.
5. Update `GETTING_STARTED.md` to describe one runtime profile and mandatory external keys.

---

## Step 5: Test Coverage and Regression Safety

1. Update processing config contract tests for new defaults.
2. Update runtime validator tests for new required keys and error messages.
3. Update command tests for new command semantics.
4. Replace Textract OCR integration tests with OpenAI OCR integration tests.
5. Keep existing classification provider and pipeline behavior tests green.

---

## Test Matrix

| Scenario | Expected Result |
| --- | --- |
| Missing `OPENAI_API_KEY` in non-testing runtime | Runtime validation fails with actionable message |
| `FILESYSTEM_DISK` not `s3` | Runtime validation fails |
| `PROCESSING_QUEUE_CONNECTION` not `rabbitmq` | Runtime validation fails |
| OpenAI OCR success | Extraction payload is persisted with normalized fields |
| OpenAI OCR rate-limit/transient response | Circuit breaker emits degraded exception path |
| Command `docintern:cutover-check` | Reports environment contract check pass/fail messaging |
| Test environment boot | Runtime validator does not block suite execution |

---

## Final Verification

```bash
# Backend formatting
docker compose exec app vendor/bin/pint --dirty --format agent

# Focused contract + provider checks
docker compose exec app php artisan test --compact tests/Feature/Infrastructure/ProcessingConfigContractTest.php
docker compose exec app php artisan test --compact tests/Feature/Infrastructure/ProcessingRuntimeConfigValidatorTest.php
docker compose exec app php artisan test --compact tests/Feature/Processing/LiveProviderIntegrationTest.php

# Processing regression
docker compose exec app php artisan test --compact tests/Feature/Processing

# Full backend
docker compose exec app php artisan test --compact

# Frontend build
npx vite build
```

---

## Phase 4 Continuation Rule

Phase 4 begins only after Phase 3.6 merges and the unified runtime contract and processing regression suites are green.
