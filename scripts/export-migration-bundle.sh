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
MONGO_DATABASE="${MONGO_DATABASE:-genieacs}"
TS="$(date '+%Y%m%d-%H%M%S')"
OUT_BASE="${ROOT_DIR}/backups"
WORK_DIR="${OUT_BASE}/migration-${TS}"
BUNDLE="${OUT_BASE}/genieacs-migration-${TS}.tar.gz"

mkdir -p "${WORK_DIR}"
mkdir -p "${OUT_BASE}"

echo "[1/7] Menjalankan preflight..."
"${SCRIPT_DIR}/preflight-portability.sh"

echo "[2/7] Menyalin konfigurasi inti..."
cp "${ROOT_DIR}/docker-compose.yml" "${WORK_DIR}/docker-compose.yml"
if [ -f "${ROOT_DIR}/docker-compose.portable.yml" ]; then
  cp "${ROOT_DIR}/docker-compose.portable.yml" "${WORK_DIR}/docker-compose.portable.yml"
fi
cp "${ROOT_DIR}/.env" "${WORK_DIR}/.env"
cp "${ROOT_DIR}/README.md" "${WORK_DIR}/README.md"

echo "[3/7] Mengekspor Mongo runtime (tanpa downtime)..."
docker exec "${PROJECT_NAME}-mongo" sh -lc "mongodump --archive --gzip --db ${MONGO_DATABASE}" > "${WORK_DIR}/mongo-${MONGO_DATABASE}.archive.gz"

echo "[4/7] Mengekspor ext GenieACS (jika ada)..."
docker exec "${PROJECT_NAME}-genieacs" sh -lc "tar -czf - -C /opt genieacs/ext" > "${WORK_DIR}/genieacs-ext.tar.gz"

echo "[5/7] Menyalin overlay dan source dashboard..."
tar -C "${ROOT_DIR}" -czf "${WORK_DIR}/overlays.tar.gz" overlays
tar -C "${ROOT_DIR}" -czf "${WORK_DIR}/dashboard-app.tar.gz" dashboard/app
if [ -d "${ROOT_DIR}/inventory" ]; then
  tar -C "${ROOT_DIR}" -czf "${WORK_DIR}/inventory.tar.gz" inventory
fi

echo "[6/7] Menulis manifest..."
{
  echo "timestamp=${TS}"
  echo "project=${PROJECT_NAME}"
  echo "mongo_database=${MONGO_DATABASE}"
  echo "cwmp_url=${GENIEACS_CWMP_URL:-unknown}"
  echo "dashboard_url=${PUBLIC_DASHBOARD_URL:-unknown}"
} > "${WORK_DIR}/manifest.env"

(cd "${WORK_DIR}" && sha256sum ./* > SHA256SUMS)

echo "[7/7] Membuat bundle final..."
tar -C "${OUT_BASE}" -czf "${BUNDLE}" "migration-${TS}"

echo ""
echo "Bundle selesai: ${BUNDLE}"
echo "Isi kerja: ${WORK_DIR}"
echo "Next: copy bundle ini ke server target, extract, lalu deploy."
