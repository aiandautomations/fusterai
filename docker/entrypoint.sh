#!/usr/bin/env bash

if [ ! -z "$WWWUSER" ]; then
    usermod -u $WWWUSER sail
fi

if [ ! -d /.composer ]; then
    mkdir /.composer
fi

chmod -R ugo+rw /.composer

# If a command was passed (e.g. "php artisan horizon"), run it directly
if [ $# -gt 0 ]; then
    exec gosu $WWWUSER "$@"
fi

# --- Bootstrap (runs only on the main app container, not horizon/scheduler) ---
cd /var/www/html

echo "Waiting for database..."
until gosu $WWWUSER php artisan db:show > /dev/null 2>&1; do
    sleep 2
done

echo "Running migrations..."
gosu $WWWUSER php artisan migrate --force

echo "Generating Passport OAuth keys (if missing)..."
gosu $WWWUSER php artisan passport:keys --force 2>/dev/null || true

echo "Installing frontend dependencies..."
npm install

echo "Building frontend assets..."
npm run build

echo "Caching routes and views..."
gosu $WWWUSER php artisan route:cache
gosu $WWWUSER php artisan view:cache

echo "Linking storage..."
gosu $WWWUSER php artisan storage:link --force 2>/dev/null || true

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
