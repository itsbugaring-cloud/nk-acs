const fs = require("node:fs");
const path = require("node:path");
const crypto = require("node:crypto");
const { MongoClient } = require("mongodb");

const mongoUrl = `${process.env.MONGO_URI}/${process.env.MONGO_DATABASE}`;
const databaseName = process.env.MONGO_DATABASE;

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function hashPassword(password, salt) {
  return crypto.pbkdf2Sync(password, salt, 10000, 128, "sha512").toString("hex");
}

async function waitForBaseImport(db) {
  for (let attempt = 1; attempt <= 60; attempt += 1) {
    const marker = await db.collection("bootstrap_meta").findOne({ _id: "base_import" });
    if (marker && marker.completed) return;
    console.log(`[genieacs-init] waiting for base import (${attempt}/60)`);
    await sleep(2000);
  }

  throw new Error("Timed out waiting for baseline Mongo import");
}

async function upsertScripts(db, collectionName, directory) {
  if (!fs.existsSync(directory)) return 0;

  const files = fs.readdirSync(directory)
    .filter((file) => file.endsWith(".js"))
    .sort((left, right) => left.localeCompare(right));

  for (const file of files) {
    const script = fs.readFileSync(path.join(directory, file), "utf8");
    const id = path.basename(file, ".js");
    await db.collection(collectionName).updateOne(
      { _id: id },
      { $set: { script } },
      { upsert: true },
    );
  }

  return files.length;
}

async function resetUiConfigIfNeeded(db) {
  const rawValue = process.env.GENIEACS_DISABLE_CUSTOM_UI ?? "true";
  const disableCustomUi = !["false", "0", "no"].includes(String(rawValue).toLowerCase());

  if (!disableCustomUi) {
    return 0;
  }

  const result = await db.collection("config").deleteMany({ _id: /^ui\./ });
  return result.deletedCount ?? 0;
}

async function removeDangerousCredentialWriters(db) {
  const provisionIds = ["useradmin"];
  const presetIds = ["useradmin"];
  const virtualParameterIds = ["superAdmin", "superPassword", "userAdmin", "userPassword"];

  const provisionResult = await db.collection("provisions").deleteMany({ _id: { $in: provisionIds } });
  const presetResult = await db.collection("presets").deleteMany({ _id: { $in: presetIds } });
  const virtualParameterResult = await db.collection("virtualParameters").deleteMany({ _id: { $in: virtualParameterIds } });

  return {
    provisions: provisionResult.deletedCount ?? 0,
    presets: presetResult.deletedCount ?? 0,
    virtualParameters: virtualParameterResult.deletedCount ?? 0,
  };
}

async function main() {
  const client = new MongoClient(mongoUrl);
  await client.connect();

  try {
    const db = client.db(databaseName);
    await waitForBaseImport(db);

    const cwmpAuthExpression = `AUTH(${JSON.stringify(process.env.GENIEACS_CPE_USERNAME)}, ${JSON.stringify(process.env.GENIEACS_CPE_PASSWORD)})`;
    await db.collection("config").updateOne(
      { _id: "cwmp.auth" },
      { $set: { value: cwmpAuthExpression } },
      { upsert: true },
    );

    const username = process.env.GENIEACS_ADMIN_USERNAME;
    const password = process.env.GENIEACS_ADMIN_PASSWORD;
    const salt = crypto.randomBytes(64).toString("hex");
    await db.collection("users").updateOne(
      { _id: username },
      {
        $set: {
          salt,
          password: hashPassword(password, salt),
          roles: "admin",
        },
      },
      { upsert: true },
    );

    const provisionCount = await upsertScripts(db, "provisions", "/overlays/provisions");
    const virtualParameterCount = await upsertScripts(db, "virtualParameters", "/overlays/virtual-params");
    const removedUiConfigCount = await resetUiConfigIfNeeded(db);
    const removedDangerousCredentialWriters = await removeDangerousCredentialWriters(db);

    await db.collection("bootstrap_meta").updateOne(
      { _id: "overlay_import" },
      {
        $set: {
          completed: true,
          updatedAt: new Date(),
          provisions: provisionCount,
          virtualParameters: virtualParameterCount,
          removedUiConfig: removedUiConfigCount,
          removedDangerousCredentialWriters,
        },
      },
      { upsert: true },
    );

    console.log(`[genieacs-init] cwmp.auth configured for ${process.env.GENIEACS_CPE_USERNAME}`);
    console.log(`[genieacs-init] admin user upserted: ${username}`);
    console.log(`[genieacs-init] imported ${provisionCount} provisions and ${virtualParameterCount} virtual parameters`);
    console.log(`[genieacs-init] removed ${removedUiConfigCount} custom UI config entries`);
    console.log(`[genieacs-init] removed dangerous credential writers: ${JSON.stringify(removedDangerousCredentialWriters)}`);
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error("[genieacs-init] failed:", error);
  process.exit(1);
});
