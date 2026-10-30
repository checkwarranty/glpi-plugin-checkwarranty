#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"
docker compose up -d
for major in 10 11; do
  for attempt in $(seq 1 30); do
    if docker compose exec -T "glpi${major}-db" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then break; fi
    sleep 2
  done
  docker compose exec -T "glpi${major}" php bin/console database:install --db-host="glpi${major}-db" --db-name=glpi --db-user=glpi --db-password=acceptance-only --no-telemetry --no-interaction
  docker compose exec -T "glpi${major}" php bin/console plugin:install checkwarranty --username=glpi --no-interaction
  docker compose exec -T "glpi${major}" php bin/console plugin:activate checkwarranty --no-interaction
  docker compose exec -T "glpi${major}" php /opt/cw-testing/runtime.php
  docker compose exec -T "glpi${major}" php bin/console plugin:deactivate checkwarranty --no-interaction
  docker compose exec -T "glpi${major}" php bin/console plugin:activate checkwarranty --no-interaction
  docker compose exec -T "glpi${major}" php bin/console plugin:uninstall checkwarranty --no-interaction
  docker compose exec -T "glpi${major}-db" mariadb -uglpi -pacceptance-only glpi -N -e "SHOW TABLES LIKE 'glpi_plugin_checkwarranty_%'" > "schema-${major}.txt"
  test ! -s "schema-${major}.txt"
done
