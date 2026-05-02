let m = "";
let writable = true;
const now = Date.now();
let paths = [];

for (let i = 1; i <= 8; i++) {
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANPPPConnection.1.NATEnabled`);
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANIPConnection.1.NATEnabled`);
  paths.push(`Device.NAT.InterfaceSetting.${i}.Enable`);
}

if (args[1].value) {
  m = args[1].value[0];
  for (let p of paths) {
    try { declare(p, null, {value: m === "true" || m === true}); } catch (e) {}
  }
} else {
  for (let p of paths) {
    let d = declare(p, {value: now});
    if (d.size && d.value && d.value[0] !== undefined) {
      m = String(d.value[0]);
      break;
    }
  }
  if (!m) { writable = false; m = "N/A"; }
}

return {writable: writable, value: [m, "xsd:boolean"]};
