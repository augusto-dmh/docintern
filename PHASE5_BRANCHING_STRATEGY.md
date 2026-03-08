# Phase 5 — Branch, Commit & PR Strategy

## Conventions (same as prior phases)

- **Branches**: `feat/<area>-<description>` off `main`
- **Commits**: `docs:` / `feat:` / `fix:` / `test:` prefix, lowercase, imperative, concise
- **PRs**: Merge to `main`, title under 70 chars, description follows `PR_GUIDELINES.md`
- **Granularity**: Multiple deployable PRs, sequenced by dependency

---

## Why Phase 5 Exists

Phase 4 completed live document status visibility and workspace notifications.
The next product step is a real review workspace where users can inspect documents inline, compare extracted outputs against source files, and prepare for collaborative annotation and assignment flows.

---

## PR Breakdown (Phase 5)

### PR R1: `feat/phase5-review-workspace-foundation`

**Title:** Add document review workspace foundation  
**Scope:** Phase 5 plan markdowns, inline document preview route, PDF viewer foundation, extracted data panel, document detail workspace restructuring, focused regression coverage

**Commit plan:**

1. `docs: add phase 5 branching strategy and implementation plan`
2. `feat: add document preview route and review workspace foundation`
3. `feat: surface extracted data and classification in document review panel`
4. `test: add document review workspace coverage`

---

### PR R2: `feat/phase5-review-annotations`

**Title:** Add document annotations and review audit entries  
**Scope:** Annotation persistence, policy/controller flow, `AnnotationLayer.vue`, optimistic annotation mutations, audit trail coverage

---

### PR R3: `feat/phase5-review-workflow`

**Title:** Add reviewer assignment and rejection workflow  
**Scope:** reviewer assignment, rejection status, Phase 5 document permissions, assignment-aware UI, realtime review workflow broadcasts

---

### PR R4: `feat/phase5-review-collaboration-tools`

**Title:** Add review collaboration and bulk review tools  
**Scope:** discussion thread, bulk review actions, document versions, side-by-side comparison and diff payloads

---

## Dependency Rules

- R2 depends on R1 merged.
- R3 depends on R2 merged.
- R4 depends on R3 merged.
- Phase 6 begins only after R1-R4 are merged and Phase 5 regression checks are green.

---

## Workflow per PR

```bash
1. git checkout main && git pull
2. git checkout -b feat/<branch-name>
3. Implement in logical commits
4. docker compose exec app vendor/bin/pint --dirty --format agent
5. docker compose exec app php artisan test --compact <target>
6. npx eslint . --fix
7. npx prettier --write resources/
8. docker compose exec app php artisan test --compact
9. npx vite build
10. git push -u origin feat/<branch-name>
11. gh pr create --title "<title>" --body "<body>"
```

---

## PR Scope Guardrail

- Keep Phase 5 markdown files in the R1 PR only because they are explicitly requested for this phase kickoff.
- Do not include unrelated markdown files or local planning notes.
- Stage only the code and generated route artifacts required for the active PR scope.

---

## Execution Status (March 8, 2026)

- Phase 4 is complete through PR #32.
- Phase 5 starts with R1 and introduces the review workspace foundation.
