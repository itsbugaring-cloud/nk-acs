let m = "";
let writable = true;
const now = Date.now();
let paths = [];

for (let i = 1; i <= 8; i++) {
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANPPPConnection.1.X_CT-COM_VLANID`);
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANPPPConnection.1.X_HW_VLAN`);
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANPPPConnection.1.X_CMCC_VLANIDMark`);
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANPPPConnection.1.VLANID`);
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANIPConnection.1.X_CT-COM_VLANID`);
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANIPConnection.1.X_HW_VLAN`);
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANIPConnection.1.X_CMCC_VLANIDMark`);
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANIPConnection.1.VLANID`);
}

if (args[1].value) {
  m = args[1].value[0];
  for (let p of paths) {
    try { declare(p, null, {value: parseInt(m)}); } catch (e) {}
  }
} else {
  for (let p of paths) {
    let d = declare(p, {value: now});
    if (d.size && d.value && d.value[0] !== undefined && d.value[0] !== null && String(d.value[0]).trim() !== "") {
      m = String(d.value[0]);
      break;
    }
  }
  if (!m) { writable = false; m = "N/A"; }
}

return {writable: writable, value: [m, "xsd:unsignedInt"]};
