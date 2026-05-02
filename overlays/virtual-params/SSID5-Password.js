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
  declare(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.KeyPassphrase`, null, {value: m});
  declare(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.PreSharedKey.1.KeyPassphrase`, null, {value: m});
}
else {
  let keys = [
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.KeyPassphrase`,
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.PreSharedKey.1.KeyPassphrase`,
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.PreSharedKey.1.PreSharedKey`,
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.X_HW_KeyPassphrase`,
    `InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.X_CMS_KeyPassphrase`,
    `Device.WiFi.AccessPoint.2.Security.KeyPassphrase`,
    `Device.WiFi.AccessPoint.5.Security.KeyPassphrase`,
    `Device.WiFi.AccessPoint.2.Security.PreSharedKey`,
    `Device.WiFi.AccessPoint.5.Security.PreSharedKey`
  ];

  for (let i = 1; i <= 8; i++) {
    if (i !== 5) {
      keys.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.KeyPassphrase`);
      keys.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.PreSharedKey.1.KeyPassphrase`);
      keys.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.PreSharedKey.1.PreSharedKey`);
      keys.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.X_HW_KeyPassphrase`);
      keys.push(`InternetGatewayDevice.LANDevice.1.WLANConfiguration.${i}.X_CMS_KeyPassphrase`);
      keys.push(`Device.WiFi.AccessPoint.${i}.Security.KeyPassphrase`);
      keys.push(`Device.WiFi.AccessPoint.${i}.Security.PreSharedKey`);
    }
  }

  m = firstValue(keys);
}

return {writable: true, value: [m, "xsd:string"]};
