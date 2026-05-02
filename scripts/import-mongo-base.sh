#!/bin/sh
set -eu

mongo_admin_uri="${MONGO_URI}/admin"
mongo_restore_uri="${MONGO_URI}"

echo "[mongo-seed] waiting for MongoDB"
until mongosh "${mongo_admin_uri}" --quiet --eval "db.runCommand({ ping: 1 }).ok" >/dev/null 2>&1; do
  sleep 2
done

existing_config_docs="$(mongosh "${mongo_admin_uri}" --quiet --eval "db.getSiblingDB('${MONGO_DATABASE}').getCollection('config').countDocuments({})")"

if [ "${existing_config_docs}" = "0" ]; then
  echo "[mongo-seed] restoring baseline dump into ${MONGO_DATABASE}"
  mongorestore --uri="${mongo_restore_uri}" --db "${MONGO_DATABASE}" --drop /seed/mongo-base
else
  echo "[mongo-seed] baseline already present, skipping restore"
fi

echo "[mongo-seed] writing bootstrap marker"
mongosh "${mongo_admin_uri}" --quiet --eval "db.getSiblingDB('${MONGO_DATABASE}').getCollection('bootstrap_meta').updateOne({_id: 'base_import'}, {\$set: {completed: true, updatedAt: new Date()}}, {upsert: true})" >/dev/null

echo "[mongo-seed] done"
