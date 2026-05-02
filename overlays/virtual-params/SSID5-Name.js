let m = "";

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
  declare(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSID`, null, {value: m});
}
else {
  let keys = [
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSID`,
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.X_HW_SSIDName`,
    `Device.WiFi.SSID.2.SSID`,
    `Device.WiFi.SSID.5.SSID`
  ];

  for (let i = 1; i <= 8; i++) {
    if (i !== 5) {
      keys.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.SSID`);
      keys.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.X_HW_SSIDName`);
      keys.push(`Device.WiFi.SSID.${i}.SSID`);
    }
  }

  m = firstValue(keys);
}

return {writable: true, value: [m, "xsd:string"]};
