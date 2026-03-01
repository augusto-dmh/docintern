# Phase 3.5 Completion — Branch, Commit & PR Strategy

## Conventions (same as Phase 1 / Phase 2 / Phase 2.5 / Phase 2.6 / Phase 3)

- **Branches**: `feat/<area>-<description>` off `main`
- **Commits**: `feat:` / `fix:` prefix, lowercase, imperative mood, concise
- **PRs**: Merge to `main`. Title under 70 chars, imperative. Description follows PR_GUIDELINES.md (Why / What changed / Notes)
- **Granularity**: Each PR is a self-contained, deployable unit. Each commit is a logical chunk within the PR.

---

## Why This Intermediary Phase Exists

Phase 3 delivers the RabbitMQ pipeline with simulation-capable provider adapters to keep development and CI deterministic.
Before Phase 4 real-time UX work, the platform needs a dedicated cutover phase that replaces simulation defaults with real infrastructure and live providers.

---

## PR Breakdown (3 PRs, sequential)

Each PR depends on the previous one being merged into `main` first.

---

### PR I1: `feat/infrastructure-provider-cutover`

**Branch:** `feat/infrastructure-provider-cutover`  
**Title:** Add phase 3.5 infrastructure cutover configuration  
**Scope:** runtime mode configuration, selective production service setup runbook for current components, environment contracts, docker profile alignment, secret and endpoint wiring

**Commits:**
1. `feat: add phase 3.5 provider mode and endpoint configuration contracts`
2. `docs: add beginner external services runbook and inline .env.production template`
3. `feat: align docker and worker runtime profiles for real infrastructure mode`
4. `test: add configuration contract and environment guard coverage`

**PR Description:**

```markdown
## Why

The pipeline currently assumes simulation-capable defaults. Production and staging need explicit configuration contracts to run against real infrastructure safely and predictably.

## What changed

- Added provider mode config to distinguish simulated and live runtime behavior
- Added beginner-friendly external services runbook for current components (S3, mail transport, RabbitMQ, DB, Redis, secrets mapping, and preflight checks)
- Added inline `.env.production` template with production-safe placeholders and required keys
- Kept AWS usage selective (S3/live provider integrations/optional SES), with provider-agnostic guidance for DB/Redis/RabbitMQ
- Added explicit env contracts for live OCR/classification/queue management integrations
- Updated Docker runtime/profile guidance for real-infra execution paths
- Added tests to verify configuration guards and boot-time validation

## Notes

- This PR does not change processing behavior by itself; it enables controlled cutover
- Missing required live-provider config now fails fast with actionable errors
- Production onboarding runbook is part of this PR's acceptance criteria
```

---

### PR I2: `feat/live-provider-integration`

**Branch:** `feat/live-provider-integration`  
**Title:** Integrate live OCR and classification providers  
**Scope:** AWS Textract + OpenAI provider implementations, circuit-breaker/retry behavior, fallback and observability

**Commits:**
1. `feat: implement textract provider behind ocr adapter interface`
2. `feat: implement openai classification provider behind adapter interface`
3. `feat: add circuit breaker and degraded service requeue behavior`
4. `test: add live provider integration and failure mode coverage`

**PR Description:**

```markdown
## Why

Phase 3.5 requires replacing simulation defaults with real extraction and classification services so pipeline outputs reflect production behavior.

## What changed

- Added live AWS Textract-backed OCR provider implementation
- Added live OpenAI-backed classification provider implementation
- Wired provider selection through the existing adapter contracts
- Added circuit-breaker and delayed requeue behavior for degraded external services
- Added integration tests for provider success paths and controlled failure handling

## Notes

- Simulation providers remain available for local/testing when configured
- Production/staging default to live providers after this PR
```

---

### PR I3: `feat/phase3-5-cutover-validation`

**Branch:** `feat/phase3-5-cutover-validation`  
**Title:** Finalize phase 3.5 cutover validation and operations checks  
**Scope:** DLQ/queue-health live validation, operational checks, regression hardening

**Commits:**
1. `feat: add live infrastructure queue health and dlq operational checks`
2. `feat: tighten processing observability for live provider paths`
3. `test: add phase 3.5 cutover regression and tenant safety coverage`

**PR Description:**

```markdown
## Why

Before real-time UX investments in Phase 4, the live processing stack must prove reliability, observability, and tenant-safe behavior in cutover mode.

## What changed

- Added phase 3.5 operational verification checks for queues and dead letters
- Improved observability around live provider latency/failure paths
- Added targeted regression suite for cutover behavior and tenant isolation
- Finalized phase gate criteria for Phase 4 readiness

## Notes

- This PR is the readiness gate for Phase 4 start
- Any unresolved cutover blockers keep Phase 4 paused until fixed
```

---

## Dependency Rule for Phase 4

- **Phase 4 starts only after I1, I2, and I3 are merged into `main`.**
- **I1 is not complete until selective production services setup + env contract documentation is merged.**

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

- Phase 3.5 is not started yet and is pending Phase 3 completion.
