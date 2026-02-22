# Docintern — Docker Infrastructure Specification

## Per-Phase Dockerfile & Docker Compose Evolution Guide

---

## 1. Overview

This document specifies the exact Docker infrastructure needed at each phase of the Docintern project. Each phase builds incrementally on the previous one — new services are added, existing configurations are extended, and no containers are removed once introduced.

**Principles:**

- Every phase includes the **complete** `docker-compose.yaml` and any new/modified Dockerfiles for that phase — not just diffs.
- Services introduced in earlier phases carry forward unchanged unless explicitly modified.
- All images use **pinned versions** (no `latest` tags) for reproducibility.
- Local dev uses bind mounts for hot-reload. Production uses multi-stage builds with minimal images.
- Healthchecks are defined for every service from the start.

---

## 2. Directory Structure (Final State)

```
docintern/
├── docker/
│   ├── php/
│   │   ├── Dockerfile              # Laravel PHP-FPM (dev)
│   │   ├── Dockerfile.prod         # Laravel PHP-FPM (production, multi-stage)
│   │   ├── php.ini                 # Custom PHP config overrides
│   │   └── supervisord.conf        # Supervisor config for workers (Phase 3+)
│   ├── nginx/
│   │   ├── Dockerfile              # Nginx with custom config
│   │   └── default.conf            # Nginx site config for Laravel
│   ├── node/
│   │   └── Dockerfile              # Node container for Vite dev server
│   ├── workers/
│   │   ├── Dockerfile              # Worker container (extends php, runs consumers)
│   │   └── supervisord.conf        # Supervisor managing multiple queue consumers
│   ├── reverb/
│   │   └── Dockerfile              # Laravel Reverb WebSocket server
│   ├── scheduler/
│   │   └── Dockerfile              # Laravel task scheduler (cron)
│   └── lambda/
│       ├── textract-trigger/
│       │   ├── Dockerfile           # Lambda container image for Textract trigger
│       │   └── handler.py
│       ├── textract-callback/
│       │   ├── Dockerfile
│       │   └── handler.py
│       └── thumbnail-gen/
│           ├── Dockerfile
│           └── handler.py
├── docker-compose.yaml              # Main dev compose file
├── docker-compose.prod.yaml         # Production overrides
├── docker-compose.testing.yaml      # Testing overrides (ephemeral DBs, etc.)
├── .env.docker                      # Docker-specific env vars
└── Makefile                         # Convenience commands (make up, make down, etc.)
```

---

## 3. Shared Configuration: Docker Network & Volumes

All phases use these common definitions (shown once, included in every compose file):

```yaml
networks:
  docintern:
    driver: bridge

volumes:
  mysql_data:
    driver: local
  redis_data:
    driver: local
  rabbitmq_data:
    driver: local
  meilisearch_data: # Added in Phase 7
    driver: local
```

---

## Phase 1 — Foundation & Auth

### Services Introduced

| Service    | Image / Build            | Purpose                            | Ports           |
| ---------- | ------------------------ | ---------------------------------- | --------------- |
| `app`      | Custom PHP 8.3-FPM       | Laravel application server         | 9000 (internal) |
| `nginx`    | Custom Nginx 1.26        | Web server / reverse proxy         | 80 → 80         |
| `node`     | Node 20-alpine           | Vite HMR + Wayfinder generation    | 5173 → 5173     |
| `mysql`    | MySQL 8.0                | Primary database                   | 3306 → 3306     |
| `redis`    | Redis 7-alpine           | Cache, sessions                    | 6379 → 6379     |
| `rabbitmq` | RabbitMQ 3.13-management | Message broker (provisioned early) | 5672, 15672     |
| `mailpit`  | Mailpit latest           | Local email testing                | 8025, 1025      |

### docker/php/Dockerfile

```dockerfile
FROM php:8.3-fpm-bookworm

ARG USER_ID=1000
ARG GROUP_ID=1000

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        sockets \
        opcache \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Create application user
RUN groupadd -g ${GROUP_ID} docintern \
    && useradd -u ${USER_ID} -g docintern -m docintern

WORKDIR /var/www/html

USER docintern

EXPOSE 9000
CMD ["php-fpm"]
```

### docker/php/php.ini

```ini
upload_max_filesize = 100M
post_max_size = 120M
memory_limit = 256M
max_execution_time = 120
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 0
```

### docker/nginx/Dockerfile

```dockerfile
FROM nginx:1.26-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
```

### docker/nginx/default.conf

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 120M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### docker/node/Dockerfile

```dockerfile
FROM node:20-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
    git \
    php83 \
    php83-bcmath \
    php83-ctype \
    php83-curl \
    php83-dom \
    php83-fileinfo \
    php83-mbstring \
    php83-openssl \
    php83-pcntl \
    php83-pdo \
    php83-pdo_mysql \
    php83-pecl-redis \
    php83-session \
    php83-simplexml \
    php83-sockets \
    php83-tokenizer \
    php83-xml \
    php83-zip \
    && ln -sf /usr/bin/php83 /usr/bin/php

EXPOSE 5173
CMD ["sh", "-lc", "mkdir -p node_modules && chown -R node:node node_modules public && su node -s /bin/sh -c 'if [ ! -x node_modules/.bin/vite ]; then npm install; fi && npm run dev -- --host 0.0.0.0'"]
```

### docker-compose.yaml — Phase 1

```yaml
version: "3.8"

services:
  # ──────────────────────────────────────────────
  # Laravel Application (PHP-FPM)
  # ──────────────────────────────────────────────
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      args:
        USER_ID: ${DOCKER_USER_ID:-1000}
        GROUP_ID: ${DOCKER_GROUP_ID:-1000}
    container_name: docintern-app
    restart: unless-stopped
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor # Prevent vendor override from host
    environment:
      - APP_ENV=${APP_ENV:-local}
      - DB_HOST=mysql
      - DB_PORT=3306
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - RABBITMQ_HOST=rabbitmq
      - RABBITMQ_PORT=5672
      - MAIL_HOST=mailpit
      - MAIL_PORT=1025
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
      rabbitmq:
        condition: service_healthy
    networks:
      - docintern
    healthcheck:
      test: ["CMD-SHELL", "php-fpm-healthcheck || exit 1"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 30s

  # ──────────────────────────────────────────────
  # Nginx Web Server
  # ──────────────────────────────────────────────
  nginx:
    build:
      context: .
      dockerfile: docker/nginx/Dockerfile
    container_name: docintern-nginx
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html:ro
    depends_on:
      - app
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 5s
      retries: 3

  # ──────────────────────────────────────────────
  # Vite Dev Server (HMR)
  # ──────────────────────────────────────────────
  node:
    build:
      context: .
      dockerfile: docker/node/Dockerfile
    container_name: docintern-node
    restart: unless-stopped
    ports:
      - "5173:5173"
    volumes:
      - .:/var/www/html
      - /var/www/html/node_modules # Prevent node_modules override from host
    networks:
      - docintern

  # ──────────────────────────────────────────────
  # MySQL 8.0
  # ──────────────────────────────────────────────
  mysql:
    image: mysql:8.0
    container_name: docintern-mysql
    restart: unless-stopped
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD:-rootsecret}
      MYSQL_DATABASE: ${DB_DATABASE:-docintern}
      MYSQL_USER: ${DB_USERNAME:-docintern}
      MYSQL_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - docintern
    healthcheck:
      test:
        [
          "CMD",
          "mysqladmin",
          "ping",
          "-h",
          "localhost",
          "-u",
          "root",
          "-p${DB_ROOT_PASSWORD:-rootsecret}",
        ]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s
    command: >
      --default-authentication-plugin=mysql_native_password
      --character-set-server=utf8mb4
      --collation-server=utf8mb4_unicode_ci
      --innodb-buffer-pool-size=256M

  # ──────────────────────────────────────────────
  # Redis 7
  # ──────────────────────────────────────────────
  redis:
    image: redis:7-alpine
    container_name: docintern-redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    command: redis-server --appendonly yes --maxmemory 128mb --maxmemory-policy allkeys-lru

  # ──────────────────────────────────────────────
  # RabbitMQ 3.13 with Management UI
  # ──────────────────────────────────────────────
  rabbitmq:
    image: rabbitmq:3.13-management-alpine
    container_name: docintern-rabbitmq
    restart: unless-stopped
    ports:
      - "5672:5672"
      - "15672:15672"
    environment:
      RABBITMQ_DEFAULT_USER: ${RABBITMQ_USER:-docintern}
      RABBITMQ_DEFAULT_PASS: ${RABBITMQ_PASSWORD:-secret}
      RABBITMQ_DEFAULT_VHOST: ${RABBITMQ_VHOST:-/docintern}
    volumes:
      - rabbitmq_data:/var/lib/rabbitmq
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "rabbitmq-diagnostics", "-q", "ping"]
      interval: 15s
      timeout: 10s
      retries: 5
      start_period: 30s

  # ──────────────────────────────────────────────
  # Mailpit (Local Email Testing)
  # ──────────────────────────────────────────────
  mailpit:
    image: axllent/mailpit:v1.19
    container_name: docintern-mailpit
    restart: unless-stopped
    ports:
      - "8025:8025" # Web UI
      - "1025:1025" # SMTP
    networks:
      - docintern

networks:
  docintern:
    driver: bridge

volumes:
  mysql_data:
    driver: local
  redis_data:
    driver: local
  rabbitmq_data:
    driver: local
```

### Makefile — Phase 1

```makefile
.PHONY: up down build shell composer artisan migrate seed fresh test

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

shell:
	docker compose exec app bash

composer:
	docker compose exec app composer $(filter-out $@,$(MAKECMDGOALS))

artisan:
	docker compose exec app php artisan $(filter-out $@,$(MAKECMDGOALS))

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

fresh:
	docker compose exec app php artisan migrate:fresh --seed

test:
	docker compose exec app php artisan test

npm:
	docker compose exec node npm $(filter-out $@,$(MAKECMDGOALS))

logs:
	docker compose logs -f $(filter-out $@,$(MAKECMDGOALS))

%:
	@:
```

---

## Phase 2 — Document Upload & S3 Integration

### Changes from Phase 1

| Change                     | Detail                                                                 |
| -------------------------- | ---------------------------------------------------------------------- |
| `app` Dockerfile           | No changes. AWS SDK is installed via Composer, not system packages.    |
| `localstack` service added | LocalStack to emulate S3 + CloudFront locally without AWS credentials. |
| `docker-compose.yaml`      | Add `localstack` service. Add S3 env vars to `app`.                    |

### New Service: LocalStack (S3 Emulation)

> **Why LocalStack?** So developers don't need real AWS credentials to work on the upload flow. The Laravel filesystem driver is configured to point at LocalStack in `local` env and real S3 in `production`.

### docker-compose.yaml — Phase 2 Additions

Add the following service and update the `app` environment block:

```yaml
# ──────────────────────────────────────────────
# LocalStack (AWS S3/CloudFront emulation)
# ──────────────────────────────────────────────
localstack:
  image: localstack/localstack:3.7
  container_name: docintern-localstack
  restart: unless-stopped
  ports:
    - "4566:4566" # Gateway
    - "4510-4559:4510-4559" # Service ports
  environment:
    SERVICES: s3,cloudfront
    DEBUG: 0
    DEFAULT_REGION: ${AWS_DEFAULT_REGION:-us-east-1}
    AWS_ACCESS_KEY_ID: test
    AWS_SECRET_ACCESS_KEY: test
  volumes:
    - ./docker/localstack/init-s3.sh:/etc/localstack/init/ready.d/init-s3.sh
  networks:
    - docintern
  healthcheck:
    test: ["CMD", "curl", "-f", "http://localhost:4566/_localstack/health"]
    interval: 15s
    timeout: 5s
    retries: 5
    start_period: 20s
```

### docker/localstack/init-s3.sh

```bash
#!/bin/bash
echo "Initializing LocalStack S3..."

awslocal s3 mb s3://docintern-dev
awslocal s3api put-bucket-versioning \
    --bucket docintern-dev \
    --versioning-configuration Status=Enabled

echo "S3 bucket 'docintern-dev' created with versioning enabled."
```

### Updated `app` environment (additions)

```yaml
app:
  environment:
    # ... all Phase 1 vars ...
    - AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID:-test}
    - AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY:-test}
    - AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION:-us-east-1}
    - AWS_S3_BUCKET=${AWS_S3_BUCKET:-docintern-dev}
    - AWS_ENDPOINT_URL=http://localstack:4566 # Only for local dev
    - AWS_USE_PATH_STYLE_ENDPOINT=true # Required for LocalStack
  depends_on:
    # ... all Phase 1 deps ...
    localstack:
      condition: service_healthy
```

### Full docker-compose.yaml — Phase 2

```yaml
version: "3.8"

services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      args:
        USER_ID: ${DOCKER_USER_ID:-1000}
        GROUP_ID: ${DOCKER_GROUP_ID:-1000}
    container_name: docintern-app
    restart: unless-stopped
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
    environment:
      - APP_ENV=${APP_ENV:-local}
      - DB_HOST=mysql
      - DB_PORT=3306
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - RABBITMQ_HOST=rabbitmq
      - RABBITMQ_PORT=5672
      - MAIL_HOST=mailpit
      - MAIL_PORT=1025
      - AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID:-test}
      - AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY:-test}
      - AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION:-us-east-1}
      - AWS_S3_BUCKET=${AWS_S3_BUCKET:-docintern-dev}
      - AWS_ENDPOINT_URL=http://localstack:4566
      - AWS_USE_PATH_STYLE_ENDPOINT=true
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
      rabbitmq:
        condition: service_healthy
      localstack:
        condition: service_healthy
    networks:
      - docintern
    healthcheck:
      test: ["CMD-SHELL", "php-fpm-healthcheck || exit 1"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 30s

  nginx:
    build:
      context: .
      dockerfile: docker/nginx/Dockerfile
    container_name: docintern-nginx
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html:ro
    depends_on:
      - app
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 5s
      retries: 3

  node:
    build:
      context: .
      dockerfile: docker/node/Dockerfile
    container_name: docintern-node
    restart: unless-stopped
    ports:
      - "5173:5173"
    volumes:
      - .:/var/www/html
      - /var/www/html/node_modules
    networks:
      - docintern

  mysql:
    image: mysql:8.0
    container_name: docintern-mysql
    restart: unless-stopped
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD:-rootsecret}
      MYSQL_DATABASE: ${DB_DATABASE:-docintern}
      MYSQL_USER: ${DB_USERNAME:-docintern}
      MYSQL_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - docintern
    healthcheck:
      test:
        [
          "CMD",
          "mysqladmin",
          "ping",
          "-h",
          "localhost",
          "-u",
          "root",
          "-p${DB_ROOT_PASSWORD:-rootsecret}",
        ]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s
    command: >
      --default-authentication-plugin=mysql_native_password
      --character-set-server=utf8mb4
      --collation-server=utf8mb4_unicode_ci
      --innodb-buffer-pool-size=256M

  redis:
    image: redis:7-alpine
    container_name: docintern-redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    command: redis-server --appendonly yes --maxmemory 128mb --maxmemory-policy allkeys-lru

  rabbitmq:
    image: rabbitmq:3.13-management-alpine
    container_name: docintern-rabbitmq
    restart: unless-stopped
    ports:
      - "5672:5672"
      - "15672:15672"
    environment:
      RABBITMQ_DEFAULT_USER: ${RABBITMQ_USER:-docintern}
      RABBITMQ_DEFAULT_PASS: ${RABBITMQ_PASSWORD:-secret}
      RABBITMQ_DEFAULT_VHOST: ${RABBITMQ_VHOST:-/docintern}
    volumes:
      - rabbitmq_data:/var/lib/rabbitmq
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "rabbitmq-diagnostics", "-q", "ping"]
      interval: 15s
      timeout: 10s
      retries: 5
      start_period: 30s

  localstack:
    image: localstack/localstack:3.7
    container_name: docintern-localstack
    restart: unless-stopped
    ports:
      - "4566:4566"
      - "4510-4559:4510-4559"
    environment:
      SERVICES: s3,cloudfront
      DEBUG: 0
      DEFAULT_REGION: ${AWS_DEFAULT_REGION:-us-east-1}
      AWS_ACCESS_KEY_ID: test
      AWS_SECRET_ACCESS_KEY: test
    volumes:
      - ./docker/localstack/init-s3.sh:/etc/localstack/init/ready.d/init-s3.sh
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:4566/_localstack/health"]
      interval: 15s
      timeout: 5s
      retries: 5
      start_period: 20s

  mailpit:
    image: axllent/mailpit:v1.19
    container_name: docintern-mailpit
    restart: unless-stopped
    ports:
      - "8025:8025"
      - "1025:1025"
    networks:
      - docintern

networks:
  docintern:
    driver: bridge

volumes:
  mysql_data:
    driver: local
  redis_data:
    driver: local
  rabbitmq_data:
    driver: local
```

### Makefile — Phase 2 Additions

```makefile
# Append to existing Makefile

s3-ls:
	docker compose exec localstack awslocal s3 ls s3://docintern-dev --recursive

s3-shell:
	docker compose exec localstack bash
```

---

## Phase 3 — RabbitMQ Pipeline & Async Processing

### Changes from Phase 2

| Change                       | Detail                                                                   |
| ---------------------------- | ------------------------------------------------------------------------ |
| `worker` service added       | Dedicated container running RabbitMQ consumers via Supervisor            |
| `scheduler` service added    | Runs `php artisan schedule:run` every minute (cron) for periodic tasks   |
| `app` Dockerfile updated     | Supervisor installed (shared base with worker)                           |
| Worker Dockerfile created    | Extends PHP base, adds Supervisor config for multiple consumer processes |
| RabbitMQ definitions mounted | Pre-declares exchanges, queues, and bindings on container startup        |

### docker/workers/Dockerfile

```dockerfile
FROM php:8.3-fpm-bookworm

ARG USER_ID=1000
ARG GROUP_ID=1000

# System dependencies (same as app)
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        sockets \
        opcache \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/workers/supervisord.conf /etc/supervisor/conf.d/docintern-workers.conf

RUN groupadd -g ${GROUP_ID} docintern \
    && useradd -u ${USER_ID} -g docintern -m docintern

WORKDIR /var/www/html

# Supervisor runs as root to manage child processes; workers run as docintern user
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/docintern-workers.conf"]
```

### docker/workers/supervisord.conf

```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

# ── Virus Scan Consumer (2 processes) ──
[program:worker-virus-scan]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan docintern:consume virus-scan --sleep=3 --tries=3 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=docintern
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker-virus-scan.log
stopwaitsecs=30

# ── OCR Extraction Consumer (3 processes) ──
[program:worker-ocr-extraction]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan docintern:consume ocr-extraction --sleep=3 --tries=3 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=docintern
numprocs=3
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker-ocr-extraction.log
stopwaitsecs=60

# ── Classification Consumer (2 processes) ──
[program:worker-classification]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan docintern:consume classification --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=docintern
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker-classification.log
stopwaitsecs=30

# ── Audit Log Consumer (1 process) ──
[program:worker-audit-log]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan docintern:consume audit-log --sleep=3 --tries=3 --timeout=30
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=docintern
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker-audit-log.log
stopwaitsecs=15

# ── Dead Letter Queue Consumer (1 process) ──
[program:worker-dlq]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan docintern:consume dead-letters --sleep=10 --tries=1 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=docintern
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker-dlq.log
stopwaitsecs=15
```

### docker/scheduler/Dockerfile

```dockerfile
FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    cron \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        pcntl \
        bcmath \
        gd \
        zip \
        sockets \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

# Run Laravel scheduler every minute
CMD ["sh", "-c", "while true; do php artisan schedule:run --verbose --no-interaction & sleep 60; done"]
```

### docker/rabbitmq/definitions.json

> Mounted into the RabbitMQ container to pre-declare all exchanges, queues, and bindings on startup.

```json
{
  "users": [
    {
      "name": "docintern",
      "password_hash": "15f0cBVPmalTwPG+z6JSroKFIBG88NpeWv9xMAKJBlgl9aOK",
      "hashing_algorithm": "rabbit_password_hashing_sha256",
      "tags": ["administrator"],
      "limits": {}
    }
  ],
  "vhosts": [{ "name": "/docintern" }],
  "permissions": [
    {
      "user": "docintern",
      "vhost": "/docintern",
      "configure": ".*",
      "write": ".*",
      "read": ".*"
    }
  ],
  "exchanges": [
    {
      "name": "docintern.upload",
      "vhost": "/docintern",
      "type": "fanout",
      "durable": true,
      "auto_delete": false
    },
    {
      "name": "docintern.processing",
      "vhost": "/docintern",
      "type": "topic",
      "durable": true,
      "auto_delete": false
    },
    {
      "name": "docintern.notifications",
      "vhost": "/docintern",
      "type": "topic",
      "durable": true,
      "auto_delete": false
    },
    {
      "name": "docintern.dlx",
      "vhost": "/docintern",
      "type": "direct",
      "durable": true,
      "auto_delete": false
    }
  ],
  "queues": [
    {
      "name": "queue.virus-scan",
      "vhost": "/docintern",
      "durable": true,
      "auto_delete": false,
      "arguments": {
        "x-dead-letter-exchange": "docintern.dlx",
        "x-dead-letter-routing-key": "dlq.virus-scan"
      }
    },
    {
      "name": "queue.audit-log",
      "vhost": "/docintern",
      "durable": true,
      "auto_delete": false,
      "arguments": {
        "x-dead-letter-exchange": "docintern.dlx",
        "x-dead-letter-routing-key": "dlq.audit-log"
      }
    },
    {
      "name": "queue.ocr-extraction",
      "vhost": "/docintern",
      "durable": true,
      "auto_delete": false,
      "arguments": {
        "x-dead-letter-exchange": "docintern.dlx",
        "x-dead-letter-routing-key": "dlq.ocr-extraction"
      }
    },
    {
      "name": "queue.classify.contract",
      "vhost": "/docintern",
      "durable": true,
      "auto_delete": false,
      "arguments": {
        "x-dead-letter-exchange": "docintern.dlx",
        "x-dead-letter-routing-key": "dlq.classify"
      }
    },
    {
      "name": "queue.classify.tax",
      "vhost": "/docintern",
      "durable": true,
      "auto_delete": false,
      "arguments": {
        "x-dead-letter-exchange": "docintern.dlx",
        "x-dead-letter-routing-key": "dlq.classify"
      }
    },
    {
      "name": "queue.classify.invoice",
      "vhost": "/docintern",
      "durable": true,
      "auto_delete": false,
      "arguments": {
        "x-dead-letter-exchange": "docintern.dlx",
        "x-dead-letter-routing-key": "dlq.classify"
      }
    },
    {
      "name": "queue.classify.general",
      "vhost": "/docintern",
      "durable": true,
      "auto_delete": false,
      "arguments": {
        "x-dead-letter-exchange": "docintern.dlx",
        "x-dead-letter-routing-key": "dlq.classify"
      }
    },
    {
      "name": "queue.notify.email",
      "vhost": "/docintern",
      "durable": true,
      "auto_delete": false,
      "arguments": {
        "x-dead-letter-exchange": "docintern.dlx",
        "x-dead-letter-routing-key": "dlq.notify"
      }
    },
    {
      "name": "queue.notify.inapp",
      "vhost": "/docintern",
      "durable": true,
      "auto_delete": false,
      "arguments": {
        "x-dead-letter-exchange": "docintern.dlx",
        "x-dead-letter-routing-key": "dlq.notify"
      }
    },
    {
      "name": "queue.dead-letters",
      "vhost": "/docintern",
      "durable": true,
      "auto_delete": false,
      "arguments": {}
    }
  ],
  "bindings": [
    {
      "source": "docintern.upload",
      "vhost": "/docintern",
      "destination": "queue.virus-scan",
      "destination_type": "queue",
      "routing_key": ""
    },
    {
      "source": "docintern.upload",
      "vhost": "/docintern",
      "destination": "queue.audit-log",
      "destination_type": "queue",
      "routing_key": ""
    },
    {
      "source": "docintern.upload",
      "vhost": "/docintern",
      "destination": "queue.ocr-extraction",
      "destination_type": "queue",
      "routing_key": ""
    },
    {
      "source": "docintern.processing",
      "vhost": "/docintern",
      "destination": "queue.classify.contract",
      "destination_type": "queue",
      "routing_key": "doc.contract.#"
    },
    {
      "source": "docintern.processing",
      "vhost": "/docintern",
      "destination": "queue.classify.tax",
      "destination_type": "queue",
      "routing_key": "doc.tax.#"
    },
    {
      "source": "docintern.processing",
      "vhost": "/docintern",
      "destination": "queue.classify.invoice",
      "destination_type": "queue",
      "routing_key": "doc.invoice.#"
    },
    {
      "source": "docintern.processing",
      "vhost": "/docintern",
      "destination": "queue.classify.general",
      "destination_type": "queue",
      "routing_key": "doc.general.#"
    },
    {
      "source": "docintern.notifications",
      "vhost": "/docintern",
      "destination": "queue.notify.email",
      "destination_type": "queue",
      "routing_key": "notify.email.#"
    },
    {
      "source": "docintern.notifications",
      "vhost": "/docintern",
      "destination": "queue.notify.inapp",
      "destination_type": "queue",
      "routing_key": "notify.inapp.#"
    },
    {
      "source": "docintern.dlx",
      "vhost": "/docintern",
      "destination": "queue.dead-letters",
      "destination_type": "queue",
      "routing_key": "#"
    }
  ]
}
```

### Updated RabbitMQ service in docker-compose.yaml

```yaml
rabbitmq:
  image: rabbitmq:3.13-management-alpine
  container_name: docintern-rabbitmq
  restart: unless-stopped
  ports:
    - "5672:5672"
    - "15672:15672"
  environment:
    RABBITMQ_DEFAULT_USER: ${RABBITMQ_USER:-docintern}
    RABBITMQ_DEFAULT_PASS: ${RABBITMQ_PASSWORD:-secret}
    RABBITMQ_DEFAULT_VHOST: ${RABBITMQ_VHOST:-/docintern}
    RABBITMQ_SERVER_ADDITIONAL_ERL_ARGS: -rabbitmq_management load_definitions "/etc/rabbitmq/definitions.json"
  volumes:
    - rabbitmq_data:/var/lib/rabbitmq
    - ./docker/rabbitmq/definitions.json:/etc/rabbitmq/definitions.json:ro
  networks:
    - docintern
  healthcheck:
    test: ["CMD", "rabbitmq-diagnostics", "-q", "ping"]
    interval: 15s
    timeout: 10s
    retries: 5
    start_period: 30s
```

### Full docker-compose.yaml — Phase 3

```yaml
version: "3.8"

services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      args:
        USER_ID: ${DOCKER_USER_ID:-1000}
        GROUP_ID: ${DOCKER_GROUP_ID:-1000}
    container_name: docintern-app
    restart: unless-stopped
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
    environment:
      - APP_ENV=${APP_ENV:-local}
      - DB_HOST=mysql
      - DB_PORT=3306
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - RABBITMQ_HOST=rabbitmq
      - RABBITMQ_PORT=5672
      - MAIL_HOST=mailpit
      - MAIL_PORT=1025
      - AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID:-test}
      - AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY:-test}
      - AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION:-us-east-1}
      - AWS_S3_BUCKET=${AWS_S3_BUCKET:-docintern-dev}
      - AWS_ENDPOINT_URL=http://localstack:4566
      - AWS_USE_PATH_STYLE_ENDPOINT=true
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
      rabbitmq:
        condition: service_healthy
      localstack:
        condition: service_healthy
    networks:
      - docintern
    healthcheck:
      test: ["CMD-SHELL", "php-fpm-healthcheck || exit 1"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 30s

  nginx:
    build:
      context: .
      dockerfile: docker/nginx/Dockerfile
    container_name: docintern-nginx
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html:ro
    depends_on:
      - app
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 5s
      retries: 3

  node:
    build:
      context: .
      dockerfile: docker/node/Dockerfile
    container_name: docintern-node
    restart: unless-stopped
    ports:
      - "5173:5173"
    volumes:
      - .:/var/www/html
      - /var/www/html/node_modules
    networks:
      - docintern

  # ──────────────────────────────────────────────
  # Queue Workers (Supervisor-managed consumers)
  # ──────────────────────────────────────────────
  worker:
    build:
      context: .
      dockerfile: docker/workers/Dockerfile
      args:
        USER_ID: ${DOCKER_USER_ID:-1000}
        GROUP_ID: ${DOCKER_GROUP_ID:-1000}
    container_name: docintern-worker
    restart: unless-stopped
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
    environment:
      - APP_ENV=${APP_ENV:-local}
      - DB_HOST=mysql
      - DB_PORT=3306
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - RABBITMQ_HOST=rabbitmq
      - RABBITMQ_PORT=5672
      - AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID:-test}
      - AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY:-test}
      - AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION:-us-east-1}
      - AWS_S3_BUCKET=${AWS_S3_BUCKET:-docintern-dev}
      - AWS_ENDPOINT_URL=http://localstack:4566
      - AWS_USE_PATH_STYLE_ENDPOINT=true
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
      rabbitmq:
        condition: service_healthy
      localstack:
        condition: service_healthy
    networks:
      - docintern

  # ──────────────────────────────────────────────
  # Laravel Task Scheduler
  # ──────────────────────────────────────────────
  scheduler:
    build:
      context: .
      dockerfile: docker/scheduler/Dockerfile
    container_name: docintern-scheduler
    restart: unless-stopped
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
    environment:
      - APP_ENV=${APP_ENV:-local}
      - DB_HOST=mysql
      - DB_PORT=3306
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - RABBITMQ_HOST=rabbitmq
      - RABBITMQ_PORT=5672
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - docintern

  mysql:
    image: mysql:8.0
    container_name: docintern-mysql
    restart: unless-stopped
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD:-rootsecret}
      MYSQL_DATABASE: ${DB_DATABASE:-docintern}
      MYSQL_USER: ${DB_USERNAME:-docintern}
      MYSQL_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - docintern
    healthcheck:
      test:
        [
          "CMD",
          "mysqladmin",
          "ping",
          "-h",
          "localhost",
          "-u",
          "root",
          "-p${DB_ROOT_PASSWORD:-rootsecret}",
        ]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s
    command: >
      --default-authentication-plugin=mysql_native_password
      --character-set-server=utf8mb4
      --collation-server=utf8mb4_unicode_ci
      --innodb-buffer-pool-size=256M

  redis:
    image: redis:7-alpine
    container_name: docintern-redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    command: redis-server --appendonly yes --maxmemory 128mb --maxmemory-policy allkeys-lru

  rabbitmq:
    image: rabbitmq:3.13-management-alpine
    container_name: docintern-rabbitmq
    restart: unless-stopped
    ports:
      - "5672:5672"
      - "15672:15672"
    environment:
      RABBITMQ_DEFAULT_USER: ${RABBITMQ_USER:-docintern}
      RABBITMQ_DEFAULT_PASS: ${RABBITMQ_PASSWORD:-secret}
      RABBITMQ_DEFAULT_VHOST: ${RABBITMQ_VHOST:-/docintern}
      RABBITMQ_SERVER_ADDITIONAL_ERL_ARGS: -rabbitmq_management load_definitions "/etc/rabbitmq/definitions.json"
    volumes:
      - rabbitmq_data:/var/lib/rabbitmq
      - ./docker/rabbitmq/definitions.json:/etc/rabbitmq/definitions.json:ro
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "rabbitmq-diagnostics", "-q", "ping"]
      interval: 15s
      timeout: 10s
      retries: 5
      start_period: 30s

  localstack:
    image: localstack/localstack:3.7
    container_name: docintern-localstack
    restart: unless-stopped
    ports:
      - "4566:4566"
      - "4510-4559:4510-4559"
    environment:
      SERVICES: s3,cloudfront
      DEBUG: 0
      DEFAULT_REGION: ${AWS_DEFAULT_REGION:-us-east-1}
      AWS_ACCESS_KEY_ID: test
      AWS_SECRET_ACCESS_KEY: test
    volumes:
      - ./docker/localstack/init-s3.sh:/etc/localstack/init/ready.d/init-s3.sh
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:4566/_localstack/health"]
      interval: 15s
      timeout: 5s
      retries: 5
      start_period: 20s

  mailpit:
    image: axllent/mailpit:v1.19
    container_name: docintern-mailpit
    restart: unless-stopped
    ports:
      - "8025:8025"
      - "1025:1025"
    networks:
      - docintern

networks:
  docintern:
    driver: bridge

volumes:
  mysql_data:
    driver: local
  redis_data:
    driver: local
  rabbitmq_data:
    driver: local
```

### Makefile — Phase 3 Additions

```makefile
# Append to existing Makefile

worker-logs:
	docker compose logs -f worker

worker-restart:
	docker compose restart worker

worker-shell:
	docker compose exec worker bash

rabbitmq-queues:
	docker compose exec rabbitmq rabbitmqctl -p /docintern list_queues name messages consumers

scheduler-logs:
	docker compose logs -f scheduler
```

---

## Phase 3.5 — Live Infrastructure Cutover

### Changes from Phase 3

| Change                                | Detail                                                                                  |
| ------------------------------------- | --------------------------------------------------------------------------------------- |
| Provider mode contracts added         | Explicit runtime mode (`simulated` vs `live`) with environment guards                   |
| Real infra override profile added     | Staging/production-like runs use live AWS endpoints instead of LocalStack endpoints     |
| LocalStack made local-only            | Local emulation remains available only for local/testing workflows                       |
| Worker/app env alignment tightened    | `app` and `worker` share consistent provider and endpoint variables                      |
| Cutover verification commands added   | Make targets for queue/provider health validation before enabling Phase 4               |

### docker-compose override pattern for real infrastructure

Use a compose override (example: `docker-compose.infrastructure.yaml`) for production-like cutover runs:

```yaml
services:
  app:
    environment:
      - DOCINTERN_PROVIDER_MODE=live
      - AWS_ENDPOINT_URL=
      - AWS_USE_PATH_STYLE_ENDPOINT=false
  worker:
    environment:
      - DOCINTERN_PROVIDER_MODE=live
      - AWS_ENDPOINT_URL=
      - AWS_USE_PATH_STYLE_ENDPOINT=false
  localstack:
    profiles:
      - local-emulation
```

### Makefile — Phase 3.5 Additions

```makefile
cutover-check:
	docker compose exec app php artisan docintern:cutover-check

queue-health-check:
	docker compose exec app php artisan docintern:queue-health-check
```

---

## Phase 4 — Real-Time UI & WebSocket Integration

### Changes from Phase 3.5

| Change                    | Detail                                                            |
| ------------------------- | ----------------------------------------------------------------- |
| `reverb` service added    | Laravel Reverb WebSocket server for real-time broadcasting        |
| `app` environment updated | Add Reverb connection vars                                        |
| `worker` environment      | Add Reverb vars so workers can broadcast events                   |

### docker/reverb/Dockerfile

```dockerfile
FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        pcntl \
        bcmath \
        gd \
        zip \
        sockets \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

EXPOSE 8080

CMD ["php", "artisan", "reverb:start", "--host=0.0.0.0", "--port=8080"]
```

### docker-compose.yaml — Phase 4 New/Modified Services

```yaml
# ──────────────────────────────────────────────
# Laravel Reverb (WebSocket Server)
# ──────────────────────────────────────────────
reverb:
  build:
    context: .
    dockerfile: docker/reverb/Dockerfile
  container_name: docintern-reverb
  restart: unless-stopped
  ports:
    - "8080:8080"
  volumes:
    - .:/var/www/html
    - /var/www/html/vendor
  environment:
    - APP_ENV=${APP_ENV:-local}
    - DB_HOST=mysql
    - DB_PORT=3306
    - REDIS_HOST=redis
    - REDIS_PORT=6379
    - REVERB_SERVER_HOST=0.0.0.0
    - REVERB_SERVER_PORT=8080
    - REVERB_APP_ID=${REVERB_APP_ID:-docintern-local}
    - REVERB_APP_KEY=${REVERB_APP_KEY:-docintern-key}
    - REVERB_APP_SECRET=${REVERB_APP_SECRET:-docintern-secret}
  depends_on:
    redis:
      condition: service_healthy
  networks:
    - docintern
  healthcheck:
    test:
      [
        "CMD-SHELL",
        "php -r \"\\$c = @fsockopen('localhost', 8080); if (!\\$c) exit(1); fclose(\\$c);\"",
      ]
    interval: 15s
    timeout: 5s
    retries: 3
    start_period: 15s
```

### Updated environment vars for `app` and `worker`

Add these to both services:

```yaml
- BROADCAST_DRIVER=reverb
- REVERB_APP_ID=${REVERB_APP_ID:-docintern-local}
- REVERB_APP_KEY=${REVERB_APP_KEY:-docintern-key}
- REVERB_APP_SECRET=${REVERB_APP_SECRET:-docintern-secret}
- REVERB_HOST=reverb
- REVERB_PORT=8080
- REVERB_SCHEME=http
```

### Makefile — Phase 4 Additions

```makefile
reverb-logs:
	docker compose logs -f reverb

reverb-restart:
	docker compose restart reverb
```

---

## Phase 5 — Document Review & Annotation

### Changes from Phase 3.5/4

| Change           | Detail                                                                                    |
| ---------------- | ----------------------------------------------------------------------------------------- |
| No new services  | Phase 5 is purely application-level code (PDF viewer, annotation layer, review workflow). |
| `app` Dockerfile | No changes. `pdf.js` is an npm dependency, handled by the `node` container.               |

**No Docker changes in this phase.** Use the Phase 4 `docker-compose.yaml` as-is.

---

## Phase 6 — Client Portal & Notifications

### Changes from Phase 4

| Change                         | Detail                                              |
| ------------------------------ | --------------------------------------------------- |
| `worker` supervisord.conf      | Add notification consumer processes (email, in-app) |
| LocalStack init script updated | Create SNS topics for notifications                 |

### Updated docker/workers/supervisord.conf — Phase 6 Additions

Append to the existing `supervisord.conf`:

```ini
# ── Email Notification Consumer (2 processes) ──
[program:worker-notify-email]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan docintern:consume notify-email --sleep=3 --tries=3 --timeout=30
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=docintern
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker-notify-email.log
stopwaitsecs=15

# ── In-App Notification Consumer (2 processes) ──
[program:worker-notify-inapp]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan docintern:consume notify-inapp --sleep=3 --tries=3 --timeout=15
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=docintern
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker-notify-inapp.log
stopwaitsecs=15
```

### Updated docker/localstack/init-s3.sh — Phase 6

```bash
#!/bin/bash
echo "Initializing LocalStack resources..."

# S3
awslocal s3 mb s3://docintern-dev
awslocal s3api put-bucket-versioning \
    --bucket docintern-dev \
    --versioning-configuration Status=Enabled

# SNS Topics
awslocal sns create-topic --name docintern-document-processed
awslocal sns create-topic --name docintern-review-required
awslocal sns create-topic --name docintern-textract-complete

echo "LocalStack initialization complete."
```

**No new services in docker-compose.yaml.** The worker container picks up the new consumers via the updated `supervisord.conf`.

---

## Phase 7 — Analytics, Reporting & Hardening

### Changes from Phase 6

| Change                      | Detail                                              |
| --------------------------- | --------------------------------------------------- |
| `meilisearch` service added | Full-text search engine for document content search |
| `app` environment updated   | Add Meilisearch connection vars                     |
| New volume                  | `meilisearch_data` for search index persistence     |

### docker-compose.yaml — Phase 7 New Service

```yaml
# ──────────────────────────────────────────────
# Meilisearch (Full-Text Search)
# ──────────────────────────────────────────────
meilisearch:
  image: getmeili/meilisearch:v1.10
  container_name: docintern-meilisearch
  restart: unless-stopped
  ports:
    - "7700:7700"
  environment:
    MEILI_ENV: development
    MEILI_MASTER_KEY: ${MEILISEARCH_KEY:-masterKey123}
    MEILI_NO_ANALYTICS: true
  volumes:
    - meilisearch_data:/meili_data
  networks:
    - docintern
  healthcheck:
    test: ["CMD", "curl", "-f", "http://localhost:7700/health"]
    interval: 15s
    timeout: 5s
    retries: 5
    start_period: 10s
```

### Updated `app` and `worker` environment

```yaml
- SCOUT_DRIVER=meilisearch
- MEILISEARCH_HOST=http://meilisearch:7700
- MEILISEARCH_KEY=${MEILISEARCH_KEY:-masterKey123}
```

### Updated volumes block

```yaml
volumes:
  mysql_data:
    driver: local
  redis_data:
    driver: local
  rabbitmq_data:
    driver: local
  meilisearch_data:
    driver: local
```

### Makefile — Phase 7 Additions

```makefile
search-reset:
	docker compose exec app php artisan scout:flush "App\\Models\\Document"
	docker compose exec app php artisan scout:import "App\\Models\\Document"

health:
	docker compose exec app php artisan docintern:health
```

---

## Final docker-compose.yaml — Phase 7 (Complete)

```yaml
version: "3.8"

services:
  # ──────────────────────────────────────────────
  # Laravel Application (PHP-FPM)
  # ──────────────────────────────────────────────
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      args:
        USER_ID: ${DOCKER_USER_ID:-1000}
        GROUP_ID: ${DOCKER_GROUP_ID:-1000}
    container_name: docintern-app
    restart: unless-stopped
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
    environment:
      - APP_ENV=${APP_ENV:-local}
      - DB_HOST=mysql
      - DB_PORT=3306
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - RABBITMQ_HOST=rabbitmq
      - RABBITMQ_PORT=5672
      - MAIL_HOST=mailpit
      - MAIL_PORT=1025
      - AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID:-test}
      - AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY:-test}
      - AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION:-us-east-1}
      - AWS_S3_BUCKET=${AWS_S3_BUCKET:-docintern-dev}
      - AWS_ENDPOINT_URL=http://localstack:4566
      - AWS_USE_PATH_STYLE_ENDPOINT=true
      - BROADCAST_DRIVER=reverb
      - REVERB_APP_ID=${REVERB_APP_ID:-docintern-local}
      - REVERB_APP_KEY=${REVERB_APP_KEY:-docintern-key}
      - REVERB_APP_SECRET=${REVERB_APP_SECRET:-docintern-secret}
      - REVERB_HOST=reverb
      - REVERB_PORT=8080
      - REVERB_SCHEME=http
      - SCOUT_DRIVER=meilisearch
      - MEILISEARCH_HOST=http://meilisearch:7700
      - MEILISEARCH_KEY=${MEILISEARCH_KEY:-masterKey123}
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
      rabbitmq:
        condition: service_healthy
      localstack:
        condition: service_healthy
      meilisearch:
        condition: service_healthy
    networks:
      - docintern
    healthcheck:
      test: ["CMD-SHELL", "php-fpm-healthcheck || exit 1"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 30s

  # ──────────────────────────────────────────────
  # Nginx Web Server
  # ──────────────────────────────────────────────
  nginx:
    build:
      context: .
      dockerfile: docker/nginx/Dockerfile
    container_name: docintern-nginx
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html:ro
    depends_on:
      - app
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 5s
      retries: 3

  # ──────────────────────────────────────────────
  # Vite Dev Server (HMR)
  # ──────────────────────────────────────────────
  node:
    build:
      context: .
      dockerfile: docker/node/Dockerfile
    container_name: docintern-node
    restart: unless-stopped
    ports:
      - "5173:5173"
    volumes:
      - .:/var/www/html
      - /var/www/html/node_modules
    networks:
      - docintern

  # ──────────────────────────────────────────────
  # Queue Workers (Supervisor-managed consumers)
  # ──────────────────────────────────────────────
  worker:
    build:
      context: .
      dockerfile: docker/workers/Dockerfile
      args:
        USER_ID: ${DOCKER_USER_ID:-1000}
        GROUP_ID: ${DOCKER_GROUP_ID:-1000}
    container_name: docintern-worker
    restart: unless-stopped
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
    environment:
      - APP_ENV=${APP_ENV:-local}
      - DB_HOST=mysql
      - DB_PORT=3306
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - RABBITMQ_HOST=rabbitmq
      - RABBITMQ_PORT=5672
      - AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID:-test}
      - AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY:-test}
      - AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION:-us-east-1}
      - AWS_S3_BUCKET=${AWS_S3_BUCKET:-docintern-dev}
      - AWS_ENDPOINT_URL=http://localstack:4566
      - AWS_USE_PATH_STYLE_ENDPOINT=true
      - BROADCAST_DRIVER=reverb
      - REVERB_APP_ID=${REVERB_APP_ID:-docintern-local}
      - REVERB_APP_KEY=${REVERB_APP_KEY:-docintern-key}
      - REVERB_APP_SECRET=${REVERB_APP_SECRET:-docintern-secret}
      - REVERB_HOST=reverb
      - REVERB_PORT=8080
      - REVERB_SCHEME=http
      - SCOUT_DRIVER=meilisearch
      - MEILISEARCH_HOST=http://meilisearch:7700
      - MEILISEARCH_KEY=${MEILISEARCH_KEY:-masterKey123}
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
      rabbitmq:
        condition: service_healthy
      localstack:
        condition: service_healthy
    networks:
      - docintern

  # ──────────────────────────────────────────────
  # Laravel Reverb (WebSocket Server)
  # ──────────────────────────────────────────────
  reverb:
    build:
      context: .
      dockerfile: docker/reverb/Dockerfile
    container_name: docintern-reverb
    restart: unless-stopped
    ports:
      - "8080:8080"
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
    environment:
      - APP_ENV=${APP_ENV:-local}
      - DB_HOST=mysql
      - DB_PORT=3306
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - REVERB_SERVER_HOST=0.0.0.0
      - REVERB_SERVER_PORT=8080
      - REVERB_APP_ID=${REVERB_APP_ID:-docintern-local}
      - REVERB_APP_KEY=${REVERB_APP_KEY:-docintern-key}
      - REVERB_APP_SECRET=${REVERB_APP_SECRET:-docintern-secret}
    depends_on:
      redis:
        condition: service_healthy
    networks:
      - docintern
    healthcheck:
      test:
        [
          "CMD-SHELL",
          "php -r \"\\$c = @fsockopen('localhost', 8080); if (!\\$c) exit(1); fclose(\\$c);\"",
        ]
      interval: 15s
      timeout: 5s
      retries: 3
      start_period: 15s

  # ──────────────────────────────────────────────
  # Laravel Task Scheduler
  # ──────────────────────────────────────────────
  scheduler:
    build:
      context: .
      dockerfile: docker/scheduler/Dockerfile
    container_name: docintern-scheduler
    restart: unless-stopped
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
    environment:
      - APP_ENV=${APP_ENV:-local}
      - DB_HOST=mysql
      - DB_PORT=3306
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - RABBITMQ_HOST=rabbitmq
      - RABBITMQ_PORT=5672
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - docintern

  # ──────────────────────────────────────────────
  # MySQL 8.0
  # ──────────────────────────────────────────────
  mysql:
    image: mysql:8.0
    container_name: docintern-mysql
    restart: unless-stopped
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD:-rootsecret}
      MYSQL_DATABASE: ${DB_DATABASE:-docintern}
      MYSQL_USER: ${DB_USERNAME:-docintern}
      MYSQL_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - docintern
    healthcheck:
      test:
        [
          "CMD",
          "mysqladmin",
          "ping",
          "-h",
          "localhost",
          "-u",
          "root",
          "-p${DB_ROOT_PASSWORD:-rootsecret}",
        ]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s
    command: >
      --default-authentication-plugin=mysql_native_password
      --character-set-server=utf8mb4
      --collation-server=utf8mb4_unicode_ci
      --innodb-buffer-pool-size=256M

  # ──────────────────────────────────────────────
  # Redis 7
  # ──────────────────────────────────────────────
  redis:
    image: redis:7-alpine
    container_name: docintern-redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    command: redis-server --appendonly yes --maxmemory 128mb --maxmemory-policy allkeys-lru

  # ──────────────────────────────────────────────
  # RabbitMQ 3.13 with Management UI
  # ──────────────────────────────────────────────
  rabbitmq:
    image: rabbitmq:3.13-management-alpine
    container_name: docintern-rabbitmq
    restart: unless-stopped
    ports:
      - "5672:5672"
      - "15672:15672"
    environment:
      RABBITMQ_DEFAULT_USER: ${RABBITMQ_USER:-docintern}
      RABBITMQ_DEFAULT_PASS: ${RABBITMQ_PASSWORD:-secret}
      RABBITMQ_DEFAULT_VHOST: ${RABBITMQ_VHOST:-/docintern}
      RABBITMQ_SERVER_ADDITIONAL_ERL_ARGS: -rabbitmq_management load_definitions "/etc/rabbitmq/definitions.json"
    volumes:
      - rabbitmq_data:/var/lib/rabbitmq
      - ./docker/rabbitmq/definitions.json:/etc/rabbitmq/definitions.json:ro
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "rabbitmq-diagnostics", "-q", "ping"]
      interval: 15s
      timeout: 10s
      retries: 5
      start_period: 30s

  # ──────────────────────────────────────────────
  # LocalStack (AWS Emulation)
  # ──────────────────────────────────────────────
  localstack:
    image: localstack/localstack:3.7
    container_name: docintern-localstack
    restart: unless-stopped
    ports:
      - "4566:4566"
      - "4510-4559:4510-4559"
    environment:
      SERVICES: s3,cloudfront,lambda,sns,dynamodb
      DEBUG: 0
      DEFAULT_REGION: ${AWS_DEFAULT_REGION:-us-east-1}
      AWS_ACCESS_KEY_ID: test
      AWS_SECRET_ACCESS_KEY: test
      LAMBDA_EXECUTOR: docker
      DOCKER_HOST: unix:///var/run/docker.sock
    volumes:
      - ./docker/localstack/init-s3.sh:/etc/localstack/init/ready.d/init-s3.sh
      - /var/run/docker.sock:/var/run/docker.sock
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:4566/_localstack/health"]
      interval: 15s
      timeout: 5s
      retries: 5
      start_period: 20s

  # ──────────────────────────────────────────────
  # Meilisearch (Full-Text Search)
  # ──────────────────────────────────────────────
  meilisearch:
    image: getmeili/meilisearch:v1.10
    container_name: docintern-meilisearch
    restart: unless-stopped
    ports:
      - "7700:7700"
    environment:
      MEILI_ENV: development
      MEILI_MASTER_KEY: ${MEILISEARCH_KEY:-masterKey123}
      MEILI_NO_ANALYTICS: true
    volumes:
      - meilisearch_data:/meili_data
    networks:
      - docintern
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:7700/health"]
      interval: 15s
      timeout: 5s
      retries: 5
      start_period: 10s

  # ──────────────────────────────────────────────
  # Mailpit (Local Email Testing)
  # ──────────────────────────────────────────────
  mailpit:
    image: axllent/mailpit:v1.19
    container_name: docintern-mailpit
    restart: unless-stopped
    ports:
      - "8025:8025"
      - "1025:1025"
    networks:
      - docintern

networks:
  docintern:
    driver: bridge

volumes:
  mysql_data:
    driver: local
  redis_data:
    driver: local
  rabbitmq_data:
    driver: local
  meilisearch_data:
    driver: local
```

---

## Appendix A: Production Dockerfile (Multi-Stage)

### docker/php/Dockerfile.prod

```dockerfile
# ── Stage 1: Composer dependencies ──
FROM composer:2.7 AS composer-deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# ── Stage 2: Node build ──
FROM node:20-alpine AS node-build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ── Stage 3: Final production image ──
FROM php:8.3-fpm-bookworm AS production

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        sockets \
        opcache \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Production PHP config
RUN echo "opcache.enable=1\n\
opcache.memory_consumption=256\n\
opcache.max_accelerated_files=20000\n\
opcache.validate_timestamps=0\n\
upload_max_filesize=100M\n\
post_max_size=120M\n\
memory_limit=256M\n\
max_execution_time=120" > /usr/local/etc/php/conf.d/production.ini

# Non-root user
RUN groupadd -g 1000 docintern \
    && useradd -u 1000 -g docintern -m docintern

WORKDIR /var/www/html

# Copy application
COPY --chown=docintern:docintern . .
COPY --from=composer-deps --chown=docintern:docintern /app/vendor ./vendor
COPY --from=node-build --chown=docintern:docintern /app/public/build ./public/build

# Generate autoloader
RUN composer dump-autoload --optimize --no-dev

# Cache config, routes, views
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

USER docintern

EXPOSE 9000
CMD ["php-fpm"]
```

---

## Appendix B: docker-compose.testing.yaml

```yaml
version: "3.8"

# Usage: docker compose -f docker-compose.yaml -f docker-compose.testing.yaml up -d

services:
  app:
    environment:
      - APP_ENV=testing
      - DB_DATABASE=docintern_test

  mysql:
    environment:
      MYSQL_DATABASE: docintern_test
    tmpfs:
      - /var/lib/mysql # RAM-backed DB for fast tests
    volumes: [] # Override: no persistent volume

  redis:
    volumes: []
    command: redis-server --maxmemory 64mb --maxmemory-policy allkeys-lru --save ""

  rabbitmq:
    volumes: []
```

---

## Appendix C: Port Reference

| Port  | Service     | Purpose               |
| ----- | ----------- | --------------------- |
| 80    | Nginx       | HTTP (application)    |
| 3306  | MySQL       | Database              |
| 5173  | Node (Vite) | HMR dev server        |
| 5672  | RabbitMQ    | AMQP protocol         |
| 6379  | Redis       | Cache / sessions      |
| 7700  | Meilisearch | Search API            |
| 8025  | Mailpit     | Email testing web UI  |
| 8080  | Reverb      | WebSocket server      |
| 15672 | RabbitMQ    | Management UI         |
| 4566  | LocalStack  | AWS emulation gateway |

---

## Appendix D: Phase-by-Phase Service Summary

| Service       | Ph1 | Ph2 | Ph3  | Ph4  | Ph5 | Ph6  | Ph7 |
| ------------- | --- | --- | ---- | ---- | --- | ---- | --- |
| `app`         | ✅  | ✅  | ✅   | ✅   | ✅  | ✅   | ✅  |
| `nginx`       | ✅  | ✅  | ✅   | ✅   | ✅  | ✅   | ✅  |
| `node`        | ✅  | ✅  | ✅   | ✅   | ✅  | ✅   | ✅  |
| `mysql`       | ✅  | ✅  | ✅   | ✅   | ✅  | ✅   | ✅  |
| `redis`       | ✅  | ✅  | ✅   | ✅   | ✅  | ✅   | ✅  |
| `rabbitmq`    | ✅  | ✅  | ✅\* | ✅   | ✅  | ✅   | ✅  |
| `mailpit`     | ✅  | ✅  | ✅   | ✅   | ✅  | ✅   | ✅  |
| `localstack`  |     | ✅  | ✅   | ✅\* | ✅  | ✅\* | ✅  |
| `worker`      |     |     | ✅   | ✅   | ✅  | ✅\* | ✅  |
| `scheduler`   |     |     | ✅   | ✅   | ✅  | ✅   | ✅  |
| `reverb`      |     |     |      | ✅   | ✅  | ✅   | ✅  |
| `meilisearch` |     |     |      |      |     |      | ✅  |

`*` = modified configuration in that phase
