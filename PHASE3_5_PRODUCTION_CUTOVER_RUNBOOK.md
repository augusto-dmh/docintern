# Phase 3.5 Production Cutover Runbook

This runbook covers the external services and environment contract required to run infrastructure cutover checks in live mode.

## Service checklist

### 1) Object storage (S3-compatible)

- Create a dedicated production bucket.
- Keep bucket public access blocked.
- Enable encryption at rest.
- Configure:
  - `FILESYSTEM_DISK=s3`
  - `AWS_DEFAULT_REGION=<region>`
  - `AWS_BUCKET=<bucket>`
  - `AWS_ENDPOINT_URL=` for native AWS S3 (or custom endpoint for non-AWS S3-compatible providers)
  - `AWS_USE_PATH_STYLE_ENDPOINT=false` unless your provider requires path-style addressing

### 2) RabbitMQ broker and management API

- Provision a production RabbitMQ-compatible broker.
- Create dedicated user and vhost for the app.
- Restrict broker and management access to app runtime networks.
- Configure:
  - `QUEUE_CONNECTION=rabbitmq`
  - `PROCESSING_QUEUE_CONNECTION=rabbitmq`
  - `RABBITMQ_HOST`, `RABBITMQ_PORT`, `RABBITMQ_USER`, `RABBITMQ_PASSWORD`, `RABBITMQ_VHOST`
  - `RABBITMQ_MANAGEMENT_SCHEME`, `RABBITMQ_MANAGEMENT_HOST`, `RABBITMQ_MANAGEMENT_PORT`
  - `RABBITMQ_MANAGEMENT_USER`, `RABBITMQ_MANAGEMENT_PASSWORD`, `RABBITMQ_MANAGEMENT_VHOST`
  - `RABBITMQ_MANAGEMENT_TIMEOUT=5`

### 3) Database (MySQL-compatible)

- Provision a production MySQL database.
- Create a dedicated database and user for the app.
- Restrict access to app runtime networks.
- Configure `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

### 4) Redis

- Provision Redis with authentication enabled.
- Restrict access to app runtime networks.
- Configure:
  - `CACHE_STORE=redis`
  - `SESSION_DRIVER=redis`
  - `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`

### 5) Mail transport

- Use `ses` or `smtp`.
- Configure:
  - `MAIL_MAILER`
  - `MAIL_FROM_ADDRESS`
  - `MAIL_FROM_NAME`
- For SMTP, also configure host/port/username/password/scheme.

### 6) Secrets

- Store credentials in a secrets manager (do not commit to git).
- Keep separate secret sets for staging and production.
- Rotate credentials after first successful cutover.

## Preflight checklist

- `APP_ENV=production`
- `APP_DEBUG=false`
- `DOCINTERN_PROVIDER_MODE=live`
- `PROCESSING_OCR_PROVIDER=live`
- `PROCESSING_CLASSIFICATION_PROVIDER=live`
- `PROCESSING_QUEUE_CONNECTION=rabbitmq`
- `FILESYSTEM_DISK=s3`
- `php artisan docintern:cutover-check` succeeds
- `php artisan docintern:queue-health-check` succeeds

## `.env.production` template

```dotenv
APP_NAME=Docintern
APP_ENV=production
APP_KEY=base64:REPLACE_WITH_PRODUCTION_APP_KEY
APP_DEBUG=false
APP_URL=https://app.your-domain.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

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
AWS_ENDPOINT_URL=
AWS_USE_PATH_STYLE_ENDPOINT=false
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

DOCINTERN_PROVIDER_MODE=live
PROCESSING_OCR_PROVIDER=live
PROCESSING_CLASSIFICATION_PROVIDER=live
PROCESSING_QUEUE_CONNECTION=rabbitmq
PROCESSING_RETRY_ATTEMPTS=3
PROCESSING_RETRY_BACKOFF=5,15,45
PROCESSING_SCAN_WAIT_DELAY_SECONDS=5

VITE_APP_NAME="${APP_NAME}"
```
