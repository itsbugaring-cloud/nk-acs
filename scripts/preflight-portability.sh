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

fail() {
  echo "[FAIL] $1" >&2
  exit 1
}

pass() {
  echo "[OK] $1"
}

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || fail "command '$1' tidak ditemukan"
}

need_cmd docker
need_cmd tar
need_cmd gzip

[ -f "${ROOT_DIR}/docker-compose.yml" ] || fail "docker-compose.yml tidak ditemukan"
[ -f "${ROOT_DIR}/.env" ] || fail ".env tidak ditemukan"
[ -d "${ROOT_DIR}/overlays" ] || fail "folder overlays tidak ditemukan"
[ -d "${ROOT_DIR}/dashboard/app" ] || fail "folder dashboard/app tidak ditemukan"
pass "struktur file baseline lengkap"

docker compose --project-directory "${ROOT_DIR}" --env-file "${ROOT_DIR}/.env" config >/dev/null
pass "docker compose config valid"

for svc in mongo genieacs dashboard; do
  cname="${PROJECT_NAME}-${svc}"
  docker ps --format '{{.Names}}' | grep -qx "${cname}" || fail "container ${cname} tidak running"
done
pass "container utama running"

docker exec "${PROJECT_NAME}-mongo" mongosh "mongodb://localhost:27017/${MONGO_DATABASE}" --quiet --eval "db.runCommand({ ping: 1 }).ok" | grep -q "1" || fail "koneksi mongo gagal"
pass "mongo ping sukses"

echo ""
echo "Preflight portability selesai. Sistem siap dibuat migration bundle."
