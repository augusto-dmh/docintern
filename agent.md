# Agent Development Reference

## Running Commands

All commands run inside Docker containers. Use `make` shortcuts or `docker compose exec`.

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
