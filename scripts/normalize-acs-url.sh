#!/bin/sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
ROOT_DIR="$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)"

if [ -f "${ROOT_DIR}/.env" ]; then
  set -a
  # shellcheck disable=SC1091
  . "${ROOT_DIR}/.env"
  set +a
fi

PROJECT_NAME="${PROJECT_NAME:-genieacs-prod}"
DASHBOARD_CONTAINER="${PROJECT_NAME}-dashboard"

exec docker exec -i "${DASHBOARD_CONTAINER}" php /var/www/html/scripts/normalize-acs-url.php "$@"
