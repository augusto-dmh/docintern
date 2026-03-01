# Agent Development Reference

## Running Commands

All commands run inside Docker containers. Use `make` shortcuts or `docker compose exec`.

Start or refresh the stack with:

```bash
docker compose up -d --build
```

The `app` service bootstraps Composer dependencies on startup if `vendor/autoload.php` is missing.

If the node container is not running, use:

```bash
docker compose run --rm node sh -lc "npm install && npm run build"
```

Run frontend commands in the `node` service as the default `node` user (do not use `-u root`), to avoid Wayfinder file ownership issues.

## Docker Troubleshooting

### `vendor/autoload.php` missing in `app`

```bash
docker compose logs -f app
```

Wait for Composer bootstrap to complete, or run:

```bash
docker compose exec app composer install --no-interaction --prefer-dist
```

### Wayfinder permission denied from Vite startup

If generated files were created as root in a prior run, repair ownership once:

```bash
docker compose exec -u root node sh -lc "chown -R node:node /var/www/html/resources/js/actions /var/www/html/resources/js/routes /var/www/html/bootstrap/cache"
```

Then start dev server normally:

```bash
docker compose exec node npm run dev
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

### PR File Scope (Strict)

- Do not include markdown files (`*.md`) in PRs unless the user explicitly asks for that specific markdown file change.
- Stage only files touched to fulfill the current chat request.
- Never include unrelated dirty files, local notes, or plan artifacts in the PR.
- Before commit, verify scope with `git status --short` and `git diff --cached --stat`.

### Responsibility Split (Enforced)

- The agent is responsible for all git and PR handling:
  - creating and switching branches
  - committing in logical units
  - pushing the branch to `origin`
  - creating/updating the PR on GitHub (`gh pr create`, etc.)
- The user is responsible only for manual review decisions:
  - requesting changes
  - approving the PR
  - merging/closing the PR
- Do not stop at "ready to push" or "open PR manually" unless the user explicitly asks to do those steps themselves.

Creating a PR:

```bash
git push -u origin feat/<branch-name>
gh pr create --title "PR title" --body "..."
```

Follow `PR_GUIDELINES.md` for title and description format. Follow `PHASE1_BRANCHING_STRATEGY.md` for branch naming and commit structure.
