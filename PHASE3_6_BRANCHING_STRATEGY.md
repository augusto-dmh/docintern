# Phase 3.6 Completion — Branch, Commit & PR Strategy

## Conventions (same as prior phases)

- **Branches**: `feat/<area>-<description>` off `main`
- **Commits**: `feat:` / `fix:` / `chore:` / `test:` prefix, lowercase, imperative, concise
- **PRs**: Merge to `main`, title under 70 chars, description follows `PR_GUIDELINES.md`
- **Granularity**: One deployable PR for this phase

---

## Why This Intermediary Phase Exists

Phase 3.5 focused on production-like live cutover contracts. Current project direction requires one development-first runtime profile to reduce environment branching and operational complexity before Phase 4.

Phase 3.6 unifies day-to-day runtime assumptions:

- OpenAI required in development runtime
- S3 required in development runtime
- RabbitMQ + Redis + MySQL remain Docker-hosted
- OpenAI becomes the primary OCR and classification live path

---

## PR Breakdown (single PR)

### PR U1: `feat/phase3-6-unify-dev-runtime`

**Branch:** `feat/phase3-6-unify-dev-runtime`  
**Title:** Unify runtime to development-first OpenAI and S3 flow  
**Scope:** provider unification, runtime contract simplification, env/docker default alignment, developer workflow docs, regression coverage updates

**Commits:**

1. `docs: add phase 3.6 branching strategy and implementation plan`
2. `feat: replace textract ocr path with openai ocr provider`
3. `feat: enforce unified development runtime contracts`
4. `chore: align docker and env defaults to single runtime model`
5. `test: update provider and runtime contract coverage for phase 3.6`

**PR Description:**

```markdown
## Why

The current runtime model mixes development and production-like cutover paths, creating configuration drift and avoidable failures in local workflows.

Phase 3.6 establishes one development-first runtime contract so contributors can run the same assumptions every day:

- OpenAI required for processing providers
- S3 required for document storage
- RabbitMQ queue connection required
- Docker-hosted MySQL/Redis/RabbitMQ remain the local infrastructure baseline

## What changed

- Replaced Textract OCR default path with OpenAI OCR provider integration
- Simplified processing runtime validation to a single development contract model
- Removed provider-mode branching for runtime decisions (`DOCINTERN_PROVIDER_MODE` / `PROCESSING_PROVIDER_MODE`)
- Updated `.env.example`, Docker service env defaults, and queue/filesystem defaults for the unified profile
- Updated developer workflow docs and runtime check commands for the new baseline
- Added and updated tests for runtime contracts, OpenAI OCR integration, and command behavior

## Notes

- `docintern:cutover-check` is retained and repurposed as the development runtime contract check
- Simulated providers remain available for focused testing/mocking, but are no longer runtime defaults
```

---

## Dependency Rule for Phase 4

- Phase 4 starts only after U1 is merged into `main` and the Phase 3.6 runtime contract suite passes.

---

## Workflow

```bash
1. git checkout main && git pull
2. git checkout -b feat/phase3-6-unify-dev-runtime
3. Implement + commit in logical chunks
4. docker compose exec app vendor/bin/pint --dirty --format agent
5. docker compose exec app php artisan test --compact
6. npx vite build
7. git push -u origin feat/phase3-6-unify-dev-runtime
8. gh pr create --title "Unify runtime to development-first OpenAI and S3 flow" --body "<PR body>"
```

---

## Execution Status (March 1, 2026)

- Phase 3.6 planned.
- U1 implementation in progress.
