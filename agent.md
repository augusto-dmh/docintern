# Agent Development Reference

## Running Commands

All commands run inside Docker containers. Use `make` shortcuts or `docker compose exec`.

If the node container is not running (e.g. `npm` deps not installed), use:

```bash
docker compose exec node sh -c "npm install && npm run build"
```

## Backend

### Tests

```bash
# Full test suite
make test

# Compact output
docker compose exec app php artisan test --compact

# Filter by test name
docker compose exec app php artisan test --compact --filter=TestName

# Specific file
docker compose exec app php artisan test --compact tests/Feature/Client/ClientCrudTest.php
```

Tests use SQLite `:memory:` (configured in `phpunit.xml`).

### Code Formatting

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
```

### Artisan Commands

```bash
make artisan <command>
# Example: make artisan make:controller ClientController --resource --no-interaction
```

## Frontend

### Build & Dev

```bash
# Production build
make npm run build

# Dev server (HMR)
make npm run dev
```

### Linting & Formatting

```bash
# ESLint (fix)
make npm run lint

# Prettier (check)
make npm run format:check

# Prettier (fix)
make npm run format
```

### Wayfinder (Route TypeScript Generation)

```bash
make artisan wayfinder:generate
```

Run this after adding or modifying Laravel routes to regenerate TypeScript route/action files.

## PR Workflow

Before pushing, always run all checks in this order:

```bash
# 1. Backend formatting
docker compose exec app vendor/bin/pint --dirty --format agent

# 2. Frontend linting (must pass with zero errors)
npx eslint . --fix

# 3. Frontend formatting
npx prettier --write resources/

# 4. Backend tests
docker compose exec app php artisan test --compact

# 5. Frontend build
npx vite build   # or: make npm run build
```

All five steps are mandatory. Do not push if any step fails.

Creating a PR:

```bash
git push -u origin feat/<branch-name>
gh pr create --title "PR title" --body "..."
```

Follow `PR_GUIDELINES.md` for title and description format. Follow `PHASE1_BRANCHING_STRATEGY.md` for branch naming and commit structure.
