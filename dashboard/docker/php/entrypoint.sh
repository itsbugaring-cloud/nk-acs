#!/bin/sh
set -eu

wait_for_db() {
  echo "[dashboard] waiting for MariaDB"
  until mariadb-admin ping -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASSWORD}" --silent >/dev/null 2>&1; do
    sleep 2
  done
}

bootstrap_db() {
  table_count="$(mariadb -N -s -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" -e "SHOW TABLES;" 2>/dev/null | wc -l | tr -d ' ')"

  if [ "${table_count}" = "0" ]; then
    echo "[dashboard] importing database.sql"
    mariadb -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" < /var/www/html/database.sql
  else
    echo "[dashboard] schema already present, skipping import"
  fi

  admin_hash="$(php -r 'echo password_hash(getenv("DASHBOARD_ADMIN_PASSWORD"), PASSWORD_BCRYPT);')"
  mariadb -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" <<SQL
INSERT INTO users (username, password)
VALUES ('${DASHBOARD_ADMIN_USERNAME}', '${admin_hash}')
ON DUPLICATE KEY UPDATE password = VALUES(password);

INSERT INTO genieacs_credentials (host, port, username, password, is_connected)
VALUES ('${DASHBOARD_GENIEACS_HOST}', ${DASHBOARD_GENIEACS_PORT}, '${DASHBOARD_GENIEACS_USERNAME}', '${DASHBOARD_GENIEACS_PASSWORD}', 0)
ON DUPLICATE KEY UPDATE
  host = VALUES(host),
  port = VALUES(port),
  username = VALUES(username),
  password = VALUES(password);
SQL
}

mark_genieacs_connection() {
  probe_url="http://${DASHBOARD_GENIEACS_HOST}:${DASHBOARD_GENIEACS_PORT}/devices?limit=1"
  auth_args=""

  if [ -n "${DASHBOARD_GENIEACS_USERNAME}" ]; then
    auth_args="-u ${DASHBOARD_GENIEACS_USERNAME}:${DASHBOARD_GENIEACS_PASSWORD}"
  fi

  echo "[dashboard] probing GenieACS NBI at ${probe_url}"

  http_code="$(
    sh -c "curl -sS -o /tmp/genieacs_probe.json -w '%{http_code}' ${auth_args} '${probe_url}'" 2>/dev/null || true
  )"

  if [ "${http_code}" = "200" ]; then
    echo "[dashboard] GenieACS NBI reachable, marking dashboard connection as active"
    mariadb -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" <<SQL
UPDATE genieacs_credentials
SET is_connected = 1,
    role = 'admin',
    last_test = NOW(),
    updated_at = NOW()
WHERE host = '${DASHBOARD_GENIEACS_HOST}'
  AND port = ${DASHBOARD_GENIEACS_PORT};
SQL
  else
    echo "[dashboard] GenieACS NBI probe failed (HTTP ${http_code:-none}), keeping dashboard connection as inactive"
  fi
}

hardening() {
  if [ "${REMOVE_INIT_PHP:-true}" = "true" ] && [ -f /var/www/html/init.php ]; then
    mv /var/www/html/init.php /var/www/html/init.php.disabled
  fi
}

wait_for_db
bootstrap_db
mark_genieacs_connection
hardening

exec "$@"
