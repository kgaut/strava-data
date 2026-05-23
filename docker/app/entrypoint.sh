#!/usr/bin/env bash
set -euo pipefail

# Wait for the database to accept connections (max 30s).
if [ -n "${DATABASE_URL:-}" ]; then
    php -r '
        $url = parse_url(getenv("DATABASE_URL"));
        $host = $url["host"] ?? "db";
        $port = $url["port"] ?? 5432;
        $deadline = time() + 30;
        while (time() < $deadline) {
            $sock = @fsockopen($host, $port, $e, $err, 1.0);
            if ($sock) { fclose($sock); exit(0); }
            usleep(500000);
        }
        fwrite(STDERR, "database $host:$port not reachable\n");
        exit(1);
    '
fi

# Apply pending Doctrine migrations on container start (idempotent).
if [ "${RUN_MIGRATIONS_ON_BOOT:-1}" = "1" ]; then
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
fi

# Warm cache in prod.
if [ "${APP_ENV:-prod}" = "prod" ]; then
    php bin/console cache:warmup --no-debug || true
fi

exec "$@"
