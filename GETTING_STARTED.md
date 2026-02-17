# Getting Started

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/install/) installed
- [Make](https://www.gnu.org/software/make/) installed (optional, for convenience commands)

## Setup

### 1. Clone the repository

```bash
git clone <repository-url> docintern
cd docintern
```

### 2. Configure environment

```bash
cp .env.example .env
```

Generate an application key (after containers are running):

```bash
make artisan key:generate
```

### 3. Build and start containers

```bash
make build
make up
```

This starts 7 services:

| Service    | Container          | URL / Port                   |
|------------|--------------------|------------------------------|
| App        | docintern-app      | Internal (PHP-FPM :9000)     |
| Nginx      | docintern-nginx    | http://localhost              |
| Vite (HMR) | docintern-node    | http://localhost:5173         |
| MySQL      | docintern-mysql    | localhost:3306                |
| Redis      | docintern-redis    | localhost:6379                |
| RabbitMQ   | docintern-rabbitmq | localhost:5672 (AMQP)        |
| Mailpit    | docintern-mailpit  | http://localhost:8025 (Web UI)|

RabbitMQ Management UI is available at http://localhost:15672 (user: `docintern`, password: `secret`).

### 4. Install dependencies

```bash
make composer install
make npm install
```

### 5. Run migrations

```bash
make migrate
```

To migrate and seed in one step:

```bash
make fresh
```

### 6. Access the application

Open http://localhost in your browser.

## Daily workflow

```bash
make up       # Start all containers
make down     # Stop all containers
```

## Makefile commands

| Command                       | Description                           |
|-------------------------------|---------------------------------------|
| `make up`                     | Start all containers in detached mode |
| `make down`                   | Stop all containers                   |
| `make build`                  | Rebuild all images (no cache)         |
| `make shell`                  | Open a bash shell in the app container|
| `make composer <cmd>`         | Run a Composer command                |
| `make artisan <cmd>`          | Run an Artisan command                |
| `make migrate`                | Run database migrations               |
| `make seed`                   | Run database seeders                  |
| `make fresh`                  | Fresh migrate with seeders            |
| `make test`                   | Run the test suite                    |
| `make npm <cmd>`              | Run an npm command in the node container |
| `make logs <service>`         | Tail logs for a service               |

## Connecting to the database

Use any MySQL client with these credentials (from `.env.example` defaults):

| Field    | Value      |
|----------|------------|
| Host     | 127.0.0.1  |
| Port     | 3306       |
| Database | docintern  |
| User     | docintern  |
| Password | secret     |

## Email testing

All outgoing emails are captured by Mailpit. View them at http://localhost:8025.

## Troubleshooting

### Port conflicts

If a port is already in use on your host, either stop the conflicting service or change the host port mapping in `docker-compose.yaml` (e.g., `"3307:3306"` for MySQL).

### Permission issues (Linux)

If you encounter file permission issues, set your user/group ID in `.env.docker` or pass them as environment variables:

```bash
DOCKER_USER_ID=$(id -u) DOCKER_GROUP_ID=$(id -g) make build
```

### Frontend changes not reflecting

If UI changes don't appear, ensure the Vite dev server is running:

```bash
make logs node
```

Or rebuild assets manually:

```bash
make npm run build
```
