let pw = "";
let writable = true;
const now = Date.now();
let paths = [];

for (let i = 1; i <= 8; i++) {
  paths.push(`InternetGatewayDevice.WANDevice.1.WANConnectionDevice.${i}.WANPPPConnection.1.Password`);
  paths.push(`Device.PPP.Interface.${i}.Password`);
}

if (args[1].value) {
  pw = String(args[1].value[0] || "");
  for (let p of paths) {
    try { declare(p, null, {value: pw}); } catch (e) {}
  }
} else {
  for (let p of paths) {
    let d = declare(p, {value: now});
    if (d.size && d.value && d.value[0] !== undefined && d.value[0] !== null && String(d.value[0]).trim() !== "") {
      pw = String(d.value[0]).trim();
      break;
    }
  }

  if (!pw) {
    writable = false;
    pw = "N/A";
  }
}

return {writable: writable, value: [pw, "xsd:string"]};
