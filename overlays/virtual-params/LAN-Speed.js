function normalizePort(raw) {
  if (raw === null || raw === undefined) return "1";
  const text = String(raw).trim();
  const exact = text.match(/^\d+$/);
  if (exact) return exact[0];
  const firstDigits = text.match(/\d+/);
  return firstDigits ? firstDigits[0] : "1";
}

const port = normalizePort(args && args[0]);
let value = "";
let writable = true;

const paths = [
  `InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.${port}.MaxBitRate`,
  `Device.Ethernet.Interface.${port}.MaxBitRate`,
];

function readPath(path) {
  try {
    const declared = declare(path, { value: Date.now() - 60000 });
    for (const item of declared) {
      if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
      const parsed = String(item.value[0]).trim();
      if (parsed !== "") return parsed;
    }
  } catch (err) {
    // Ignore invalid/non-existent paths for vendor variants.
  }
  return "";
}

const incoming = args && args[1] && args[1].value && args[1].value[0] !== undefined
  ? String(args[1].value[0]).trim()
  : "";

if (incoming !== "") {
  value = incoming;
  for (const path of paths) {
    try {
      declare(path, null, { value: value });
    } catch (err) {
      // Best-effort write only.
    }
  }
} else {
  for (const path of paths) {
    value = readPath(path);
    if (value !== "") break;
  }
  if (value === "") {
    writable = false;
    value = "N/A";
  }
}

return { writable: writable, value: [value, "xsd:string"] };
