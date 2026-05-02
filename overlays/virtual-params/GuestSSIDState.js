function readEnable(paths) {
    const ts = Date.now() - (5 * 60 * 1000);
    for (const path of paths) {
        const declared = declare(path, { path: ts, value: ts });
        for (const item of declared) {
            if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
            const raw = String(item.value[0]).trim().toLowerCase();
            if (raw === "true" || raw === "1" || raw === "enabled") return "enabled";
            if (raw === "false" || raw === "0" || raw === "disabled") return "disabled";
        }
    }
    return "unknown";
}

const result = readEnable([
    "InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.Enable",
    "InternetGatewayDevice.LANDevice.1.WLANConfiguration.4.Enable",
    "Device.WiFi.SSID.5.Enable",
    "Device.WiFi.AccessPoint.5.Enable",
    "Device.WiFi.SSID.4.Enable",
    "Device.WiFi.AccessPoint.4.Enable",
]);

return { writable: false, value: [result, "xsd:string"] };
