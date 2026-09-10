#!/usr/bin/env bash
# Brings the admin panel up from nothing: dependencies, .env, schema, seed data, server.
# Every step is guarded so this is safe to re-run — `docker compose up` after a restart
# reinstalls nothing and reseeds nothing.
set -euo pipefail

cd /var/www/html

say() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }

say "Waiting for MySQL at ${WAIT_DB_HOST}:${WAIT_DB_PORT}"
# Tested through PDO rather than the mysqladmin client on purpose. The client Debian
# ships is MariaDB's, and it refuses MySQL 8's auto-generated self-signed certificate
# with "TLS/SSL error: self-signed certificate in certificate chain" — so it reports a
# perfectly healthy server as unreachable. PDO is also simply the more meaningful check:
# it is the driver Laravel itself will connect with.
#
# These read WAIT_DB_* rather than DB_*: anything named DB_* in the container environment
# would outrank phpunit.xml's <env> pinning and point the test suite at this database.
until php -r '
    try {
        new PDO(
            sprintf("mysql:host=%s;port=%s", getenv("WAIT_DB_HOST"), getenv("WAIT_DB_PORT")),
            getenv("WAIT_DB_USERNAME"),
            getenv("WAIT_DB_PASSWORD")
        );
    } catch (Throwable $e) {
        exit(1);
    }
' 2>/dev/null; do
    sleep 1
done
echo "MySQL is up."

if [ ! -f vendor/autoload.php ]; then
    say "Installing Composer dependencies (first run — this takes a few minutes)"
    composer install --no-interaction --prefer-dist --no-progress
fi

if [ ! -f .env ]; then
    say "Creating .env from .env.docker"
    cp .env.docker .env
fi

# The template ships APP_KEY empty; Laravel refuses to boot without one.
if ! grep -qE '^APP_KEY=base64:' .env; then
    say "Generating application key"
    php artisan key:generate --force
fi

# storage/app/public -> public/storage, so uploads written to the local disk are
# reachable. Bind-mounted, so it survives; --force keeps a stale link from stopping us.
if [ ! -L public/storage ]; then
    say "Linking storage"
    php artisan storage:link --force
fi

say "Running migrations"
php artisan migrate --force

# DatabaseSeeder is idempotent, but it is not free — only run it into an empty database
# so a restart doesn't pay for it. Set SEED=always to force it.
USER_COUNT=$(php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tr -dc '0-9' || echo 0)
if [ "${SEED:-auto}" = "always" ] || [ "${USER_COUNT:-0}" = "0" ]; then
    say "Seeding database"
    php artisan db:seed --force
else
    echo "Database already has ${USER_COUNT} users — skipping seed."
fi

# A config cache baked from a previous run's .env would silently win over the current one.
php artisan config:clear >/dev/null 2>&1 || true

say "Admin panel ready at http://localhost:8000/login"
exec php artisan serve --host=0.0.0.0 --port=8000
