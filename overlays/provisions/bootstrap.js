const now = Date.now();

// Clear cached data model to force a refresh
clear("Device", now);
clear("InternetGatewayDevice", now);

// Mark that the device has at least completed a BOOTSTRAP session in Netking.
declare("Tags.netking_bootstrap_seen", null, {value: true});
declare("Tags.netking_provision_ok", null, {value: true});
declare("Tags.netking_provision_version_2026_04_09_01", null, {value: true});

// Force early rediscovery of the management and WAN/WiFi trees so first-inform data is not half-empty.
declare("InternetGatewayDevice.ManagementServer.*", {path: now, value: now});
declare("Device.ManagementServer.*", {path: now, value: now});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.*", {path: now, value: now});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANIPConnection.*.*", {path: now, value: now});
declare("Device.PPP.Interface.*.*", {path: now, value: now});
declare("Device.IP.Interface.*.*", {path: now, value: now});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.*", {path: now, value: now});
declare("Device.WiFi.SSID.*.SSID", {path: now, value: now});
declare("Device.WiFi.AccessPoint.*.Security.*", {path: now, value: now});
