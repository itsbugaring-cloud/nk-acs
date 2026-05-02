let m = "";
const instanceIndex = '1';

function firstValue(keys) {
  for (let key of keys) {
    let d = declare(key, {value: Date.now()});
    if (d.size && d.value && d.value[0]) {
      return d.value[0];
    }
  }
  return "";
}

if (args[1].value) {
  m = args[1].value[0];
  declare(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.SSID`, null, {value: m});
}
else {
  let keys = [
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.SSID`,
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.${instanceIndex}.X_HW_SSIDName`,
    `Device.WiFi.SSID.1.SSID`
  ];

  for (let i = 2; i <= 8; i++) {
    keys.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.SSID`);
    keys.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.X_HW_SSIDName`);
    keys.push(`Device.WiFi.SSID.${i}.SSID`);
  }

  m = firstValue(keys);
}

return {writable: true, value: [m, "xsd:string"]};
