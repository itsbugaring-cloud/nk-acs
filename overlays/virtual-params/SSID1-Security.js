let m = "";
const instanceIndex = 1;
const now = Date.now();

function readFirst(paths) {
  for (let path of paths) {
    let d = declare(path, {value: now});
    if (d.size && d.value && d.value[0] !== undefined && d.value[0] !== null && String(d.value[0]).trim() !== "") {
      return String(d.value[0]).trim();
    }
  }
  return "";
}

function hasNonEmptyValue(paths) {
  return readFirst(paths) !== "";
}

function normalizeSecurity(mode, auth, wpaEnc, ieeeEnc, hasPassword) {
  let raw = [mode, auth, wpaEnc, ieeeEnc].filter(Boolean).join("|").toUpperCase();

  if (raw.includes("SAE") || raw.includes("WPA3")) return "WPA3-SAE";
  if (raw.includes("WPAAND11I") || raw.includes("WPA-WPA2") || raw.includes("WPAWPA2") || (raw.includes("WPA") && (raw.includes("11I") || raw.includes("WPA2")))) {
    return "WPA/WPA2-PSK";
  }
  if (raw.includes("11I") || raw.includes("WPA2")) return "WPA2-PSK";
  if (raw.includes("WPA")) return "WPA-PSK";
  if (raw.includes("WEP")) return "WEP";
  if (raw.includes("BASIC") || raw.includes("OPEN") || raw.includes("NONE")) return hasPassword ? "Secured" : "Open";
  return hasPassword ? "Secured" : "Open";
}

function writeSecurity(desired) {
  let profile = String(desired || "").trim().toUpperCase();
  let beaconType = "Basic";
  let modeEnabled = "None";
  let basicAuth = "None";
  let wpaAuth = "";
  let wpaEnc = "";
  let ieeeEnc = "";

  if (profile === "WPA2-PSK" || profile === "WPA2PSK") {
    beaconType = "11i";
    modeEnabled = "WPA2-Personal";
    wpaAuth = "PSKAuthentication";
    ieeeEnc = "AESEncryption";
  } else if (profile === "WPA-PSK" || profile === "WPAPSK") {
    beaconType = "WPA";
    modeEnabled = "WPA-Personal";
    wpaAuth = "PSKAuthentication";
    wpaEnc = "TKIPEncryption";
  } else if (profile === "WPA/WPA2-PSK" || profile === "WPA2PSKWPAPSK" || profile === "MIXED") {
    beaconType = "WPAand11i";
    modeEnabled = "WPA-WPA2-Personal";
    wpaAuth = "PSKAuthentication";
    wpaEnc = "TKIPandAESEncryption";
    ieeeEnc = "AESEncryption";
  } else if (profile === "WEP") {
    beaconType = "Basic";
    modeEnabled = "WEP-64";
    basicAuth = "SharedAuthentication";
  }

  let writes = [
    [`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.BeaconType`, beaconType],
    [`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.BasicAuthenticationMode`, basicAuth],
    [`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.WPAAuthenticationMode`, wpaAuth],
    [`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.WPAEncryptionModes`, wpaEnc],
    [`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.IEEE11iEncryptionModes`, ieeeEnc],
    [`Device.WiFi.AccessPoint.${instanceIndex}.Security.ModeEnabled`, modeEnabled]
  ];

  for (let entry of writes) {
    try {
      if (entry[1] !== "") declare(entry[0], null, {value: entry[1]});
    } catch (e) {
    }
  }
}

if (args[1].value) {
  m = String(args[1].value[0] || "");
  writeSecurity(m);
} else {
  let mode = readFirst([
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.BeaconType`,
    `Device.WiFi.AccessPoint.${instanceIndex}.Security.ModeEnabled`,
    `Device.WiFi.AccessPoint.${instanceIndex}.Security.ModesSupported`,
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.X_HW_AuthenticationMode`
  ]);
  let auth = readFirst([
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.WPAAuthenticationMode`,
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.BasicAuthenticationMode`,
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.X_HW_AuthenticationMode`
  ]);
  let wpaEnc = readFirst([
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.WPAEncryptionModes`,
    `Device.WiFi.AccessPoint.${instanceIndex}.Security.ModesSupported`
  ]);
  let ieeeEnc = readFirst([
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.IEEE11iEncryptionModes`
  ]);
  let hasPassword = hasNonEmptyValue([
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.KeyPassphrase`,
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.PreSharedKey.1.KeyPassphrase`,
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.PreSharedKey.1.PreSharedKey`,
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.X_HW_KeyPassphrase`,
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.X_CMS_KeyPassphrase`,
    `Device.WiFi.AccessPoint.${instanceIndex}.Security.KeyPassphrase`,
    `Device.WiFi.AccessPoint.${instanceIndex}.Security.PreSharedKey`
  ]);

  m = normalizeSecurity(mode, auth, wpaEnc, ieeeEnc, hasPassword);
}

return {writable: true, value: [m, "xsd:string"]};
