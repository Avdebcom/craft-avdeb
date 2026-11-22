#!/usr/bin/env bash
# Throw-away Craft CMS 5 site (Docker: MariaDB + craftcms/nginx:8.2) with this plugin mounted
# read-only, CKEditor, a "Blog" channel and two posts.
#   tests/dev-site.sh [name] [port]      → http://localhost:<port>/admin  (admin / password)
#   AVDEB_INSTALL=0 tests/dev-site.sh    → the plugin is required but not installed yet
#   AVDEB_REF_CODE=CODE tests/dev-site.sh → referral code saved in the plugin settings
# The Composer project is built once in ~/.cache/craft-avdeb/site (needs host composer).
# Remove it:      docker rm -f <name> <name>-db && docker network rm <name>
set -euo pipefail
NAME="${1:-avdeb-craft}"
PORT="${2:-8094}"
PLUGIN="$(cd "$(dirname "$0")/.." && pwd)"
CACHE="${AVDEB_CRAFT_CACHE:-$HOME/.cache/craft-avdeb}"
SITE="$CACHE/site"
IMAGE="${AVDEB_CRAFT_IMAGE:-craftcms/nginx:8.2}"

if [ ! -f "$SITE/vendor/autoload.php" ]; then
  mkdir -p "$CACHE"
  rm -rf "$SITE"
  composer create-project craftcms/craft "$SITE" --no-install --no-interaction --no-scripts
  (
    cd "$SITE"
    composer config platform.php 8.2.26
    composer config minimum-stability dev
    composer config repositories.avdeb "{\"type\":\"path\",\"url\":\"$PLUGIN\",\"options\":{\"symlink\":true,\"versions\":{\"avdeb/craft-avdeb\":\"1.0.0\"}}}"
    composer require "avdeb/craft-avdeb:^1.0" craftcms/ckeditor --no-interaction --ignore-platform-reqs --no-scripts
  )
fi

docker rm -f "$NAME" "$NAME-db" >/dev/null 2>&1 || true
docker network rm "$NAME" >/dev/null 2>&1 || true
docker network create "$NAME" >/dev/null
docker run -d --name "$NAME-db" --network "$NAME" \
  -e MARIADB_DATABASE=craft -e MARIADB_USER=craft -e MARIADB_PASSWORD=craft -e MARIADB_ROOT_PASSWORD=root \
  mariadb:11 >/dev/null
# nginx + PHP start only after the install, so the port refuses connections until the site is ready.
docker run -d --name "$NAME" --network "$NAME" -p "$PORT:8080" \
  -e PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 -e PHP_MEMORY_LIMIT=512M \
  -v "$PLUGIN:$PLUGIN:ro" --entrypoint sleep "$IMAGE" infinity >/dev/null
tar -C "$SITE" --exclude=./config/project --exclude=./storage/runtime --exclude=./storage/logs --exclude=./web/cpresources -cf - . \
  | docker exec -i -u root "$NAME" tar -C /app -xf -
docker exec -i -u root "$NAME" sh -c "
  set -e
  rm -rf /app/templates && cp -r '$PLUGIN/tests/site/templates' /app/templates
  mkdir -p /app/storage /app/web/cpresources /app/config/project
  cat > /app/.env <<ENV
CRAFT_APP_ID=avdeb-craft-dev
CRAFT_ENVIRONMENT=dev
CRAFT_SECURITY_KEY=avdeb-dev-only-security-key-0123456789
CRAFT_DEV_MODE=true
CRAFT_ALLOW_ADMIN_CHANGES=true
CRAFT_DISALLOW_ROBOTS=true
CRAFT_DB_DRIVER=mysql
CRAFT_DB_SERVER=$NAME-db
CRAFT_DB_PORT=3306
CRAFT_DB_DATABASE=craft
CRAFT_DB_USER=craft
CRAFT_DB_PASSWORD=craft
PRIMARY_SITE_URL=http://localhost:$PORT
ENV
  chown -R www-data:www-data /app
"
for _ in $(seq 1 60); do
  docker exec "$NAME-db" mariadb -ucraft -pcraft -e 'select 1' craft >/dev/null 2>&1 && break
  sleep 1
done
docker exec -u www-data "$NAME" sh -c "
  set -e
  cd /app
  php craft install --interactive=0 --email=admin@example.com --username=admin --password=password \
    --site-name='Maker Notes' --site-url='\$PRIMARY_SITE_URL' --language=en-US >/dev/null
  php craft plugin/install ckeditor >/dev/null
  php '$PLUGIN/tests/craft-seed.php' /app
  if [ '${AVDEB_INSTALL:-1}' != 0 ]; then
    php craft plugin/install avdeb
    if [ -n '${AVDEB_REF_CODE:-}' ]; then php craft project-config/set plugins.avdeb.settings.refCode '${AVDEB_REF_CODE:-}' >/dev/null; fi
  fi
"
docker exec -d "$NAME" /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisor.conf
for _ in $(seq 1 60); do
  curl -s -o /dev/null "http://localhost:$PORT/admin/login" && break
  sleep 0.5
done
echo "Craft ready: http://localhost:$PORT/admin (admin / password)"
