const hourly = Date.now() - 3600000;
const fiveMin = Date.now() - 300000;
const acsUrl = "http://10.88.0.100:7547";
const acsUsername = "caesarbugar";
const acsPassword = "CaesarBugar007";
const informInterval = 120;
declare("Tags.netking_provision_ok", null, {value: true});
declare("Tags.netking_provision_version_2026_04_09_01", null, {value: true});

// Keep the ACS endpoint aligned with the production deployment.
declare("InternetGatewayDevice.ManagementServer.URL", {value: hourly}, {value: acsUrl});
declare("InternetGatewayDevice.ManagementServer.Username", {value: hourly}, {value: acsUsername});
declare("InternetGatewayDevice.ManagementServer.Password", {value: hourly}, {value: acsPassword});
declare("InternetGatewayDevice.ManagementServer.PeriodicInformEnable", {value: hourly}, {value: true});
declare("InternetGatewayDevice.ManagementServer.PeriodicInformInterval", {value: hourly}, {value: informInterval});

declare("Device.ManagementServer.URL", {value: hourly}, {value: acsUrl});
declare("Device.ManagementServer.Username", {value: hourly}, {value: acsUsername});
declare("Device.ManagementServer.Password", {value: hourly}, {value: acsPassword});
declare("Device.ManagementServer.PeriodicInformEnable", {value: hourly}, {value: true});
declare("Device.ManagementServer.PeriodicInformInterval", {value: hourly}, {value: informInterval});

// Do not overwrite ConnectionRequest credentials blindly; many ONTs use site-specific values.
// Instead, refresh the URL so the dashboard can decide whether a direct summon is possible.
declare("InternetGatewayDevice.ManagementServer.ConnectionRequestURL", {value: fiveMin});
declare("Device.ManagementServer.ConnectionRequestURL", {value: fiveMin});

// Pull the key areas we care about right after inform so first-render data is populated faster.
declare("VirtualParameters", {path: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.*", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANIPConnection.*.*", {path: fiveMin, value: fiveMin});
declare("Device.PPP.Interface.*.*", {path: fiveMin, value: fiveMin});
declare("Device.IP.Interface.*.*", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.*", {path: fiveMin, value: fiveMin});
declare("Device.WiFi.SSID.*.SSID", {path: fiveMin, value: fiveMin});
declare("Device.WiFi.AccessPoint.*.Security.*", {path: fiveMin, value: fiveMin});
// CATATAN: VP credentials (superAdmin, superPassword, WebAdmin-User, dll)
// TIDAK di-declare di sini karena VP tersebut dapat menulis ke device.
// Credentials dibaca dari cache MongoDB yang sudah ada.
// Gunakan tombol "Get Credentials" di device detail untuk refresh manual.

// === VirtualParameter values — WAJIB agar nilai VP dihitung dan disimpan ===
// Redaman optik (optical power) - evaluasi VP OpticalRXPower setiap inform
declare("VirtualParameters.OpticalRXPower", {value: fiveMin});
declare("VirtualParameters.OpticalTemperature", {value: fiveMin});

// WiFi SSID & Password
declare("VirtualParameters.SSID1-Name",     {value: hourly});
declare("VirtualParameters.SSID1-Password", {value: hourly});
declare("VirtualParameters.SSID5-Name",     {value: hourly});
declare("VirtualParameters.SSID5-Password", {value: hourly});
declare("VirtualParameters.WlanPassword",   {value: hourly});
declare("VirtualParameters.OpticalTXPower", {value: fiveMin});
declare("VirtualParameters.OpticalVoltage", {value: fiveMin});
declare("VirtualParameters.OpticalBiasCurrent", {value: fiveMin});
declare("VirtualParameters.LastInformAgeSec", {value: fiveMin});
declare("VirtualParameters.OnlineState", {value: fiveMin});
declare("VirtualParameters.PeriodicInformIntervalActual", {value: fiveMin});
declare("VirtualParameters.ConnectionRequestReachable", {value: fiveMin});
declare("VirtualParameters.PPPLastError", {value: fiveMin});
declare("VirtualParameters.DefaultGateway", {value: fiveMin});
declare("VirtualParameters.PrimaryDNS", {value: fiveMin});
declare("VirtualParameters.SecondaryDNS", {value: fiveMin});
declare("VirtualParameters.IPv6WAN", {value: fiveMin});
declare("VirtualParameters.WiFiChannel", {value: fiveMin});
declare("VirtualParameters.WiFiBandwidth", {value: fiveMin});
declare("VirtualParameters.WiFiTxPower", {value: fiveMin});
declare("VirtualParameters.GuestSSIDState", {value: fiveMin});
declare("VirtualParameters.FirstInformAt", {value: fiveMin});
declare("VirtualParameters.BootstrapStatus", {value: fiveMin});
declare("VirtualParameters.ProvisionVersion", {value: fiveMin});
declare("VirtualParameters.LastProvisionResult", {value: fiveMin});
declare("VirtualParameters.FirmwareVersionNormalized", {value: fiveMin});
declare("VirtualParameters.FirmwareTarget", {value: fiveMin});
declare("VirtualParameters.UpgradeState", {value: fiveMin});
declare("VirtualParameters.InformJitterSec", {value: fiveMin});
declare("VirtualParameters.ConsecutiveInformMiss", {value: fiveMin});
declare("VirtualParameters.TaskFailureCount24h", {value: fiveMin});
declare("VirtualParameters.LOSCount24h", {value: fiveMin});
declare("VirtualParameters.FECErrorRate", {value: fiveMin});
declare("VirtualParameters.CRCErrorRate", {value: fiveMin});
declare("VirtualParameters.LinkFlapCount24h", {value: fiveMin});
declare("VirtualParameters.PPPSessionDrops24h", {value: fiveMin});
declare("VirtualParameters.PPPLastUpAt", {value: fiveMin});

// PPPoE credentials & WAN info
declare("VirtualParameters.pppoeUsername",  {value: hourly});
declare("VirtualParameters.pppoePassword",  {value: hourly});
declare("VirtualParameters.pppoeIP",        {value: fiveMin});
declare("VirtualParameters.PPPUsername",    {value: hourly});

// Raw optical parameter paths (fallback jika VP belum compute)
declare("InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.*", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.*", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.*", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.*", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.1.X_VSOL_PON_Interface.*", {path: fiveMin, value: fiveMin});
