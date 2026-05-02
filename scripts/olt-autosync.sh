#!/bin/sh
set -eu

LOG_DIR="/var/log/netking"
LOG_FILE="${LOG_DIR}/olt-autosync.log"
LOCK_FILE="/tmp/netking-olt-autosync.lock"
mkdir -p "${LOG_DIR}"

exec 9>"${LOCK_FILE}"
if ! flock -n 9; then
  exit 0
fi

timestamp() {
  date '+%Y-%m-%d %H:%M:%S'
}

{
  echo "[$(timestamp)] OLT auto-sync started"
  docker exec genieacs-prod-dashboard php /var/www/html/scripts/sync-olt-onus.php
  echo "[$(timestamp)] OLT auto-sync finished"
} >> "${LOG_FILE}" 2>&1
