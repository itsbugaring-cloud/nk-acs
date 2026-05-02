const hourly = Date.now() - 3600000;
const fiveMin = Date.now() - 300000;
declare("Tags.netking_provision_ok", null, {value: true});
declare("Tags.netking_provision_version_2026_04_09_01", null, {value: true});

// Refresh basic parameters hourly
declare("InternetGatewayDevice.DeviceInfo.HardwareVersion", {path: hourly, value: hourly});
declare("InternetGatewayDevice.DeviceInfo.SoftwareVersion", {path: hourly, value: hourly});


//vparam
declare("VirtualParameters.KnownManufacturer", {path: hourly, value: hourly});
declare("VirtualParameters.KnownProductClass", {path: hourly, value: hourly});
declare("VirtualParameters.IP-TR069", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.IP-WANIP", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.IP-WANPPP", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.WANBridge", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.VLAN-PPP1", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.VLAN-PPP2", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.VLAN-PPP3", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.VLAN-PPP4", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.VLAN-IP1", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.VLAN-IP2", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.VLAN-IP3", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.VLAN-IP4", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.Binding-PPP1", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.Binding-PPP2", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.Binding-PPP3", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.Binding-PPP4", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.Binding-IP1", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.Binding-IP2", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.Binding-IP3", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.Binding-IP4", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.PPPUsername", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.TotalStations", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.activedevices", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.DeviceUptime", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.SSID1-Name", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.SSID1-Password", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.SSID1-Security", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.SSID5-Name", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.SSID5-Password", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.WlanPassword", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.WANParameterModel", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.RXPower", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.gettemp", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.OpticalTXPower", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.OpticalVoltage", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.OpticalBiasCurrent", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.LastInformAgeSec", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.OnlineState", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.PeriodicInformIntervalActual", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.ConnectionRequestReachable", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.PPPLastError", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.DefaultGateway", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.PrimaryDNS", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.SecondaryDNS", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.IPv6WAN", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.WiFiChannel", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.WiFiBandwidth", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.WiFiTxPower", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.GuestSSIDState", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.FirstInformAt", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.BootstrapStatus", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.ProvisionVersion", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.LastProvisionResult", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.FirmwareVersionNormalized", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.FirmwareTarget", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.UpgradeState", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.InformJitterSec", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.ConsecutiveInformMiss", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.TaskFailureCount24h", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.LOSCount24h", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.FECErrorRate", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.CRCErrorRate", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.LinkFlapCount24h", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.PPPSessionDrops24h", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.PPPLastUpAt", {path: fiveMin, value: fiveMin});

declare("VirtualParameters.LoginSuperUser", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.LoginSuperPass", {path: fiveMin, value: fiveMin});

//device
declare("InternetGatewayDevice.DeviceInfo.UpTime", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.DeviceInfo.Description", {path: hourly, value: hourly});
declare("InternetGatewayDevice.DeviceInfo.X_TDTC_CustomiseName", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.ManagementServer.ConnectionRequestURL", {path: fiveMin, value: fiveMin});
//WAN
declare("InternetGatewayDevice.WANDevice.1.WANCommonInterfaceConfig.WANAccessType", {path: hourly, value: hourly});
declare("InternetGatewayDevice.WANDevice.1.WANConnectionNumberOfEntries", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*", {path: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANIPConnection.*", {path: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.*", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANIPConnection.*.*", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.X_HW_LANBIND.*", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANIPConnection.*.X_HW_LANBIND.*", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.X_CT-COM_ServiceList", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.X_CT-COM_VLANID", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.X_HW_SERVICELIST", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.X_HW_VLAN", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.X_CMCC_ServiceList", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.X_CMCC_VLANIDMark", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.X_FH_ServiceList", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.VLANID", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANIPConnection.*.X_CT-COM_ServiceList", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANIPConnection.*.X_CT-COM_VLANID", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANIPConnection.*.X_HW_VLAN", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANIPConnection.*.X_CMCC_VLANIDMark", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANIPConnection.*.VLANID", {path: fiveMin, value: fiveMin});
declare("Device.PPP.Interface.*", {path: fiveMin});
declare("Device.PPP.Interface.*.*", {path: fiveMin, value: fiveMin});
declare("Device.IP.Interface.*", {path: fiveMin});
declare("Device.IP.Interface.*.*", {path: fiveMin, value: fiveMin});
declare("Device.NAT.InterfaceSetting.*", {path: fiveMin});
declare("Device.NAT.InterfaceSetting.*.*", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.RXPower", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.TransceiverTemperature", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.X_ALU_OntOpticalParam.RXPower", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.X_ALU_OntOpticalParam.Temperature", {path: fiveMin, value: fiveMin});

//LAN
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*", {path: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.Enable", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.SSID", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.KeyPassphrase", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.PreSharedKey.*.KeyPassphrase", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.PreSharedKey.*.PreSharedKey", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.X_CMS_KeyPassphrase", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.X_HW_KeyPassphrase", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.BeaconType", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.BasicAuthenticationMode", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.WPAAuthenticationMode", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.WPAEncryptionModes", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.IEEE11iEncryptionModes", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.X_HW_AuthenticationMode", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.TotalAssociations", {path: fiveMin, value: fiveMin});
declare("Device.WiFi.SSID.*", {path: fiveMin});
declare("Device.WiFi.SSID.*.SSID", {path: fiveMin, value: fiveMin});
declare("Device.WiFi.AccessPoint.*", {path: fiveMin});
declare("Device.WiFi.AccessPoint.*.Security.KeyPassphrase", {path: fiveMin, value: fiveMin});
declare("Device.WiFi.AccessPoint.*.Security.PreSharedKey", {path: fiveMin, value: fiveMin});
declare("Device.WiFi.AccessPoint.*.Security.ModeEnabled", {path: fiveMin, value: fiveMin});
declare("Device.WiFi.AccessPoint.*.Security.ModesSupported", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.LANEthernetInterfaceConfig.*.*", {path: fiveMin, value: fiveMin});
declare("InternetGatewayDevice.LANDevice.*.Hosts.HostNumberOfEntries", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.OpticalTemperature", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.OpticalRXPower", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.SSID5-Security", {path: fiveMin, value: fiveMin});
declare("VirtualParameters.pppoePassword", {path: fiveMin, value: fiveMin});

let productDeclaration = declare('DeviceID.ProductClass', {value: fiveMin});
const product = productDeclaration && productDeclaration.value && productDeclaration.value[0] ? productDeclaration.value[0] : '';
if (product != "GM220-S" &&
    product != "GM630" &&
   	product != "TOTOLINK_N100RE" &&
   	product != "TOTOLINK_N200RE" &&
   	product != "TOTOLINK_N300RT"){
    declare("InternetGatewayDevice.LANDevice.*.Hosts.Host.*.HostName", {path: fiveMin, value: fiveMin});
    declare("InternetGatewayDevice.LANDevice.*.Hosts.Host.*.IPAddress", {path: fiveMin, value: fiveMin});
    declare("InternetGatewayDevice.LANDevice.*.Hosts.Host.*.MACAddress", {path: fiveMin, value: fiveMin});
}
