let m = "";
let writable = true;
const now = Date.now();

function firstValue(paths) {
  for (let path of paths) {
    let d = declare(path, {value: now});
    if (d.size && d.value && d.value[0] !== undefined && d.value[0] !== null && String(d.value[0]).trim() !== "") {
      return String(d.value[0]).trim();
    }
  }
  return "";
}

let readPaths = [];
for (let i = 1; i <= 8; i++) {
  readPaths.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.PreSharedKey.1.KeyPassphrase`);
  readPaths.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.PreSharedKey.1.PreSharedKey`);
  readPaths.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.KeyPassphrase`);
  readPaths.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.X_HW_KeyPassphrase`);
  readPaths.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.X_CMS_KeyPassphrase`);
  readPaths.push(`Device.WiFi.AccessPoint.${i}.Security.KeyPassphrase`);
  readPaths.push(`Device.WiFi.AccessPoint.${i}.Security.PreSharedKey`);
}

if (args[1].value) {
  m = String(args[1].value[0] || "");
  for (let i = 1; i <= 8; i++) {
    let writePaths = [
      `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.PreSharedKey.1.KeyPassphrase`,
      `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.KeyPassphrase`,
      `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.X_HW_KeyPassphrase`,
      `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.X_CMS_KeyPassphrase`,
      `Device.WiFi.AccessPoint.${i}.Security.KeyPassphrase`,
      `Device.WiFi.AccessPoint.${i}.Security.PreSharedKey`
    ];
    for (let path of writePaths) {
      try { declare(path, null, {value: m}); } catch (e) {}
    }
  }
} else {
  m = firstValue(readPaths);
  if (!m) {
    writable = false;
    m = "N/A";
  }
}

return {writable: writable, value: [m, "xsd:string"]};
