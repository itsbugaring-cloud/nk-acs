let m = "";
let writable = true;
const now = Date.now();
let paths = [];

for (let i = 1; i <= 8; i++) {
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANPPPConnection.1.X_CT-COM_ServiceList`);
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANPPPConnection.1.X_HW_SERVICELIST`);
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANPPPConnection.1.X_CMCC_ServiceList`);
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANPPPConnection.1.X_FH_ServiceList`);
  paths.push(`InternetGatewayDevice.WANDevice.1.WANIPConnection.${i}.X_CT-COM_ServiceList`);
}

if (args[1].value) {
  m = args[1].value[0];
  for (let p of paths) {
    try { declare(p, null, {value: m}); } catch (e) {}
  }
} else {
  for (let p of paths) {
    let d = declare(p, {value: now});
    if (d.size && d.value && d.value[0]) {
      m = String(d.value[0]);
      break;
    }
  }
  if (!m) { writable = false; m = "N/A"; }
}

return {writable: writable, value: [m, "xsd:string"]};
